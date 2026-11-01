<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/media.php';
require __DIR__ . '/app/views.php';

$action = (string) ($_GET['action'] ?? 'library');
// Links públicos não precisam de sessão nem de cookie.
$isPublicShare = $action === 'shared' || ($action === 'file' && (string) ($_GET['token'] ?? '') !== '');
if (!$isPublicShare) {
    av_start_session();
}
av_security_headers();

function av_install_post(): array
{
    $errors = [];
    $old = [
        'db_host' => trim((string) ($_POST['db_host'] ?? 'localhost')),
        'db_name' => trim((string) ($_POST['db_name'] ?? '')),
        'db_user' => trim((string) ($_POST['db_user'] ?? '')),
        'owner_email' => strtolower(trim((string) ($_POST['owner_email'] ?? ''))),
        'base_url' => trim((string) ($_POST['base_url'] ?? 'https://www.auravault.com.br')),
    ];
    $databasePassword = (string) ($_POST['db_password'] ?? '');
    $ownerPassword = (string) ($_POST['owner_password'] ?? '');

    if (!av_verify_csrf()) {
        return [['A sessão expirou. Recarregue a página e tente novamente.'], $old];
    }

    $keyPath = av_install_key_path();
    $throttlePath = av_install_throttle_path();
    $expectedKey = av_read_install_key($keyPath);
    if ($expectedKey === null) {
        return [['Instalador bloqueado: a chave de instalação não foi encontrada no servidor.'], $old];
    }
    if (av_install_throttled($throttlePath)) {
        return [['Muitas tentativas com chave inválida. Aguarde 15 minutos.'], $old];
    }
    if (!av_install_key_matches($expectedKey, (string) ($_POST['install_key'] ?? ''))) {
        av_install_register_failure($throttlePath);
        av_log('setup: invalid install key', ['ip' => av_client_ip()]);
        return [['Chave de instalação inválida.'], $old];
    }

    if (!filter_var($old['owner_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido para o titular.';
    }
    $errors = [...$errors, ...av_password_errors($ownerPassword, $old['owner_email'])];
    if (!preg_match('/^[A-Za-z0-9_.:\-]{1,190}$/', $old['db_host'])) {
        $errors[] = 'O servidor do banco contém caracteres inválidos.';
    }
    foreach (['db_name', 'db_user'] as $field) {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $old[$field])) {
            $errors[] = 'Confira o nome do banco e do usuário conforme aparecem no cPanel.';
            break;
        }
    }
    if ($databasePassword === '' || strlen($databasePassword) > 512) {
        $errors[] = 'Informe a senha do usuário MySQL criado no cPanel.';
    }

    $url = parse_url($old['base_url']);
    $localHost = in_array(strtolower((string) ($url['host'] ?? '')), ['localhost', '127.0.0.1'], true);
    $validScheme = ($url['scheme'] ?? '') === 'https' || ($localHost && ($url['scheme'] ?? '') === 'http');
    if (!$validScheme || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || !in_array($url['path'] ?? '', ['', '/'], true)) {
        $errors[] = 'Informe a URL pública do domínio com HTTPS, por exemplo https://www.auravault.com.br.';
    }

    $requestHost = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
    $isLocalRequest = in_array($requestHost, ['localhost', '127.0.0.1'], true);
    $isSecureRequest = av_is_https();
    if (!$isLocalRequest && !$isSecureRequest) {
        $errors[] = 'Ative o certificado SSL e acesse o instalador por HTTPS antes de informar credenciais.';
    }

    if ($errors !== []) {
        return [$errors, $old];
    }

    $configPath = av_config_path();
    $privateDirectory = dirname($configPath);
    $mediaDirectory = $privateDirectory . DIRECTORY_SEPARATOR . 'media';
    $configWritten = false;

    try {
        if (!is_dir($privateDirectory) && !mkdir($privateDirectory, 0700, true) && !is_dir($privateDirectory)) {
            throw new RuntimeException('Não foi possível criar o diretório privado.');
        }
        @chmod($privateDirectory, 0700);
        if (!is_dir($mediaDirectory) && !mkdir($mediaDirectory, 0700, true) && !is_dir($mediaDirectory)) {
            throw new RuntimeException('Não foi possível criar o armazenamento de mídia.');
        }
        @chmod($mediaDirectory, 0700);

        $database = [
            'driver' => 'mysql',
            'host' => $old['db_host'],
            'name' => $old['db_name'],
            'user' => $old['db_user'],
            'password' => $databasePassword,
        ];
        $db = av_connect_database($database);
        av_create_schema($db);

        $ownerCount = (int) $db->query('SELECT COUNT(*) FROM owners')->fetchColumn();
        if ($ownerCount > 0) {
            throw new RuntimeException('Este banco já possui um titular. Use outro banco vazio ou restaure o acesso pelo cPanel.');
        }

        $config = [
            'db' => $database,
            'base_url' => rtrim($old['base_url'], '/'),
            'media_dir' => $mediaDirectory,
        ];
        $temporaryConfig = $configPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $configContents = "<?php\ndeclare(strict_types=1);\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents($temporaryConfig, $configContents, LOCK_EX) === false) {
            throw new RuntimeException('Não foi possível salvar a configuração privada.');
        }
        @chmod($temporaryConfig, 0600);
        if (is_file($configPath)) {
            @unlink($temporaryConfig);
            throw new RuntimeException('Instalação concluída por outra requisição.');
        }
        if (!rename($temporaryConfig, $configPath)) {
            @unlink($temporaryConfig);
            throw new RuntimeException('Não foi possível ativar a configuração privada.');
        }
        @chmod($configPath, 0600);
        $configWritten = true;

        $statement = $db->prepare('INSERT INTO owners (email, password_hash) VALUES (?, ?)');
        $statement->execute([$old['owner_email'], password_hash($ownerPassword, PASSWORD_DEFAULT)]);
        $keyRemoved = @unlink($keyPath);
        @unlink($throttlePath);
        av_flash('notice', $keyRemoved
            ? ['type' => 'success', 'message' => 'Instalação concluída e chave de instalação removida. Entre com o e-mail e a senha do titular.']
            : ['type' => 'error', 'message' => 'Instalação concluída, mas o arquivo install.key não pôde ser removido. Apague-o agora pelo cPanel.']);
        return [[], []];
    } catch (Throwable $exception) {
        if ($configWritten) {
            @unlink($configPath);
        }
        av_log('setup failed', ['error' => $exception->getMessage()]);
        $errors[] = $exception instanceof PDOException
            ? 'Não foi possível conectar ou criar as tabelas. Confira servidor, banco, usuário, senha e privilégios no cPanel.'
            : 'Não foi possível concluir a instalação. Verifique as permissões para criar o armazenamento privado e consulte o log de erros PHP.';
        return [$errors, $old];
    }
}

