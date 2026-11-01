<?php
declare(strict_types=1);

const AV_VERSION = '0.2.1';
const AV_MAX_UPLOAD_BYTES = 104857600;

date_default_timezone_set('UTC');

function av_request_id(): string
{
    static $id;
    return $id ??= bin2hex(random_bytes(4));
}

/**
 * Registra um evento no log de erros do PHP com ID da requisição. Nunca passe segredos em $context.
 */
function av_log(string $event, array $context = []): void
{
    $line = 'AuraVault [' . av_request_id() . '] ' . $event;
    if ($context !== []) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    error_log($line);
}

function av_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function av_private_dir(): string
{
    $override = getenv('AURAVAULT_PRIVATE_DIR');
    if (is_string($override) && $override !== '') {
        return rtrim($override, '/\\');
    }

    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
    if ($documentRoot === false) {
        $documentRoot = dirname(__DIR__);
    }

    $directory = $documentRoot;
    while ($directory !== dirname($directory)) {
        if (strtolower(basename($directory)) === 'public_html') {
            return dirname($directory) . DIRECTORY_SEPARATOR . 'auravault_private';
        }
        $directory = dirname($directory);
    }

    return dirname($documentRoot) . DIRECTORY_SEPARATOR . 'auravault_private';
}

function av_config_path(): string
{
    return av_private_dir() . DIRECTORY_SEPARATOR . 'config.php';
}

function av_install_key_path(): string
{
    return av_private_dir() . DIRECTORY_SEPARATOR . 'install.key';
}

function av_install_throttle_path(): string
{
    return av_private_dir() . DIRECTORY_SEPARATOR . 'install.throttle';
}

/**
 * Lê a chave de instalação criada pelo responsável via cPanel, fora do document root.
 * Retorna null se o arquivo não existir ou se a chave tiver menos de 32 caracteres.
 */
function av_read_install_key(string $path): ?string
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $key = trim((string) file_get_contents($path, false, null, 0, 1024));
    return strlen($key) >= 32 ? $key : null;
}

function av_install_key_matches(string $expected, string $submitted): bool
{
    return hash_equals(hash('sha256', $expected), hash('sha256', trim($submitted)));
}

function av_install_throttled(string $path): bool
{
    $data = json_decode((string) @file_get_contents($path), true);
    return is_array($data) && (int) ($data['until'] ?? 0) > time();
}

function av_install_register_failure(string $path): void
{
    $data = json_decode((string) @file_get_contents($path), true);
    $fails = (is_array($data) ? (int) ($data['fails'] ?? 0) : 0) + 1;
    $until = 0;
    if ($fails >= 5) {
        $fails = 0;
        $until = time() + 900;
    }
    @file_put_contents($path, json_encode(['fails' => $fails, 'until' => $until]), LOCK_EX);
    @chmod($path, 0600);
}

function av_is_installed(): bool
{
    return is_file(av_config_path());
}

function av_load_config(): array
{
    $config = require av_config_path();
    if (!is_array($config) || !isset($config['db'], $config['media_dir'], $config['base_url'])) {
        throw new RuntimeException('Configuração do AuraVault inválida.');
    }

    return $config;
}

