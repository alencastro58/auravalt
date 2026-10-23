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

$keyFile = tempnam(sys_get_temp_dir(), 'avkey');
check(av_read_install_key($keyFile . '.inexistente') === null, 'Instalador liberado sem install.key.');
file_put_contents($keyFile, "curta\n");
check(av_read_install_key($keyFile) === null, 'Chave de instalação curta foi aceita.');
$installKey = bin2hex(random_bytes(32));
file_put_contents($keyFile, $installKey . "\r\n");
check(av_read_install_key($keyFile) === $installKey, 'Chave de instalação válida não foi lida.');
check(av_install_key_matches($installKey, $installKey), 'Chave correta foi recusada.');
check(!av_install_key_matches($installKey, ''), 'Chave vazia foi aceita.');
check(!av_install_key_matches($installKey, strtoupper($installKey)), 'Chave diferente foi aceita.');
unlink($keyFile);

$throttleFile = tempnam(sys_get_temp_dir(), 'avthr');
unlink($throttleFile);
for ($i = 0; $i < 4; $i++) {
    av_install_register_failure($throttleFile);
}
check(!av_install_throttled($throttleFile), 'Bloqueio antes da quinta tentativa.');
av_install_register_failure($throttleFile);
check(av_install_throttled($throttleFile), 'Cinco chaves inválidas não bloquearam o instalador.');
unlink($throttleFile);

$filters = av_library_filters(['type' => 'video', 'sort' => 'x; DROP TABLE media', 'q' => "  foto\t "]);
check($filters === ['type' => 'video', 'sort' => 'recent', 'q' => 'foto'], 'Filtros da biblioteca não foram normalizados.');
check(av_library_filters(['type' => 'pdf'])['type'] === 'all', 'Tipo de mídia inválido foi aceito.');
$statement = $db->prepare('INSERT INTO media (id, owner_id, stored_name, original_name, mime_type, size_bytes) VALUES (?, ?, ?, ?, ?, ?)');
$statement->execute([bin2hex(random_bytes(16)), $ownerId, bin2hex(random_bytes(24)) . '.mp4', '100%_video.mp4', 'video/mp4', 4096]);
[$where, $params] = av_library_where(['type' => 'video', 'sort' => 'recent', 'q' => '100%_'], $ownerId);
$count = $db->prepare('SELECT COUNT(*) FROM media WHERE ' . $where);
$count->execute($params);
check((int) $count->fetchColumn() === 1, 'Busca com % e _ não foi tratada como texto literal.');
[$where, $params] = av_library_where(['type' => 'image', 'sort' => 'recent', 'q' => '%'], $ownerId);
$count = $db->prepare('SELECT COUNT(*) FROM media WHERE ' . $where);
$count->execute($params);
check((int) $count->fetchColumn() === 0, 'Curinga % na busca retornou resultados indevidos.');
$stats = av_library_stats($db, $ownerId);
check($stats['videos'] === 1 && $stats['total'] === $stats['images'] + $stats['videos'], 'Estatísticas da biblioteca incorretas.');
check(av_library_url(['type' => 'all', 'sort' => 'recent', 'q' => '']) === './', 'URL da biblioteca sem filtros incorreta.');

fwrite(STDOUT, "AuraVault smoke test: OK\n");
