<?php
declare(strict_types=1);

const AV_LOGIN_WINDOW_SECONDS = 900;
const AV_LOGIN_MAX_PER_PAIR = 5;
const AV_LOGIN_MAX_PER_IP = 20;
const AV_LOGIN_MAX_PER_EMAIL = 50;
const AV_PASSWORD_MIN_LENGTH = 12;

function av_login_hash(string $kind, string $value): string
{
    return hash('sha256', $kind . '|' . strtolower($value));
}

function av_login_window_start(int $now): string
{
    return gmdate('Y-m-d H:i:s', $now - AV_LOGIN_WINDOW_SECONDS);
}

function av_login_count(PDO $db, string $column, string $hash, string $since, ?string $emailHash = null): int
{
    $sql = 'SELECT COUNT(*) FROM login_attempts WHERE ' . $column . ' = ? AND attempted_at > ?';
    $params = [$hash, $since];
    if ($emailHash !== null) {
        $sql .= ' AND email_hash = ?';
        $params[] = $emailHash;
    }
    $statement = $db->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

/**
 * Bloqueio no servidor: não depende de cookie, então não é contornado descartando a sessão.
 * Limites por par IP+e-mail, por IP e por e-mail (ataque distribuído).
 * $includesCurrent indica que a tentativa em curso já foi registrada e entra na contagem.
 */
function av_login_throttled(PDO $db, string $ip, string $email, ?int $now = null, bool $includesCurrent = false): bool
{
    $since = av_login_window_start($now ?? time());
    $ipHash = av_login_hash('ip', $ip);
    $emailHash = av_login_hash('email', $email);
    $extra = $includesCurrent ? 1 : 0;

    return av_login_count($db, 'ip_hash', $ipHash, $since, $emailHash) >= AV_LOGIN_MAX_PER_PAIR + $extra
        || av_login_count($db, 'ip_hash', $ipHash, $since) >= AV_LOGIN_MAX_PER_IP + $extra
        || av_login_count($db, 'email_hash', $emailHash, $since) >= AV_LOGIN_MAX_PER_EMAIL + $extra;
}

function av_login_register_failure(PDO $db, string $ip, string $email, ?int $now = null): void
{
    $now ??= time();
    $db->prepare('INSERT INTO login_attempts (ip_hash, email_hash, attempted_at) VALUES (?, ?, ?)')
        ->execute([av_login_hash('ip', $ip), av_login_hash('email', $email), gmdate('Y-m-d H:i:s', $now)]);
    $db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')
        ->execute([gmdate('Y-m-d H:i:s', $now - 86400)]);
}

/**
 * Registra a tentativa ANTES de verificar a senha (o bcrypt é lento e requisições paralelas
 * veriam todas o contador vazio). Retorna false se o limite já foi excedido.
 * Em caso de sucesso, chame av_login_clear().
 */
function av_login_begin_attempt(PDO $db, string $ip, string $email, ?int $now = null): bool
{
    av_login_register_failure($db, $ip, $email, $now);

    return !av_login_throttled($db, $ip, $email, $now, true);
}

function av_login_clear(PDO $db, string $ip, string $email): void
{
    $db->prepare('DELETE FROM login_attempts WHERE ip_hash = ? AND email_hash = ?')
        ->execute([av_login_hash('ip', $ip), av_login_hash('email', $email)]);
}

/**
 * Confere e-mail e senha em tempo semelhante exista ou não o e-mail, para não revelar contas.
 * Retorna o id do titular ou null.
 */
function av_login_verify(PDO $db, string $email, string $password): ?int
{
    static $dummyHash;
    $dummyHash ??= password_hash('av-dummy-password', PASSWORD_DEFAULT);

    $statement = $db->prepare('SELECT id, password_hash FROM owners WHERE email = ?');
    $statement->execute([$email]);
    $record = $statement->fetch();

    $valid = password_verify($password, $record ? (string) $record['password_hash'] : $dummyHash);
    if (!$record || !$valid) {
        return null;
    }
    if (password_needs_rehash((string) $record['password_hash'], PASSWORD_DEFAULT)) {
        $db->prepare('UPDATE owners SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $record['id']]);
    }

    return (int) $record['id'];
}

function av_chars(string $value): array
{
    preg_match_all('/./us', $value, $matches);
    return $matches[0];
}

/**
 * Valida uma nova senha. Retorna a lista de erros (vazia se aceita).
 */
function av_password_errors(string $password, string $email = ''): array
{
    $errors = [];
    $chars = av_chars($password);
    if (count($chars) < AV_PASSWORD_MIN_LENGTH) {
        $errors[] = 'A nova senha deve ter pelo menos ' . AV_PASSWORD_MIN_LENGTH . ' caracteres.';
    }
    if (strlen($password) > 512) {
        $errors[] = 'A nova senha é longa demais.';
    }
    if ($email !== '' && strtolower($password) === strtolower($email)) {
        $errors[] = 'A senha não pode ser igual ao e-mail.';
    }
    if (count(array_unique($chars)) < 5) {
        $errors[] = 'A senha precisa de mais variedade de caracteres.';
    }

    return $errors;
}

function av_change_password(PDO $db, int $ownerId, string $current, string $new): array
{
    $statement = $db->prepare('SELECT email, password_hash FROM owners WHERE id = ?');
    $statement->execute([$ownerId]);
    $owner = $statement->fetch();
    if (!$owner || !password_verify($current, (string) $owner['password_hash'])) {
        return ['A senha atual está incorreta.'];
    }
    $errors = av_password_errors($new, (string) $owner['email']);
    if ($errors === [] && hash_equals($current, $new)) {
        $errors[] = 'A nova senha deve ser diferente da atual.';
    }
    if ($errors !== []) {
        return $errors;
    }
    $db->prepare('UPDATE owners SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_DEFAULT), $ownerId]);

    return [];
}

/**
 * Remove links vencidos ou revogados há mais de 30 dias para a tabela não crescer sem limite.
 */
function av_purge_old_shares(PDO $db, ?int $now = null): int
{
    $limit = gmdate('Y-m-d H:i:s', ($now ?? time()) - 30 * 86400);
    $statement = $db->prepare('DELETE FROM shares WHERE expires_at < ? OR (revoked_at IS NOT NULL AND revoked_at < ?)');
    $statement->execute([$limit, $limit]);

    return $statement->rowCount();
}
