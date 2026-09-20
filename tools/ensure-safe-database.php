<?php

declare(strict_types=1);

/**
 * Refuse setup migrations until the database target is explicit and safe.
 *
 * This intentionally does not bootstrap Laravel or connect to a database.
 */
$createSqliteFile = in_array('--create-sqlite', $argv, true);
$envArgument = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--env=')) {
        $envArgument = $argument;
        break;
    }
}
$envPath = $envArgument !== null && str_starts_with($envArgument, '--env=')
    ? substr($envArgument, 6)
    : ($envArgument ?? dirname(__DIR__).'/.env');

if (! is_file($envPath)) {
    fwrite(STDERR, "Refusing to migrate: {$envPath} does not exist. Copy .env.example to .env and choose a database target first.\n");
    exit(1);
}

$values = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    $line = preg_replace('/^export\s+/', '', $line) ?? $line;
    if (! str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);

    if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
        $value = substr($value, 1, -1);
    }

    $values[$key] = $value;
}

$value = static function (string $key) use ($values): string {
    $processValue = getenv($key);

    return $processValue === false ? ($values[$key] ?? '') : trim((string) $processValue);
};

$connection = strtolower($value('DB_CONNECTION'));
$database = $value('DB_DATABASE');
$url = $value('DB_URL');
$target = $value('PLAYNEXUS_DB_TARGET');
$reasons = [];
$sqlitePath = null;

if ($url !== '') {
    $reasons[] = 'DB_URL is set; use explicit DB_CONNECTION, DB_DATABASE, and PLAYNEXUS_DB_TARGET values instead';
}

if ($connection === 'sqlite') {
    if ($database === '' || $database === ':memory:') {
        $reasons[] = 'SQLite requires a persistent project-local file, not an empty value or :memory:';
    } else {
        $projectRoot = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));
        $databasePath = $database;
        if (! preg_match('#^(?:[A-Za-z]:[\\\\/]|[\\\\/])#', $databasePath)) {
            $databasePath = $projectRoot.DIRECTORY_SEPARATOR.$databasePath;
        }
        $databasePath = str_replace('\\', '/', $databasePath);
        $databasePath = preg_replace('#/+#', '/', $databasePath) ?? $databasePath;
        $projectPrefix = rtrim($projectRoot, '/').'/';

        $databaseDirectory = realpath(dirname($databasePath));
        if ($databaseDirectory !== false) {
            $databasePath = str_replace('\\', '/', $databaseDirectory).'/'.basename($databasePath);
        }

        $sqlitePath = $databasePath;

        if (! str_starts_with(strtolower($databasePath), strtolower($projectPrefix))) {
            $reasons[] = 'SQLite database must be inside this project directory';
        }
    }
} elseif ($connection === 'mysql' || $connection === 'mariadb') {
    if ($database === '' || in_array(strtolower($database), ['laravel', 'playnexus', 'testing', ':memory:'], true)) {
        $reasons[] = 'DB_DATABASE must be a non-default disposable database name';
    }

    if ($target === '' || $target !== $database) {
        $reasons[] = 'PLAYNEXUS_DB_TARGET must exactly match the intentionally selected DB_DATABASE';
    }
} else {
    $reasons[] = 'DB_CONNECTION must be sqlite, mysql, or mariadb';
}

if ($reasons !== []) {
    fwrite(STDERR, "Refusing to migrate: database setup is unsafe or unconfigured.\n");
    foreach ($reasons as $reason) {
        fwrite(STDERR, "- {$reason}\n");
    }
    fwrite(STDERR, "Choose a project-local SQLite file, or configure a disposable MySQL target and set PLAYNEXUS_DB_TARGET to the exact same database name.\n");
    exit(1);
}

if ($createSqliteFile && $connection === 'sqlite' && $sqlitePath !== null && ! is_file($sqlitePath) && ! touch($sqlitePath)) {
    fwrite(STDERR, "Refusing to migrate: unable to create the project-local SQLite database file.\n");
    exit(1);
}

fwrite(STDOUT, "Safe database target confirmed ({$connection}). Migrations may proceed.\n");
