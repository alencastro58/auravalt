<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/auth.php';
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

// Migrações e throttle de login no servidor
check((int) $db->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn() === 2, 'Migrações não foram aplicadas.');
av_migrate($db);
check((int) $db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 2, 'Migrações não são idempotentes.');

$now = 1_800_000_000;
$ip = '203.0.113.9';
for ($i = 0; $i < AV_LOGIN_MAX_PER_PAIR; $i++) {
    check(av_login_begin_attempt($db, $ip, 'titular@example.test', $now), 'Tentativa ' . ($i + 1) . ' bloqueada antes do limite.');
}
check(!av_login_begin_attempt($db, $ip, 'titular@example.test', $now), 'Limite por IP+e-mail não bloqueou a tentativa seguinte.');
check(av_login_throttled($db, $ip, 'TITULAR@example.test', $now), 'Bloqueio não ignora maiúsculas no e-mail.');
check(!av_login_throttled($db, '198.51.100.1', 'outro@example.test', $now), 'Bloqueio atingiu IP e e-mail não relacionados.');
check(!av_login_throttled($db, $ip, 'titular@example.test', $now + AV_LOGIN_WINDOW_SECONDS + 1), 'Bloqueio não expirou.');
av_login_clear($db, $ip, 'titular@example.test');
check(!av_login_throttled($db, $ip, 'titular@example.test', $now), 'Sucesso não limpou as tentativas.');
for ($i = 0; $i < AV_LOGIN_MAX_PER_IP; $i++) {
    av_login_register_failure($db, $ip, 'e' . $i . '@example.test', $now);
}
check(!av_login_begin_attempt($db, $ip, 'novo@example.test', $now), 'Limite por IP não bloqueou e-mails diferentes.');
$db->exec('DELETE FROM login_attempts');

check(av_login_verify($db, 'titular@example.test', 'uma-senha-de-teste') === $ownerId, 'Login válido foi recusado.');
check(av_login_verify($db, 'titular@example.test', 'errada') === null, 'Senha errada foi aceita.');
check(av_login_verify($db, 'ninguem@example.test', 'uma-senha-de-teste') === null, 'E-mail inexistente foi aceito.');

check(av_password_errors('curta') !== [], 'Senha curta foi aceita.');
check(av_password_errors('aaaaaaaaaaaaaaaa') !== [], 'Senha sem variedade foi aceita.');
check(av_password_errors('uma frase longa e boa') === [], 'Senha forte foi recusada.');
check(av_change_password($db, $ownerId, 'errada', 'nova senha bem longa') !== [], 'Troca com senha atual errada foi aceita.');
check(av_change_password($db, $ownerId, 'uma-senha-de-teste', 'nova senha bem longa') === [], 'Troca de senha válida falhou.');
check(av_login_verify($db, 'titular@example.test', 'nova senha bem longa') === $ownerId, 'Nova senha não funciona.');

// Limpeza de links antigos
$old = gmdate('Y-m-d H:i:s', time() - 40 * 86400);
$db->prepare('INSERT INTO shares (id, media_id, token_hash, expires_at) VALUES (?, ?, ?, ?)')
    ->execute([bin2hex(random_bytes(16)), $mediaId, hash('sha256', 'velho'), $old]);
check(av_purge_old_shares($db) === 1, 'Link vencido há mais de 30 dias não foi removido.');

// Limites de upload, nomes e miniaturas
check(av_ini_bytes('100M') === 104857600 && av_ini_bytes('2G') === 2147483648 && av_ini_bytes('512K') === 524288, 'Conversão de limites do PHP incorreta.');
check(av_effective_upload_limit() <= AV_MAX_UPLOAD_BYTES, 'Limite efetivo maior que o do aplicativo.');
check(av_ascii_filename("fóto de férias\r\n.jpg") === 'f_to_de_f_rias_.jpg', 'Nome ASCII do download incorreto.');
check(av_ascii_filename('???') === 'media', 'Nome ASCII vazio sem fallback.');
check(av_valid_stored_name(str_repeat('a', 48) . '.jpg') && !av_valid_stored_name('../' . str_repeat('a', 48) . '.jpg'), 'Validação do nome armazenado incorreta.');

if (av_thumbnails_available()) {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'avthumb' . bin2hex(random_bytes(4));
    mkdir($dir);
    $image = imagecreatetruecolor(1200, 800);
    imagepng($image, $dir . '/src.png');
    check(av_make_thumbnail($dir . '/src.png', 'image/png', $dir . '/out.jpg'), 'Miniatura não foi gerada.');
    $size = getimagesize($dir . '/out.jpg');
    check($size !== false && $size[0] === AV_THUMB_WIDTH && $size['mime'] === 'image/jpeg', 'Miniatura com tamanho ou formato incorreto.');
    file_put_contents($dir . '/fake.png', 'isto não é uma imagem');
    check(!av_make_thumbnail($dir . '/fake.png', 'image/png', $dir . '/fake.jpg'), 'Arquivo inválido gerou miniatura.');
    array_map('unlink', glob($dir . '/*'));
    rmdir($dir);
}

fwrite(STDOUT, "AuraVault smoke test: OK\n");
