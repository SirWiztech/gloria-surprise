<?php

declare(strict_types=1);

/**
 * Generate a bcrypt hash for the admin password.
 *
 * Usage (from the project root):
 *     php tools/hash-password.php "your new password"
 *
 * Paste the output into admin.password_hash inside config.php.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This tool can only be run from the command line.');
}

$password = $argv[1] ?? null;

if (!is_string($password) || $password === '') {
    fwrite(STDERR, "Usage: php tools/hash-password.php \"your new password\"\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Please choose a password with at least 8 characters.\n");
    exit(1);
}

echo password_hash($password, PASSWORD_DEFAULT), PHP_EOL;
