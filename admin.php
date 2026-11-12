<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$adminCssVersion = @filemtime(__DIR__ . '/assets/css/admin.css') ?: time();

header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$isAuthed = !empty($_SESSION['admin_authed']);
$errors = [];
$usingDefaultPassword = password_verify(
    'WiztechForever2026',
    (string) config_value('admin.password_hash', '')
);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $csrf = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';

    if (!verify_csrf($csrf)) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($action === 'login') {
        $fails = (int) ($_SESSION['admin_fails'] ?? 0);
        $lastFail = (int) ($_SESSION['admin_last_fail'] ?? 0);
        $password = $_POST['password'] ?? '';

        if ($fails >= 5 && (time() - $lastFail) < 300) {
            $errors[] = 'Too many attempts. Please wait a few minutes and try again.';
        } else {
            $hash = (string) config_value('admin.password_hash', '');

            if ($hash !== '' && is_string($password) && password_verify($password, $hash)) {
                session_regenerate_id(true);
                $_SESSION['admin_authed'] = true;
                unset($_SESSION['admin_fails'], $_SESSION['admin_last_fail'], $_SESSION['csrf_token']);
                header('Location: admin.php');
                exit;
            }

            $_SESSION['admin_fails'] = $fails + 1;
            $_SESSION['admin_last_fail'] = time();
            usleep(600000);
            $errors[] = 'That password is not correct.';
        }
    } elseif ($action === 'logout') {
        if ($isAuthed) {
            unset($_SESSION['admin_authed']);
            session_regenerate_id(true);
            header('Location: admin.php');
            exit;
        }
        $errors[] = 'You are already signed out.';
    } elseif ($action === 'delete_response') {
        $deleteResponseId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$isAuthed) {
            $errors[] = 'Please sign in to delete a response.';
        } elseif ($deleteResponseId === false || $deleteResponseId < 1) {
            $errors[] = 'That response could not be found.';
        } else {
            try {
                $stmt = db()->prepare('DELETE FROM responses WHERE id = :id');
                $stmt->execute([':id' => $deleteResponseId]);

                if ($stmt->rowCount() > 0) {
                    header('Location: admin.php?deleted=1');
                    exit;
                }

                $errors[] = 'That response no longer exists.';
            } catch (Throwable $e) {
                error_log('[for-gloria] delete failed: ' . $e->getMessage());
                $errors[] = 'That response could not be deleted. Please try again.';
            }
        }
    }
}

$isAuthed = !empty($_SESSION['admin_authed']);

// ── CSV export ───────────────────────────────────────────────────────────────

if ($isAuthed && isset($_GET['export'])) {
    try {
        $rows = db()->query(
            'SELECT id, session_token, comfortable, likes, dislikes, future_serious,
                    lovely_future, forever, current_step, completed, ip_address,
                    user_agent, created_at, updated_at
               FROM responses
              ORDER BY created_at DESC'
        )->fetchAll();
    } catch (Throwable $e) {
        error_log('[for-gloria] export failed: ' . $e->getMessage());
        http_response_code(503);
        exit('Responses could not be exported right now.');
    }

    $safe = static function (mixed $value): string {
        $value = (string) ($value ?? '');
        if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'" . $value;
        }
        return $value;
    };

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gloria-responses-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }

    fputcsv($out, [
        'ID', 'Session token', 'Comfortable', 'Likes', 'Dislikes', 'Serious future',
        'Lovely future', 'Forever', 'Current step', 'Completed', 'IP address',
        'User agent', 'Created at', 'Updated at',
    ]);

    foreach ($rows as $row) {
        fputcsv($out, array_map($safe, [
            $row['id'],
            $row['session_token'],
            $row['comfortable'],
            $row['likes'],
            $row['dislikes'],
            $row['future_serious'],
            $row['lovely_future'],
            $row['forever'],
            $row['current_step'],
            $row['completed'] ? 'yes' : 'no',
            $row['ip_address'],
            $row['user_agent'],
            $row['created_at'],
            $row['updated_at'],
        ]));
    }

    fclose($out);
    exit;
}

// ── Load responses ───────────────────────────────────────────────────────────

$rows = [];
$stats = ['total' => 0, 'completed' => 0, 'yes' => 0, 'no' => 0, 'not_ready' => 0, 'in_progress' => 0];
$detail = null;
$loadError = '';

