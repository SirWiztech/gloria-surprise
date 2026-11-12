<?php

declare(strict_types=1);

/**
 * Shared bootstrap: configuration, hardened session, security headers,
 * database connection and small helpers used across the app.
 */

define('APP_ROOT', __DIR__);

/**
 * Load KEY=VALUE pairs from .env when present. Real environment variables
 * always take precedence over the file. Supports comments, an optional
 * "export " prefix and single- or double-quoted values.
 */
function load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $index => $line) {
        $line = trim($line);
        if ($index === 0) {
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
        }
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = ltrim(substr($line, 7));
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key) || getenv($key) !== false) {
            continue;
        }

        if (strlen($value) >= 2) {
            $quote = $value[0];
            if (($quote === '"' || $quote === "'") && $value[strlen($value) - 1] === $quote) {
                $value = substr($value, 1, -1);
                if ($quote === '"') {
                    $value = str_replace(['\\n', '\\r', '\\"', '\\\\'], ["\n", "\r", '"', '\\'], $value);
                }
            }
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

load_env_file(APP_ROOT . '/.env');

$configPath = APP_ROOT . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("config.php is missing. Restore it from the project files and try again.\n");
}

/** @var array<string, mixed> $CONFIG */
$CONFIG = require $configPath;

$debug = (bool) ($CONFIG['app']['debug'] ?? false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

date_default_timezone_set((string) ($CONFIG['app']['timezone'] ?? 'Africa/Lagos'));

// ── Session ──────────────────────────────────────────────────────────────────

$isHttps = function (): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
};

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionDays = max(1, (int) ($CONFIG['app']['session_days'] ?? 30));

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * $sessionDays));

    session_name('gloria_sid');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * $sessionDays,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Security headers ─────────────────────────────────────────────────────────

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src https://fonts.gstatic.com; "
        . "img-src 'self' data:; "
        . "media-src 'self'; "
        . "connect-src 'self'; "
        . "form-action 'self'; "
        . "base-uri 'self'; "
        . "frame-ancestors 'none'"
    );
}

// ── Configuration ────────────────────────────────────────────────────────────

function config_value(string $path, mixed $default = null): mixed
{
    global $CONFIG;

    $value = $CONFIG;
    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

// ── Database ─────────────────────────────────────────────────────────────────

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) config_value('db.host', '127.0.0.1');
    $port = (int) config_value('db.port', 3306);
    $name = (string) config_value('db.name', 'gloria_proposal');

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);

    $pdo = new PDO(
        $dsn,
        (string) config_value('db.user', 'root'),
        (string) config_value('db.pass', ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]
    );

    // Keep TIMESTAMP values in the same timezone PHP uses for display.
    $pdo->exec("SET time_zone = '" . date('P') . "'");

    return $pdo;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    $stored = $_SESSION['csrf_token'] ?? '';
    if (!is_string($stored) || $stored === '') {
        return false;
    }

    return hash_equals($stored, $token);
}

// ── Small helpers ────────────────────────────────────────────────────────────

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
}

function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip !== '' && strlen($ip) <= 45 ? $ip : '';
}

function request_user_agent(): string
{
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (!mb_check_encoding($ua, 'UTF-8')) {
        $ua = mb_convert_encoding($ua, 'UTF-8', 'UTF-8');
    }

    return mb_substr($ua, 0, 255);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Trim, normalise and validate free text coming from the client.
 * Returns null when the value is missing, too short or too long.
 */
function clean_text(mixed $value, int $min = 3, int $max = 2000): ?string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        return null;
    }

    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = trim($value);

    $length = mb_strlen($value);
    if ($length < $min || $length > $max) {
        return null;
    }

    return $value;
}

function valid_session_token(string $token): bool
{
    return (bool) preg_match('/^[a-f0-9]{32}$/', $token);
}

/**
 * Email Wiztech once, as soon as Gloria finishes the final step.
 * Never let a mail failure break the save request.
 */
function notify_completion(array $row): void
{
    $to = trim((string) config_value('notify_email', ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = preg_replace('/[^a-z0-9.\-]/i', '', $host) ?: 'localhost';
    $final = (string) ($row['forever'] ?? '');

    $subject = match ($final) {
        'yes' => 'Gloria said YES - her final answer is in',
        'not_ready' => 'Gloria finished the questionnaire - not ready yet',
        default => 'Gloria finished the questionnaire',
    };

    $finalAnswer = match ($final) {
        'yes' => 'YES - she wants to be his Forever',
        'no' => 'NO - she answered honestly',
        'not_ready' => 'NOT READY - she needs more time',
        default => 'Not answered',
    };

    $lines = [
        'Gloria has completed the questionnaire.',
        '',
        'Final answer: ' . $finalAnswer,
        '',
        'Comfortable around Wiztech: ' . ($row['comfortable'] ?? '-'),
        'What she likes: ' . ($row['likes'] ?? '-'),
        "What she doesn't like: " . ($row['dislikes'] ?? '-'),
        'Something serious in the future: ' . ($row['future_serious'] ?? '-'),
        'The future she pictures: ' . ($row['lovely_future'] ?? '-'),
        '',
        'Submitted: ' . ($row['updated_at'] ?? date('Y-m-d H:i:s')),
        'Open the private dashboard for the full answers.',
    ];

    $headers = [
        'From: For Gloria <no-reply@' . $host . '>',
        'Reply-To: no-reply@' . $host,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    @mail($to, $subject, implode("\n", $lines), implode("\r\n", $headers));
}
