<?php

declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

/**
 * ─────────────────────────────────────────────────────────────────────────────
 *  FOR GLORIA — application configuration
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  Fill in your database details below and add the email address that should
 *  be notified when Gloria finishes.
 *
 *  Every value can also come from the .env file (read automatically) or a real
 *  environment variable exposed through getenv(), $_ENV or $_SERVER, which is
 *  handy on hosts that set env vars. The admin password is the exception: it
 *  is hardcoded here and never read from the environment.
 */

return [
    'db' => [
        'host' => env_value('DB_HOST', '127.0.0.1'),
        'port' => (int) env_value('DB_PORT', '3306'),
        'name' => env_value('DB_NAME', 'gloria_proposal'),
        'user' => env_value('DB_USERNAME', 'root'),
        'pass' => env_value('DB_PASSWORD', ''),
    ],

    'admin' => [
        // Hardcoded admin password. Do not move this to .env or the environment.
        'password' => 'WiztechForever2026',
    ],

    // Where the "she answered!" notification is sent. Leave empty to disable.
    'notify_email' => env_value('NOTIFY_EMAIL', ''),

    'app' => [
        // Used for created_at / updated_at and the admin panel dates.
        'timezone' => env_value('APP_TIMEZONE', 'Africa/Lagos'),

        // Long enough that she will not lose her place if she closes the tab.
        'session_days' => max(1, (int) env_value('APP_SESSION_DAYS', '30')),

        // Set to true while setting up to see database errors. Turn off live.
        'debug' => filter_var(env_value('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],
];
