<?php
declare(strict_types=1);

/**
 * Teste de integração HTTP: sobe o servidor embutido do PHP com banco SQLite temporário
 * e percorre os fluxos principais (login, bloqueio, upload, miniatura, links, exclusão).
 */

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function http(string $method, string $path, array $options = []): array
{
    global $base;
    $headers = $options['headers'] ?? [];
    $content = $options['body'] ?? '';
    if (isset($options['form'])) {
        $content = http_build_query($options['form']);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    if (isset($options['cookie'])) {
        $headers[] = 'Cookie: ' . $options['cookie'];
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $content,
        'follow_location' => 0,
        'ignore_errors' => true,
        'timeout' => 20,
    ]]);
    $body = (string) @file_get_contents($base . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    $parsed = [];
    foreach ($responseHeaders as $line) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m)) {
            $status = (int) $m[1];
            $parsed = [];
        } elseif (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $parsed[strtolower(trim($name))][] = trim($value);
        }
    }
    $cookie = null;
    foreach ($parsed['set-cookie'] ?? [] as $setCookie) {
        $cookie = explode(';', $setCookie, 2)[0];
    }

    return ['status' => $status, 'headers' => $parsed, 'body' => $body, 'cookie' => $cookie];
}

function header_of(array $response, string $name): string
{
    return implode(', ', $response['headers'][strtolower($name)] ?? []);
}

function csrf_of(array $response): string
{
    check(preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $response['body'], $m) === 1, 'Token CSRF não encontrado na página.');
    return $m[1];
}

function login(string $email, string $password, ?string $cookie = null): array
{
    $page = http('GET', '/?action=login', ['cookie' => $cookie]);
    $cookie = $page['cookie'] ?? $cookie;
    $response = http('POST', '/?action=login', [
        'cookie' => $cookie,
        'form' => ['csrf_token' => csrf_of($page), 'email' => $email, 'password' => $password],
    ]);
    $response['session'] = $response['cookie'] ?? $cookie;

    return $response;
}

function multipart_upload(string $cookie, string $csrf, array $files): array
{
    $boundary = '----av' . bin2hex(random_bytes(8));
    $body = "--$boundary\r\nContent-Disposition: form-data; name=\"csrf_token\"\r\n\r\n$csrf\r\n";
    foreach ($files as [$name, $type, $data]) {
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"media[]\"; filename=\"$name\"\r\nContent-Type: $type\r\n\r\n$data\r\n";
    }
    $body .= "--$boundary--\r\n";

    return http('POST', '/?action=upload', [
        'cookie' => $cookie,
        'body' => $body,
        'headers' => ['Content-Type: multipart/form-data; boundary=' . $boundary],
    ]);
}

$root = dirname(__DIR__);
$private = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'avhttp' . bin2hex(random_bytes(4));
mkdir($private . DIRECTORY_SEPARATOR . 'media', 0700, true);

require $root . '/app/bootstrap.php';
require $root . '/app/auth.php';
require $root . '/app/media.php';

$port = random_int(20000, 40000);
$base = 'http://127.0.0.1:' . $port;
$config = [
    'db' => ['driver' => 'sqlite', 'path' => $private . DIRECTORY_SEPARATOR . 'test.sqlite'],
    'base_url' => $base,
    'media_dir' => $private . DIRECTORY_SEPARATOR . 'media',
];
file_put_contents($private . DIRECTORY_SEPARATOR . 'config.php', "<?php\nreturn " . var_export($config, true) . ";\n");

$db = av_connect_database($config['db']);
av_create_schema($db);
$db->prepare('INSERT INTO owners (email, password_hash) VALUES (?, ?)')
    ->execute(['titular@example.test', password_hash('senha-de-teste-longa', PASSWORD_DEFAULT)]);

$environment = array_merge(getenv(), ['AURAVAULT_PRIVATE_DIR' => $private]);
$server = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root],
    [0 => ['pipe', 'r'], 1 => ['file', $private . '/server.log', 'w'], 2 => ['file', $private . '/server.err', 'w']],
    $pipes,
    $root,
    $environment
);

$cleanup = static function () use ($server, $private, &$db): void {
    $db = null;
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($private);
};

