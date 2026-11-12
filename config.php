<?php

declare(strict_types=1);

/**
 * ─────────────────────────────────────────────────────────────────────────────
 *  FOR GLORIA — application configuration
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  Fill in your database details below, change the admin password hash, and
 *  add the email address that should be notified when Gloria finishes.
 *
 *  Every value can also come from the .env file (read automatically) or a real
 *  environment variable, which is handy on hosts that set env vars.
 *
 *  Generate a new admin password hash with:
 *      php tools/hash-password.php "your new password"
 *
 *  The default password below is:  WiztechForever2026
 *  Change it before putting this online.
 */

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'gloria_proposal',
        'user' => getenv('DB_USERNAME') ?: 'root',
        'pass' => getenv('DB_PASSWORD') ?: '',
    ],

    'admin' => [
        // bcrypt hash of "WiztechForever2026"
        'password_hash' => getenv('ADMIN_PASSWORD_HASH') ?: '$2y$12$/R6gCg/GH4hKOgFMM6b8Je9zH5QM99wMUSyxvC060BBfK.TuSeRu6',
    ],

    // Where the "she answered!" notification is sent. Leave empty to disable.
    'notify_email' => getenv('NOTIFY_EMAIL') ?: '',

    'app' => [
        // Used for created_at / updated_at and the admin panel dates.
        'timezone' => getenv('APP_TIMEZONE') ?: 'Africa/Lagos',

        // Long enough that she will not lose her place if she closes the tab.
        'session_days' => max(1, (int) (getenv('APP_SESSION_DAYS') ?: 30)),

        // Set to true while setting up to see database errors. Turn off live.
        'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    ],
];
