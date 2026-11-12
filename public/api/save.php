<?php

declare(strict_types=1);

/**
 * POST api/save.php
 * Accepts a JSON body for one step, validates it, and persists the answer so
 * Gloria can safely close the page and pick up exactly where she left off.
 */

require dirname(__DIR__, 2) . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'error' => 'method_not_allowed', 'message' => 'Method not allowed.'], 405);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 65536) {
    json_response(['ok' => false, 'error' => 'payload_too_large', 'message' => 'That message is too long.'], 413);
}

try {
    $input = json_decode((string) file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    json_response(['ok' => false, 'error' => 'invalid_json', 'message' => 'Invalid request.'], 400);
}

if (!is_array($input)) {
    json_response(['ok' => false, 'error' => 'invalid_json', 'message' => 'Invalid request.'], 400);
}

// ── CSRF ─────────────────────────────────────────────────────────────────────

$csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!is_string($csrf) || !verify_csrf($csrf)) {
    json_response([
        'ok' => false,
        'error' => 'csrf',
        'message' => 'Your session expired. Refresh the page and try again.',
    ], 419);
}

// ── Step + answer validation ─────────────────────────────────────────────────

$step = filter_var($input['step'] ?? null, FILTER_VALIDATE_INT);
if ($step === false || $step < 1 || $step > 6) {
    json_response(['ok' => false, 'error' => 'invalid_step', 'message' => 'Invalid step.'], 422);
}

$fieldMap = [
    1 => 'comfortable',
    2 => 'likes',
    3 => 'dislikes',
    4 => 'future_serious',
    5 => 'lovely_future',
    6 => 'forever',
];
$field = $fieldMap[$step];

$rawValue = $input[$field] ?? null;

if (in_array($step, [1, 4, 5, 6], true)) {
    $allowed = $step === 6 ? ['yes', 'no', 'not_ready'] : ['yes', 'no'];
    if (!is_string($rawValue) || !in_array($rawValue, $allowed, true)) {
        json_response([
            'ok' => false,
            'error' => 'invalid_answer',
            'message' => $step === 6 ? 'Please choose YES, NO, or NOT READY.' : 'Please choose YES or NO.',
        ], 422);
    }
    $value = $rawValue;
} else {
    $value = clean_text($rawValue, 3, 2000);
    if ($value === null) {
        json_response([
            'ok' => false,
            'error' => 'invalid_answer',
            'message' => 'Please write at least a few words.',
        ], 422);
    }
}

// ── Session token ────────────────────────────────────────────────────────────

$token = isset($input['session_token']) && is_string($input['session_token'])
    ? strtolower(trim($input['session_token']))
    : '';

if ($token === '' && isset($_COOKIE['gloria_token']) && is_string($_COOKIE['gloria_token'])) {
    $token = strtolower(trim($_COOKIE['gloria_token']));
}

if (!valid_session_token($token)) {
    $token = bin2hex(random_bytes(16));
}

$pdo = null;

try {
    $pdo = db();
    $pdo->beginTransaction();

    // Create the row if it does not exist yet; the upsert also locks it.
    $pdo->prepare(
        'INSERT INTO responses (session_token, ip_address, user_agent)
         VALUES (:token, :ip, :ua)
         ON DUPLICATE KEY UPDATE id = id'
    )->execute([
        ':token' => $token,
        ':ip' => client_ip(),
        ':ua' => request_user_agent(),
    ]);

    $stmt = $pdo->prepare('SELECT * FROM responses WHERE session_token = :token LIMIT 1 FOR UPDATE');
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();

    if (!is_array($row)) {
        throw new RuntimeException('Response row could not be loaded.');
    }

    $currentStep = (int) $row['current_step'];

    // Answers must arrive in order, so a guessed request cannot finish the flow.
    if ($step > $currentStep + 1) {
        $pdo->rollBack();
        json_response([
            'ok' => false,
            'error' => 'out_of_order',
            'message' => 'Please finish the previous question first.',
        ], 409);
    }

    $wasCompleted = (int) $row['completed'] === 1;
    $newStep = max($currentStep, $step);
    $completed = ($wasCompleted || $step === 6) ? 1 : 0;

    $update = $pdo->prepare(
        "UPDATE responses
            SET {$field} = :value,
                current_step = :step,
                completed = :completed,
                ip_address = :ip,
                user_agent = :ua,
                updated_at = CURRENT_TIMESTAMP
          WHERE id = :id"
    );
    $update->execute([
        ':value' => $value,
        ':step' => $newStep,
        ':completed' => $completed,
        ':ip' => client_ip(),
        ':ua' => request_user_agent(),
        ':id' => (int) $row['id'],
    ]);

    $stmt->execute([':token' => $token]);
    $saved = $stmt->fetch();

    if (!is_array($saved)) {
        throw new RuntimeException('Saved response could not be reloaded.');
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[for-gloria] save failed: ' . $e->getMessage());

    json_response([
        'ok' => false,
        'error' => 'server_error',
        'message' => "We couldn't save that just now. Please try again.",
    ], 500);
}

// Keep the token available even if localStorage is cleared on her phone.
setcookie('gloria_token', $token, [
    'expires' => time() + 60 * 60 * 24 * 180,
    'path' => '/',
    'secure' => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

if ($step === 6 && !$wasCompleted) {
    notify_completion($saved);
}

json_response([
    'ok' => true,
    'session_token' => $token,
    'csrf_token' => csrf_token(),
    'current_step' => $newStep,
    'completed' => (bool) $completed,
    'forever' => $saved['forever'] ?? null,
]);
