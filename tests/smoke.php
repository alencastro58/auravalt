<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/media.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
av_create_schema($db);

$ownerStatement = $db->prepare('INSERT INTO owners (email, password_hash) VALUES (?, ?)');
$ownerStatement->execute(['titular@example.test', password_hash('uma-senha-de-teste', PASSWORD_DEFAULT)]);
$ownerId = (int) $db->lastInsertId();
$mediaId = bin2hex(random_bytes(16));
$mediaStatement = $db->prepare(
    'INSERT INTO media (id, owner_id, stored_name, original_name, mime_type, size_bytes)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$mediaStatement->execute([$mediaId, $ownerId, bin2hex(random_bytes(24)) . '.jpg', 'foto.jpg', 'image/jpeg', 1024]);

$token = bin2hex(random_bytes(32));
$shareStatement = $db->prepare(
    'INSERT INTO shares (id, media_id, token_hash, expires_at) VALUES (?, ?, ?, ?)'
);
$shareStatement->execute([bin2hex(random_bytes(16)), $mediaId, hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + 3600)]);

check(av_find_media($db, $mediaId, $ownerId) !== null, 'O titular não encontrou a própria mídia.');
check(av_find_media($db, $mediaId, $ownerId + 1) === null, 'Outro titular conseguiu consultar a mídia.');
check(av_find_share($db, $token) !== null, 'O link ativo não encontrou a mídia.');
check(av_find_share($db, str_repeat('a', 64)) === null, 'Um token não emitido encontrou uma mídia.');
check(av_supported_media()['image/jpeg'] === 'jpg', 'O formato JPEG não está permitido.');
check(av_supported_media()['video/mp4'] === 'mp4', 'O formato MP4 não está permitido.');
check(av_format_bytes(1048576) === '1,0 MB', 'A formatação de tamanho está incorreta.');

$shareStatement = $db->prepare('UPDATE shares SET expires_at = ? WHERE token_hash = ?');
$shareStatement->execute([gmdate('Y-m-d H:i:s', time() - 60), hash('sha256', $token)]);
check(av_find_share($db, $token) === null, 'Um link expirado ainda está acessível.');

fwrite(STDOUT, "AuraValt smoke test: OK\n");