$viewId = isset($_GET['view']) ? filter_var($_GET['view'], FILTER_VALIDATE_INT) : false;
$viewId = $viewId !== false && $viewId > 0 ? (int) $viewId : null;

$deleteId = isset($_GET['delete']) ? filter_var($_GET['delete'], FILTER_VALIDATE_INT) : false;
$deleteId = $deleteId !== false && $deleteId > 0 ? (int) $deleteId : null;
$deleteTarget = null;

if ($isAuthed) {
    try {
        $pdo = db();

        $summary = $pdo->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(completed = 1), 0) AS completed,
                    COALESCE(SUM(forever = 'yes'), 0) AS yes_count,
                    COALESCE(SUM(forever = 'no'), 0) AS no_count,
                    COALESCE(SUM(forever = 'not_ready'), 0) AS not_ready_count
               FROM responses"
        )->fetch();

        if (is_array($summary)) {
            $stats['total'] = (int) $summary['total'];
            $stats['completed'] = (int) $summary['completed'];
            $stats['yes'] = (int) $summary['yes_count'];
            $stats['no'] = (int) $summary['no_count'];
            $stats['not_ready'] = (int) $summary['not_ready_count'];
            $stats['in_progress'] = max(0, $stats['total'] - $stats['completed']);
        }

        if ($viewId !== null && $deleteId === null) {
            $stmt = $pdo->prepare('SELECT * FROM responses WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $viewId]);
            $row = $stmt->fetch();
            $detail = is_array($row) ? $row : null;
        }

        if ($deleteId !== null) {
            $stmt = $pdo->prepare(
                'SELECT id, created_at, updated_at, current_step, completed, forever
                   FROM responses
                  WHERE id = :id
                  LIMIT 1'
            );
            $stmt->execute([':id' => $deleteId]);
            $row = $stmt->fetch();
            $deleteTarget = is_array($row) ? $row : null;

            if ($deleteTarget === null) {
                $errors[] = 'That response no longer exists.';
            }
        }

        $rows = $pdo->query(
            'SELECT id, session_token, comfortable, forever, current_step, completed, created_at, updated_at
               FROM responses
              ORDER BY created_at DESC
              LIMIT 300'
        )->fetchAll();
    } catch (Throwable $e) {
        error_log('[for-gloria] admin load failed: ' . $e->getMessage());
        $loadError = "The responses couldn't be loaded. Check the database settings in config.php.";
    }
}

function pretty_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '—';
    }

    return date('M j, Y · g:i a', $timestamp);
}

function final_badge_class(?string $value): string
{
    return match ($value) {
        'yes' => 'badge-yes',
        'no' => 'badge-no',
        'not_ready' => 'badge-not-ready',
        default => 'badge-wait',
    };
}

function final_badge_label(?string $value, string $pending = 'In progress'): string
{
    return match ($value) {
        'yes' => 'YES 💗',
        'no' => 'NO',
        'not_ready' => 'NOT READY',
        default => $pending,
    };
}

function display_choice(mixed $value): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '—';
    }

    return in_array($value, ['yes', 'no'], true) ? strtoupper($value) : $value;
}