try {
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        if (@fsockopen('127.0.0.1', $port)) {
            $ready = true;
            break;
        }
        usleep(100000);
    }
    check($ready, 'Servidor embutido não iniciou.');

    // Acesso anônimo
    $home = http('GET', '/');
    check($home['status'] === 303 && str_contains(header_of($home, 'location'), 'action=login'), 'Visitante anônimo não foi enviado ao login.');
    check(str_contains(header_of($home, 'content-security-policy'), "default-src 'self'"), 'CSP ausente.');
    check(header_of($home, 'x-frame-options') === 'DENY', 'X-Frame-Options ausente.');
    $unknownShare = http('GET', '/?action=shared&token=' . str_repeat('a', 64));
    check($unknownShare['status'] === 404, 'Token inexistente não retornou 404.');
    check(!isset($unknownShare['headers']['set-cookie']), 'Link público criou sessão/cookie.');
    check(http('GET', '/?action=thumb&id=' . str_repeat('a', 32))['status'] === 403, 'Miniatura acessível sem login.');
    check(http('GET', '/?action=file&id=' . str_repeat('a', 32))['status'] === 303, 'Arquivo acessível sem login.');

    // CSRF no login
    $page = http('GET', '/?action=login');
    $noCsrf = http('POST', '/?action=login', ['cookie' => $page['cookie'], 'form' => ['email' => 'titular@example.test', 'password' => 'senha-de-teste-longa']]);
    check($noCsrf['status'] === 200 && str_contains($noCsrf['body'], 'sessão expirou'), 'Login sem CSRF não foi recusado.');

    // Força bruta: descartar o cookie a cada tentativa não deve ajudar
    for ($i = 0; $i < AV_LOGIN_MAX_PER_PAIR; $i++) {
        $attempt = login('titular@example.test', 'senha-errada-' . $i);
        check(str_contains($attempt['body'], 'incorretos'), 'Senha errada não foi recusada.');
    }
    $blocked = login('titular@example.test', 'senha-de-teste-longa');
    check($blocked['status'] === 200 && str_contains($blocked['body'], 'Muitas tentativas'), 'Bloqueio por tentativas não funcionou com sessão nova.');
    $db->exec('DELETE FROM login_attempts');

    // Login válido
    $session = login('titular@example.test', 'senha-de-teste-longa');
    check($session['status'] === 303, 'Login válido falhou.');
    $cookie = $session['session'];

    $library = http('GET', '/', ['cookie' => $cookie]);
    check($library['status'] === 200 && str_contains($library['body'], 'Sua biblioteca está vazia'), 'Biblioteca inicial incorreta.');
    $csrf = csrf_of($library);

    // Upload
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $upload = multipart_upload($cookie, $csrf, [
        ['<img src=x onerror=alert(1)>.png', 'image/png', $png],
        ['falso.jpg', 'image/jpeg', 'isto é texto, não uma imagem'],
    ]);
    check($upload['status'] === 303, 'Upload não redirecionou.');
    $library = http('GET', '/', ['cookie' => $cookie]);
    check(str_contains($library['body'], '1 arquivo(s) adicionado(s)') && str_contains($library['body'], 'formato não permitido'), 'Resultado do upload incorreto.');
    check(!str_contains($library['body'], '<img src=x'), 'Nome de arquivo não foi escapado (XSS).');
    check(str_contains($library['body'], '&lt;img src=x'), 'Nome de arquivo escapado não aparece.');
    check(count(glob($config['media_dir'] . '/*')) === 1, 'Arquivo inválido foi gravado no disco.');
    check(preg_match('/action=thumb&amp;id=([a-f0-9]{32})/', $library['body'], $m) === 1, 'Miniatura não referenciada na biblioteca.');
    $mediaId = $m[1];

    // Miniatura, cache e Range
    $thumb = http('GET', '/?action=thumb&id=' . $mediaId, ['cookie' => $cookie]);
    check($thumb['status'] === 200, 'Miniatura não foi entregue.');
    if (av_thumbnails_available()) {
        check(header_of($thumb, 'content-type') === 'image/jpeg', 'Miniatura não é JPEG.');
        check(str_contains(header_of($thumb, 'cache-control'), 'max-age=86400'), 'Miniatura sem cache privado.');
        $etag = header_of($thumb, 'etag');
        $cached = http('GET', '/?action=thumb&id=' . $mediaId, ['cookie' => $cookie, 'headers' => ['If-None-Match: ' . $etag]]);
        check($cached['status'] === 304, 'ETag da miniatura não gerou 304.');
        check(is_file($private . '/thumbs/' . $mediaId . '.jpg'), 'Miniatura não foi guardada no diretório privado.');
    }
    $file = http('GET', '/?action=file&id=' . $mediaId, ['cookie' => $cookie]);
    check($file['status'] === 200 && $file['body'] === $png, 'Arquivo original não foi entregue íntegro.');
    check(str_contains(header_of($file, 'content-disposition'), 'filename="'), 'Content-Disposition ausente.');
    $range = http('GET', '/?action=file&id=' . $mediaId, ['cookie' => $cookie, 'headers' => ['Range: bytes=0-9']]);
    check($range['status'] === 206 && strlen($range['body']) === 10, 'Range não retornou 206 com 10 bytes.');

    // Link compartilhado
    $share = http('POST', '/?action=share', ['cookie' => $cookie, 'form' => ['csrf_token' => $csrf, 'media_id' => $mediaId, 'days' => '7']]);
    check($share['status'] === 303, 'Criação de link falhou.');
    $library = http('GET', '/', ['cookie' => $cookie]);
    check(preg_match('/token=([a-f0-9]{64})/', html_entity_decode($library['body']), $m) === 1, 'URL do link não apareceu.');
    $token = $m[1];
    check(preg_match('/name="share_id" value="([a-f0-9]{32})"/', $library['body'], $m) === 1, 'Link ativo não listado.');
    $shareId = $m[1];
    $shared = http('GET', '/?action=shared&token=' . $token);
    check($shared['status'] === 200 && !isset($shared['headers']['set-cookie']), 'Página do link falhou ou criou cookie.');
    check(str_contains($shared['body'], 'MÍDIA COMPARTILHADA'), 'Rótulo do link compartilhado incorreto.');
    check(http('GET', '/?action=file&token=' . $token)['body'] === $png, 'Arquivo via link não foi entregue.');
    check(http('GET', '/?action=thumb&token=' . $token)['status'] === 403, 'Miniatura acessível por token.');

    $revoke = http('POST', '/?action=revoke', ['cookie' => $cookie, 'form' => ['csrf_token' => $csrf, 'share_id' => $shareId]]);
    check($revoke['status'] === 303, 'Revogação falhou.');
    check(http('GET', '/?action=shared&token=' . $token)['status'] === 404, 'Link revogado continua acessível.');
    check(http('POST', '/?action=revoke', ['cookie' => $cookie, 'form' => ['share_id' => $shareId]])['status'] === 403, 'POST sem CSRF foi aceito.');

    // Troca de senha
    $account = http('GET', '/?action=account', ['cookie' => $cookie]);
    check($account['status'] === 200 && str_contains($account['body'], 'ALTERAR SENHA'), 'Página de conta indisponível.');
    $wrong = http('POST', '/?action=password', ['cookie' => $cookie, 'form' => ['csrf_token' => $csrf, 'current_password' => 'errada', 'new_password' => 'outra senha bem longa', 'confirm_password' => 'outra senha bem longa']]);
    $after = http('GET', '/?action=account', ['cookie' => $cookie]);
    check(str_contains($after['body'], 'senha atual está incorreta'), 'Troca com senha atual errada não foi recusada.');
    $change = http('POST', '/?action=password', ['cookie' => $cookie, 'form' => ['csrf_token' => $csrf, 'current_password' => 'senha-de-teste-longa', 'new_password' => 'outra senha bem longa', 'confirm_password' => 'outra senha bem longa']]);
    $cookie = $change['cookie'] ?? $cookie;
    check(str_contains(http('GET', '/?action=account', ['cookie' => $cookie])['body'], 'Senha alterada com sucesso'), 'Troca de senha não confirmada.');
    check(login('titular@example.test', 'outra senha bem longa')['status'] === 303, 'Login com a nova senha falhou.');

    // Segurança (diagnóstico) e exclusão
    check(str_contains(http('GET', '/?action=security', ['cookie' => $cookie])['body'], 'DIAGNÓSTICO DO SERVIDOR'), 'Página de segurança sem diagnóstico.');
    $csrf = csrf_of(http('GET', '/', ['cookie' => $cookie]));
    $delete = http('POST', '/?action=delete', ['cookie' => $cookie, 'form' => ['csrf_token' => $csrf, 'media_id' => $mediaId]]);
    check($delete['status'] === 303, 'Exclusão falhou.');
    check(glob($config['media_dir'] . '/*') === [], 'Arquivo não foi removido do disco.');
    clearstatcache();
    check(!is_file($private . '/thumbs/' . $mediaId . '.jpg'), 'Miniatura não foi removida.');
    check(http('GET', '/?action=file&id=' . $mediaId, ['cookie' => $cookie])['status'] === 404, 'Arquivo excluído continua acessível.');

    fwrite(STDOUT, "AuraVault HTTP test: OK\n");
    $cleanup();
} catch (Throwable $exception) {
    fwrite(STDERR, 'FALHA: ' . $exception->getMessage() . "\n");
    if (is_file($private . '/server.err')) {
        fwrite(STDERR, substr((string) file_get_contents($private . '/server.err'), -2000) . "\n");
    }
    $cleanup();
    exit(1);
}
