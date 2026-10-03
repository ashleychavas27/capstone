<?php

/**
 * Applies database/supabase/*.sql to the Postgres project configured in .env,
 * in filename order. Needs no psql client: it talks to the Supabase session
 * pooler with PDO/pgsql, which is the same connection Laravel uses.
 *
 * Usage:
 *   php database/supabase/apply.php            # apply 01 -> 02 -> 03
 *   php database/supabase/apply.php --dry-run  # connect and list files only
 *
 * Requires DB_PASSWORD to be set in .env. The API keys (sb_publishable_...,
 * sb_secret_...) are NOT database passwords and cannot authenticate here.
 */

$root = dirname(__DIR__, 2);
$dryRun = in_array('--dry-run', $argv, true);

/** Read a key from .env without pulling in the framework. */
function envValue(string $root, string $key): ?string
{
    $lines = @file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines ?: [] as $line) {
        if (str_starts_with(ltrim($line), $key . '=')) {
            $value = substr(ltrim($line), strlen($key) + 1);
            return trim($value, " \t\r\n\"'");
        }
    }
    return null;
}

$host = envValue($root, 'DB_HOST') ?: '127.0.0.1';
$port = envValue($root, 'DB_PORT') ?: '5432';
$db = envValue($root, 'DB_DATABASE') ?: 'postgres';
$user = envValue($root, 'DB_USERNAME') ?: 'postgres';
$pass = envValue($root, 'DB_PASSWORD') ?? '';
$sslmode = envValue($root, 'DB_SSLMODE') ?: 'require';

$files = glob(__DIR__ . '/[0-9][0-9]_*.sql') ?: [];
sort($files);

echo "Target : pgsql://{$user}@{$host}:{$port}/{$db}?sslmode={$sslmode}\n";
echo "Files  : " . implode(', ', array_map('basename', $files)) . "\n";

if ($pass === '') {
    fwrite(STDERR, "DB_PASSWORD is empty in .env. The Supabase database password is NOT the "
        . "publishable or secret API key; set DB_PASSWORD and rerun.\n");
    exit(2);
}

if ($dryRun) {
    exit(0);
}

try {
    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode}",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 15]
    );
} catch (Throwable $e) {
    fwrite(STDERR, "Could not connect: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Connected: " . substr((string) $pdo->query('select version()')->fetchColumn(), 0, 60) . "\n";

$failed = false;
foreach ($files as $file) {
    $started = microtime(true);
    try {
        $pdo->exec((string) file_get_contents($file));
        printf("  OK   %-18s %5.2fs\n", basename($file), microtime(true) - $started);
    } catch (Throwable $e) {
        $failed = true;
        printf("  FAIL %-18s %s\n", basename($file), $e->getMessage());
        break;
    }
}

if (!$failed) {
    $tables = $pdo->query(
        "select table_name from information_schema.tables where table_schema = 'dental' order by 1"
    )->fetchAll(PDO::FETCH_COLUMN);
    echo "dental tables (" . count($tables) . "): " . implode(', ', $tables) . "\n";
}

exit($failed ? 1 : 0);