function detail_answer(mixed $value): string
{
    $text = trim((string) ($value ?? ''));

    if (in_array($text, ['yes', 'no'], true)) {
        return '<p class="answer-choice">' . e(strtoupper($text)) . '</p>';
    }

    if ($text === '') {
        return '<p class="answer-text">—</p>';
    }

    return '<p class="answer-text">' . nl2br(e($text)) . '</p>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title><?= $deleteTarget !== null ? 'Delete response #' . (int) $deleteTarget['id'] : ($detail !== null ? 'Response #' . (int) $detail['id'] : 'Responses') ?> · For Gloria</title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Poppins:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?= e((string) $adminCssVersion) ?>">
</head>

<body class="admin-body">
<?php if (!$isAuthed): ?>
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="login-title">
            <span class="auth-mark" aria-hidden="true">♥</span>
            <p class="eyebrow">Private dashboard</p>
            <h1 id="login-title">Wiztech, this one is for you.</h1>
            <p class="auth-lede">Enter the admin password to read Gloria’s answers.</p>

            <?php foreach ($errors as $error): ?>
                <p class="alert" role="alert"><?= e($error) ?></p>
            <?php endforeach; ?>

            <form method="post" action="admin.php" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="login">
                <label for="password">Admin password</label>
                <input type="password" id="password" name="password" required autofocus
                       autocomplete="current-password">
                <button class="admin-btn" type="submit">Open dashboard</button>
            </form>

            <p class="auth-foot">This page is private. Never share this link or password.</p>
        </section>
    </main>
<?php else: ?>
    <header class="admin-header">
        <a class="admin-brand" href="admin.php">
            <span class="brand-heart" aria-hidden="true">♥</span>
            <span>
                <strong>For Gloria</strong>
                <small>Private responses</small>
            </span>
        </a>

        <nav class="admin-nav" aria-label="Dashboard">
            <a href="admin.php" <?= $detail === null && $deleteTarget === null ? 'aria-current="page"' : '' ?>>Responses</a>
            <a href="admin.php?export=csv">Export CSV</a>
            <form method="post" action="admin.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="nav-button">Log out</button>
            </form>
        </nav>
    </header>

    <main class="admin-main">
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
            <p class="notice" role="status">Response deleted permanently.</p>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <p class="alert" role="alert"><?= e($error) ?></p>
        <?php endforeach; ?>

        <?php if ($loadError !== ''): ?>
            <p class="alert" role="alert"><?= e($loadError) ?></p>
        <?php endif; ?>

        <?php if ($usingDefaultPassword): ?>
            <p class="notice" role="status">
                <strong>Heads up:</strong> you are still using the default admin password. Change
                <code>admin.password_hash</code> in <code>config.php</code> before sharing this site.
            </p>
        <?php endif; ?>

        <?php if ($deleteTarget !== null): ?>
            <p class="back-link"><a href="admin.php">← All responses</a></p>

            <section class="danger-card" aria-labelledby="delete-title">
                <p class="eyebrow">Permanent action</p>
                <h1 id="delete-title">Delete response #<?= (int) $deleteTarget['id'] ?>?</h1>
                <p class="danger-copy">
                    This will permanently remove this response, including every answer and session detail.
                    This cannot be undone.
                </p>

                <dl class="danger-meta">
                    <div><dt>Started</dt><dd><?= e(pretty_date($deleteTarget['created_at'])) ?></dd></div>
                    <div><dt>Last saved</dt><dd><?= e(pretty_date($deleteTarget['updated_at'])) ?></dd></div>
                    <div>
                        <dt>Progress</dt>
                        <dd>
                            <?= (int) $deleteTarget['current_step'] ?> of 6 ·
                            <?= (int) $deleteTarget['completed'] === 1 ? 'Completed' : 'In progress' ?>
                        </dd>
                    </div>
                </dl>

                <div class="danger-actions">
                    <form method="post" action="admin.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_response">
                        <input type="hidden" name="id" value="<?= (int) $deleteTarget['id'] ?>">
                        <button class="admin-btn admin-btn-danger" type="submit">Delete permanently</button>
                    </form>
                    <a class="admin-btn admin-btn-quiet" href="admin.php">Cancel</a>
                </div>
            </section>
        <?php elseif ($detail !== null): ?>
            <p class="back-link">
                <a href="admin.php">← All responses</a>
                <span aria-hidden="true">·</span>
                <a class="delete-link" href="admin.php?delete=<?= (int) $detail['id'] ?>">Delete response</a>
            </p>

            <section class="detail-head">
                <div>
                    <p class="eyebrow">Response #<?= (int) $detail['id'] ?></p>
                    <h1>Gloria’s answer</h1>
                    <p class="detail-meta">
                        Started <?= e(pretty_date($detail['created_at'])) ?>
                        · Last saved <?= e(pretty_date($detail['updated_at'])) ?>
                    </p>
                </div>
                <span class="badge <?= e(final_badge_class($detail['forever'] ?? null)) ?>">
                    <?= e(final_badge_label($detail['forever'] ?? null)) ?>
                </span>
            </section>

            <div class="detail-grid">
                <section class="detail-card">
                    <h2>Do you feel comfortable around Wiztech?</h2>
                    <p class="answer-choice"><?= e(strtoupper((string) ($detail['comfortable'] ?? '—'))) ?></p>
                </section>

                <section class="detail-card">
                    <h2>What is it that you like about Wiztech?</h2>
                    <?= detail_answer($detail['likes'] ?? null) ?>
                </section>

                <section class="detail-card">
                    <h2>What is it that you don’t like about Wiztech?</h2>
                    <?= detail_answer($detail['dislikes'] ?? null) ?>
                </section>

                <section class="detail-card detail-card-wide">
                    <h2>Do you see yourself having something serious to do with Wiztech in the future?</h2>
                    <p class="answer-choice"><?= e(display_choice($detail['future_serious'] ?? null)) ?></p>
                </section>

                <section class="detail-card detail-card-wide">
                    <h2>Would you want to build a lovely future with him?</h2>
                    <p class="answer-choice"><?= e(display_choice($detail['lovely_future'] ?? null)) ?></p>
                </section>

                <section class="detail-card detail-card-wide detail-final">
                    <h2>Would you want to be his Forever? 💗</h2>
                    <p class="answer-choice">
                        <?= e(final_badge_label($detail['forever'] ?? null, 'Not answered yet')) ?>
                    </p>
                    <p class="detail-meta">
                        Saved step <?= (int) $detail['current_step'] ?> of 6 ·
                        <?= (int) $detail['completed'] === 1 ? 'Completed' : 'Still in progress' ?>
                    </p>
                </section>
            </div>

            <section class="meta-card">
                <h2>Session details</h2>
                <dl>
                    <div><dt>Session token</dt><dd><code><?= e((string) $detail['session_token']) ?></code></dd></div>
                    <div><dt>IP address</dt><dd><?= e((string) ($detail['ip_address'] ?? '—')) ?></dd></div>
                    <div><dt>Device</dt><dd class="ua"><?= e((string) ($detail['user_agent'] ?? '—')) ?></dd></div>
                </dl>
            </section>
        <?php else: ?>
            <section class="dash-head">
                <div>
                    <p class="eyebrow">The answers</p>
                    <h1>Every answer from Gloria</h1>
                    <p class="detail-meta">Private, quiet, and just for you.</p>
                </div>
                <a class="admin-btn" href="admin.php?export=csv">Download CSV</a>
            </section>

            <section class="stats" aria-label="Overview">
                <div class="stat">
                    <span class="stat-label">Sessions</span>
                    <strong><?= (int) $stats['total'] ?></strong>
                </div>
                <div class="stat">
                    <span class="stat-label">In progress</span>
                    <strong><?= (int) $stats['in_progress'] ?></strong>
                </div>
                <div class="stat stat-yes">
                    <span class="stat-label">Said yes</span>
                    <strong><?= (int) $stats['yes'] ?></strong>
                </div>
                <div class="stat">
                    <span class="stat-label">Said no</span>
                    <strong><?= (int) $stats['no'] ?></strong>
                </div>
                <div class="stat stat-not-ready">
                    <span class="stat-label">Not ready</span>
                    <strong><?= (int) $stats['not_ready'] ?></strong>
                </div>
            </section>

            <section class="table-card">
                <div class="table-head">
                    <h2>Recent responses</h2>
                    <span class="count-pill"><?= count($rows) ?> <?= count($rows) === 1 ? 'entry' : 'entries' ?></span>
                </div>

                <?php if ($rows === []): ?>
                    <div class="empty-state">
                        <span aria-hidden="true">♥</span>
                        <h3>No answers yet</h3>
                        <p>When Gloria opens the link, her progress will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-scroll">
                        <table>
                            <thead>
                            <tr>
                                <th scope="col">When</th>
                                <th scope="col">Comfortable</th>
                                <th scope="col">Progress</th>
                                <th scope="col">Final answer</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>
                                        <span class="cell-main"><?= e(pretty_date($row['created_at'])) ?></span>
                                        <span class="cell-sub">Saved <?= e(pretty_date($row['updated_at'])) ?></span>
                                    </td>
                                    <td><?= e(strtoupper((string) ($row['comfortable'] ?? '—'))) ?></td>
                                    <td>
                                        <span class="progress-pill <?= (int) $row['completed'] === 1 ? 'is-complete' : '' ?>">
                                            <?= (int) $row['current_step'] ?> of 6
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= e(final_badge_class($row['forever'] ?? null)) ?>">
                                            <?= e(final_badge_label($row['forever'] ?? null, 'Pending')) ?>
                                        </span>
                                    </td>
                                    <td class="cell-action">
                                        <a href="admin.php?view=<?= (int) $row['id'] ?>">Open →</a>
                                        <a class="delete-link" href="admin.php?delete=<?= (int) $row['id'] ?>">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
<?php endif; ?>
</body>
</html>