try {
    if (!av_is_installed()) {
        if (av_read_install_key(av_install_key_path()) === null) {
            http_response_code(403);
            av_render_setup_locked();
            exit;
        }
        $errors = [];
        $old = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            [$errors, $old] = av_install_post();
            if ($errors === []) {
                av_redirect('?action=login');
            }
        }
        av_render_setup($errors, $old);
        exit;
    }

    $config = av_load_config();
    $db = av_db();

    if ($action === 'shared') {
        $token = (string) ($_GET['token'] ?? '');
        $media = av_find_share($db, $token);
        if (!$media) {
            av_render_share_unavailable();
            exit;
        }
        header('Cache-Control: no-store, private');
        av_render_media_view($media, $token);
        exit;
    }

    $owner = av_owner($db);

    if ($action === 'thumb') {
        if (!$owner) {
            http_response_code(403);
            exit;
        }
        $media = av_find_media($db, (string) ($_GET['id'] ?? ''), (int) $owner['id']);
        if (!$media) {
            http_response_code(404);
            exit;
        }
        session_write_close();
        av_serve_thumbnail($media, $config['media_dir']);
    }

    if ($action === 'file') {
        $token = (string) ($_GET['token'] ?? '');
        if ($token !== '') {
            $media = av_find_share($db, $token);
        } elseif ($owner) {
            $media = av_find_media($db, (string) ($_GET['id'] ?? ''), (int) $owner['id']);
        } else {
            av_redirect('?action=login');
        }

        if (!$media) {
            http_response_code(404);
            exit('Arquivo não encontrado ou link indisponível.');
        }
        session_write_close();
        av_stream_media($media, $config['media_dir'], ($_GET['download'] ?? '') === '1');
    }

    if ($action === 'login') {
        $errors = [];
        $email = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $ip = av_client_ip();
            if (!av_verify_csrf()) {
                $errors[] = 'A sessão expirou. Recarregue a página.';
            } elseif (!av_login_begin_attempt($db, $ip, $email)) {
                av_log('login blocked', ['ip' => $ip]);
                $errors[] = 'Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.';
            } else {
                $ownerId = av_login_verify($db, $email, (string) ($_POST['password'] ?? ''));
                if ($ownerId !== null) {
                    av_login_clear($db, $ip, $email);
                    av_purge_old_shares($db);
                    session_regenerate_id(true);
                    $_SESSION['owner_id'] = $ownerId;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    av_redirect('./');
                }

                av_log('login failed', ['ip' => $ip]);
                $errors[] = 'E-mail ou senha incorretos.';
            }
        }
        av_render_login($errors, $email);
        exit;
    }

    if ($action === 'logout') {
        av_require_post();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        av_redirect('?action=login');
    }

    if (!$owner) {
        av_redirect('?action=login');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        av_require_post();
        $ownerId = (int) $owner['id'];

        if ($action === 'upload') {
            if (!isset($_FILES['media'])) {
                av_flash('notice', ['type' => 'error', 'message' => 'Selecione pelo menos um arquivo.']);
            } else {
                $result = av_store_uploads($db, $ownerId, $_FILES['media'], $config['media_dir']);
                $message = $result['stored'] . ' arquivo(s) adicionado(s).';
                if ($result['errors'] !== []) {
                    $message .= ' Falhas: ' . implode(' | ', array_slice($result['errors'], 0, 5));
                    if (count($result['errors']) > 5) {
                        $message .= ' | e mais ' . (count($result['errors']) - 5) . ' arquivo(s).';
                    }
                }
                av_flash('notice', ['type' => $result['errors'] === [] ? 'success' : 'error', 'message' => $message]);
            }
            av_redirect('./');
        }

        if ($action === 'share') {
            $media = av_find_media($db, (string) ($_POST['media_id'] ?? ''), $ownerId);
            $days = (int) ($_POST['days'] ?? 0);
            if (!$media || !in_array($days, [1, 7, 30], true)) {
                av_flash('notice', ['type' => 'error', 'message' => 'Não foi possível criar o link. Confira o arquivo e a validade escolhida.']);
            } else {
                $token = bin2hex(random_bytes(32));
                $expiresAt = gmdate('Y-m-d H:i:s', time() + ($days * 86400));
                $statement = $db->prepare('INSERT INTO shares (id, media_id, token_hash, expires_at) VALUES (?, ?, ?, ?)');
                $statement->execute([bin2hex(random_bytes(16)), $media['id'], hash('sha256', $token), $expiresAt]);
                av_log('share created', ['media' => $media['id'], 'days' => $days]);
                $shareUrl = $config['base_url'] . '/?action=shared&token=' . rawurlencode($token);
                av_flash('notice', [
                    'type' => 'success',
                    'message' => 'Link criado para “' . $media['original_name'] . '”. Ele expira em ' . $days . ' dia(s).',
                    'share_url' => $shareUrl,
                ]);
            }
            av_redirect('./');
        }

        if ($action === 'revoke') {
            $statement = $db->prepare(
                'UPDATE shares SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL
                 AND media_id IN (SELECT id FROM media WHERE owner_id = ?)'
            );
            $statement->execute([av_utc_now(), (string) ($_POST['share_id'] ?? ''), $ownerId]);
            av_flash('notice', ['type' => 'success', 'message' => $statement->rowCount() > 0 ? 'Link revogado.' : 'O link já expirou, foi revogado ou não pertence à sua conta.']);
            av_redirect('./');
        }

        if ($action === 'delete') {
            $media = av_find_media($db, (string) ($_POST['media_id'] ?? ''), $ownerId);
            if (!$media) {
                av_flash('notice', ['type' => 'error', 'message' => 'Arquivo não encontrado na sua biblioteca.']);
                av_redirect('./');
            }

            $statement = $db->prepare('DELETE FROM media WHERE id = ? AND owner_id = ?');
            $statement->execute([$media['id'], $ownerId]);
            $path = $config['media_dir'] . DIRECTORY_SEPARATOR . $media['stored_name'];
            if (is_file($path) && !@unlink($path)) {
                av_log('media file not removed', ['media' => $media['id']]);
            }
            av_delete_thumbnail($config['media_dir'], $media['id']);
            av_log('media deleted', ['media' => $media['id']]);
            av_flash('notice', ['type' => 'success', 'message' => 'Arquivo e links associados excluídos.']);
            av_redirect('./');
        }

        if ($action === 'password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            if (!hash_equals($new, (string) ($_POST['confirm_password'] ?? ''))) {
                $passwordErrors = ['A confirmação não confere com a nova senha.'];
            } else {
                $passwordErrors = av_change_password($db, $ownerId, $current, $new);
            }
            if ($passwordErrors === []) {
                session_regenerate_id(true);
                av_log('password changed', ['owner' => $ownerId]);
                av_flash('notice', ['type' => 'success', 'message' => 'Senha alterada com sucesso.']);
            } else {
                av_flash('notice', ['type' => 'error', 'message' => implode(' ', $passwordErrors)]);
            }
            av_redirect('?action=account');
        }
    }

    if ($action === 'account') {
        av_render_account($owner, av_flash('notice'));
        exit;
    }

    if ($action === 'view') {
        $media = av_find_media($db, (string) ($_GET['id'] ?? ''), (int) $owner['id']);
        if (!$media) {
            av_render_failure('Este arquivo não existe na sua biblioteca.', 404);
            exit;
        }
        header('Cache-Control: no-store, private');
        av_render_media_view($media);
        exit;
    }

    if ($action === 'guide') {
        av_render_guide($owner);
        exit;
    }

    if ($action === 'security') {
        av_render_security($owner);
        exit;
    }

    $ownerId = (int) $owner['id'];
    $filters = av_library_filters($_GET);
    $stats = av_library_stats($db, $ownerId);
    [$where, $whereParams] = av_library_where($filters, $ownerId);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = 24;
    $totalStatement = $db->prepare('SELECT COUNT(*) FROM media WHERE ' . $where);
    $totalStatement->execute($whereParams);
    $total = (int) $totalStatement->fetchColumn();
    $pages = max(1, (int) ceil($total / $pageSize));
    $page = min($page, $pages);
    $offset = ($page - 1) * $pageSize;
    $orderBy = av_library_sorts()[$filters['sort']]['sql'];
    $statement = $db->prepare('SELECT ' . AV_MEDIA_COLUMNS . ' FROM media WHERE ' . $where . ' ORDER BY ' . $orderBy . ' LIMIT ' . $pageSize . ' OFFSET ' . $offset);
    $statement->execute($whereParams);
    $items = $statement->fetchAll();

    $activeShares = [];
    if ($items !== []) {
        $ids = array_column($items, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $shareStatement = $db->prepare(
            'SELECT id, media_id, expires_at FROM shares
             WHERE media_id IN (' . $placeholders . ') AND revoked_at IS NULL AND expires_at > ?
             ORDER BY created_at DESC'
        );
        $shareStatement->execute([...$ids, av_utc_now()]);
        foreach ($shareStatement->fetchAll() as $share) {
            $activeShares[$share['media_id']][] = $share;
        }
    }

    av_render_library($owner, $items, $activeShares, $page, $pages, $total, $filters, $stats, av_flash('notice'));
} catch (Throwable $exception) {
    av_log('request failed', [
        'error' => $exception->getMessage(),
        'at' => basename($exception->getFile()) . ':' . $exception->getLine(),
    ]);
    av_render_failure('O servidor não conseguiu concluir essa solicitação. Confira a configuração do PHP e do banco de dados ou tente novamente.');
}
