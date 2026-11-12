<?php

declare(strict_types=1);

/**
 * GET api/state.php?token=…
 * Returns the saved progress for a session token (or the HttpOnly cookie) so
 * the recipient can resume from the last completed step.
 */

require dirname(__DIR__) . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

header('Cache-Control: no-store, max-age=0');

$state = [
    'ok' => true,
    'found' => false,
    'session_token' => null,
    'current_step' => 0,
    'completed' => false,
    'forever' => null,
    'answers' => [
        'comfortable' => null,
        'likes' => '',
        'dislikes' => '',
        'future_serious' => '',
        'lovely_future' => '',
    ],
    'csrf_token' => csrf_token(),
];

$token = isset($_GET['token']) && is_string($_GET['token'])
    ? strtolower(trim($_GET['token']))
    : '';

if ($token === '' && isset($_COOKIE['gloria_token']) && is_string($_COOKIE['gloria_token'])) {
    $token = strtolower(trim($_COOKIE['gloria_token']));
}

if (!valid_session_token($token)) {
    json_response($state);
}

try {
    $stmt = db()->prepare(
        'SELECT session_token, comfortable, likes, dislikes, future_serious,
                lovely_future, forever, current_step, completed
           FROM responses
          WHERE session_token = :token
          LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();
} catch (Throwable $e) {
    error_log('[for-gloria] state failed: ' . $e->getMessage());
    json_response([
        'ok' => false,
        'error' => 'server_error',
        'message' => "We couldn't load your progress just now.",
    ], 503);
}

if (!is_array($row)) {
    json_response($state);
}

$state['found'] = true;
$state['session_token'] = $row['session_token'];
$state['current_step'] = (int) $row['current_step'];
$state['completed'] = (int) $row['completed'] === 1;
$state['forever'] = in_array($row['forever'], ['yes', 'no', 'not_ready'], true) ? $row['forever'] : null;
$state['answers'] = [
    'comfortable' => in_array($row['comfortable'], ['yes', 'no'], true) ? $row['comfortable'] : null,
    'likes' => (string) ($row['likes'] ?? ''),
    'dislikes' => (string) ($row['dislikes'] ?? ''),
    'future_serious' => (string) ($row['future_serious'] ?? ''),
    'lovely_future' => (string) ($row['lovely_future'] ?? ''),
];

json_response($state);