function av_connect_database(array $database): PDO
{
    if (($database['driver'] ?? 'mysql') === 'sqlite') {
        $dsn = 'sqlite:' . $database['path'];
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $database['host'],
        $database['name']
    );

    $pdo = new PDO($dsn, $database['user'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // Datas são gravadas e lidas como UTC; TIMESTAMP segue o fuso da sessão.
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}

function av_db(): PDO
{
    static $connection;
    if (!$connection instanceof PDO) {
        $config = av_load_config();
        $connection = av_connect_database($config['db']);
        av_migrate($connection);
    }

    return $connection;
}

function av_create_schema(PDO $db): void
{
    av_migrate($db);
}

/**
 * Migrações numeradas e idempotentes. A v1 é o esquema original (CREATE IF NOT EXISTS),
 * por isso é segura em bancos já instalados.
 */
function av_migrations(string $driver): array
{
    $sqlite = $driver === 'sqlite';

    return [
        1 => av_baseline_statements($driver),
        2 => $sqlite
            ? [
                'CREATE TABLE IF NOT EXISTS login_attempts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    ip_hash TEXT NOT NULL,
                    email_hash TEXT NOT NULL,
                    attempted_at TEXT NOT NULL
                )',
                'CREATE INDEX IF NOT EXISTS login_attempts_ip ON login_attempts(ip_hash, attempted_at)',
                'CREATE INDEX IF NOT EXISTS login_attempts_email ON login_attempts(email_hash, attempted_at)',
            ]
            : [
                'CREATE TABLE IF NOT EXISTS login_attempts (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    ip_hash CHAR(64) NOT NULL,
                    email_hash CHAR(64) NOT NULL,
                    attempted_at DATETIME NOT NULL,
                    INDEX login_attempts_ip (ip_hash, attempted_at),
                    INDEX login_attempts_email (email_hash, attempted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            ],
    ];
}

function av_migrate(PDO $db): void
{
    try {
        $current = (int) $db->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();
    } catch (PDOException) {
        $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version INTEGER NOT NULL PRIMARY KEY,
            applied_at VARCHAR(19) NOT NULL
        )');
        $current = 0;
    }

    foreach (av_migrations((string) $db->getAttribute(PDO::ATTR_DRIVER_NAME)) as $version => $statements) {
        if ($version <= $current) {
            continue;
        }
        foreach ($statements as $statement) {
            $db->exec($statement);
        }
        try {
            $db->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)')
                ->execute([$version, av_utc_now()]);
        } catch (PDOException) {
            // Outra requisição aplicou a mesma migração; as instruções são idempotentes.
        }
        av_log('migration applied', ['version' => $version]);
    }
}

function av_baseline_statements(string $driver): array
{
    if ($driver === 'sqlite') {
        $statements = [
            'CREATE TABLE IF NOT EXISTS owners (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
            'CREATE TABLE IF NOT EXISTS media (
                id TEXT PRIMARY KEY,
                owner_id INTEGER NOT NULL,
                stored_name TEXT NOT NULL UNIQUE,
                original_name TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                size_bytes INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES owners(id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS media_owner_created ON media(owner_id, created_at)',
            'CREATE TABLE IF NOT EXISTS shares (
                id TEXT PRIMARY KEY,
                media_id TEXT NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                revoked_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS shares_media_expiry ON shares(media_id, expires_at)',
        ];
    } else {
        $statements = [
            'CREATE TABLE IF NOT EXISTS owners (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(254) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS media (
                id CHAR(32) NOT NULL PRIMARY KEY,
                owner_id BIGINT UNSIGNED NOT NULL,
                stored_name VARCHAR(80) NOT NULL UNIQUE,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                size_bytes BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX media_owner_created (owner_id, created_at),
                CONSTRAINT media_owner_fk FOREIGN KEY (owner_id) REFERENCES owners(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS shares (
                id CHAR(32) NOT NULL PRIMARY KEY,
                media_id CHAR(32) NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                revoked_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX shares_media_expiry (media_id, expires_at),
                CONSTRAINT shares_media_fk FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];
    }

    return $statements;
}

function av_is_https(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function av_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('AURAVAULTSESSID');
    session_cache_limiter('');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => av_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function av_security_headers(): void
{
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Resource-Policy: same-origin');
    if (av_is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header("Content-Security-Policy: default-src 'self'; img-src 'self'; media-src 'self'; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}

function av_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function av_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function av_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . av_e(av_csrf_token()) . '">';
}

function av_verify_csrf(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    return is_string($submitted)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

function av_flash(string $key, mixed $value = null): mixed
{
    if (func_num_args() === 2) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function av_redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function av_utc_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function av_owner(PDO $db): ?array
{
    $ownerId = filter_var($_SESSION['owner_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$ownerId) {
        return null;
    }

    $statement = $db->prepare('SELECT id, email FROM owners WHERE id = ?');
    $statement->execute([$ownerId]);
    $owner = $statement->fetch();

    if (!$owner) {
        unset($_SESSION['owner_id']);
        return null;
    }

    return $owner;
}

function av_require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !av_verify_csrf()) {
        http_response_code(403);
        exit('Solicitação inválida ou expirada. Atualize a página e tente novamente.');
    }
}
