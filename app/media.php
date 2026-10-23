<?php
declare(strict_types=1);

function av_supported_media(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];
}

function av_normalize_uploads(array $upload): array
{
    if (!isset($upload['name']) || !is_array($upload['name'])) {
        return [$upload];
    }

    $files = [];
    foreach ($upload['name'] as $index => $name) {
        $files[] = [
            'name' => $name,
            'type' => $upload['type'][$index] ?? '',
            'tmp_name' => $upload['tmp_name'][$index] ?? '',
            'error' => $upload['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $upload['size'][$index] ?? 0,
        ];
    }

    return $files;
}

function av_store_uploads(PDO $db, int $ownerId, array $upload, string $mediaDirectory): array
{
    $stored = 0;
    $errors = [];
    $supported = av_supported_media();
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach (av_normalize_uploads($upload) as $file) {
        $originalPath = str_replace('\\', '/', (string) ($file['name'] ?? 'arquivo'));
        $originalName = basename($originalPath);
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] = av_upload_error_message($originalName, $uploadError);
            continue;
        }
        if ((int) ($file['size'] ?? 0) > AV_MAX_UPLOAD_BYTES) {
            $errors[] = $originalName . ': excede o limite de 100 MB por arquivo.';
            continue;
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($temporaryPath) || !is_file($temporaryPath)) {
            $errors[] = $originalName . ': o arquivo recebido não pôde ser validado.';
            continue;
        }

        $mimeType = $finfo->file($temporaryPath) ?: '';
        if (!isset($supported[$mimeType])) {
            $errors[] = $originalName . ': formato não permitido ou arquivo ilegível.';
            continue;
        }

        $safeOriginalName = substr($originalName, 0, 255);
        $storedName = bin2hex(random_bytes(24)) . '.' . $supported[$mimeType];
        $targetPath = $mediaDirectory . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($temporaryPath, $targetPath)) {
            $errors[] = $originalName . ': não foi possível salvar no armazenamento privado.';
            continue;
        }
        @chmod($targetPath, 0600);

        try {
            $statement = $db->prepare(
                'INSERT INTO media (id, owner_id, stored_name, original_name, mime_type, size_bytes)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                bin2hex(random_bytes(16)),
                $ownerId,
                $storedName,
                $safeOriginalName,
                $mimeType,
                filesize($targetPath) ?: 0,
            ]);
            $stored++;
        } catch (Throwable $exception) {
            @unlink($targetPath);
            error_log('AuraVault: falha ao registrar upload: ' . $exception->getMessage());
            $errors[] = $originalName . ': falha ao registrar o arquivo.';
        }
    }

    return ['stored' => $stored, 'errors' => $errors];
}

function av_upload_error_message(string $name, int $error): string
{
    $message = match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'excede o limite de upload configurado no servidor.',
        UPLOAD_ERR_PARTIAL => 'foi recebido parcialmente; tente novamente.',
        UPLOAD_ERR_NO_TMP_DIR => 'o servidor está sem diretório temporário para uploads.',
        UPLOAD_ERR_CANT_WRITE => 'o servidor não conseguiu gravar o arquivo.',
        UPLOAD_ERR_EXTENSION => 'uma extensão do PHP bloqueou o upload.',
        default => 'não pôde ser enviado.',
    };

    return $name . ': ' . $message;
}

function av_find_media(PDO $db, string $id, int $ownerId): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
        return null;
    }

    $statement = $db->prepare('SELECT * FROM media WHERE id = ? AND owner_id = ?');
    $statement->execute([$id, $ownerId]);
    $media = $statement->fetch();

    return $media ?: null;
}

function av_find_share(PDO $db, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $statement = $db->prepare(
        'SELECT media.*, shares.expires_at AS share_expires_at
         FROM shares
         INNER JOIN media ON media.id = shares.media_id
         WHERE shares.token_hash = ? AND shares.revoked_at IS NULL AND shares.expires_at > ?'
    );
    $statement->execute([hash('sha256', $token), av_utc_now()]);
    $media = $statement->fetch();

    return $media ?: null;
}

function av_stream_media(array $media, string $mediaDirectory, bool $download = false): never
{
    $storedName = (string) ($media['stored_name'] ?? '');
    if (!preg_match('/^[a-f0-9]{48}\.(jpg|png|gif|webp|mp4|webm|mov)$/', $storedName)) {
        http_response_code(404);
        exit('Arquivo não encontrado.');
    }

    $path = $mediaDirectory . DIRECTORY_SEPARATOR . $storedName;
    if (!is_file($path) || !is_readable($path)) {
        http_response_code(404);
        exit('Arquivo não encontrado.');
    }

    $size = filesize($path);
    if ($size === false) {
        http_response_code(500);
        exit('Não foi possível ler o arquivo.');
    }

    $start = 0;
    $end = max(0, $size - 1);
    $status = 200;
    $range = $_SERVER['HTTP_RANGE'] ?? '';

    if (!$download && $range !== '') {
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches) || $size === 0) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            exit;
        }

        if ($matches[1] === '') {
            $suffixLength = (int) $matches[2];
            if ($suffixLength < 1) {
                header('Content-Range: bytes */' . $size);
                http_response_code(416);
                exit;
            }
            $start = max(0, $size - $suffixLength);
        } else {
            $start = (int) $matches[1];
            if ($matches[2] !== '') {
                $end = min($end, (int) $matches[2]);
            }
        }

        if ($start > $end || $start >= $size) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            exit;
        }
        $status = 206;
    }

    $length = $size === 0 ? 0 : $end - $start + 1;
    $mimeType = (string) ($media['mime_type'] ?? 'application/octet-stream');
    $disposition = $download ? 'attachment' : 'inline';
    $originalName = basename(str_replace('\\', '/', (string) ($media['original_name'] ?? 'media')));

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $length);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, no-store, max-age=0');
    header('Content-Disposition: ' . $disposition . '; filename="media"; filename*=UTF-8\'\'' . rawurlencode($originalName));
    if ($status === 206) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        http_response_code(500);
        exit;
    }
    if ($start > 0) {
        fseek($handle, $start);
    }

    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(1024 * 1024, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
        flush();
    }
    fclose($handle);
    exit;
}

function av_format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $value = (float) $bytes;
    foreach ($units as $unit) {
        if ($value < 1024 || $unit === 'GB') {
            return number_format($value, $unit === 'B' ? 0 : 1, ',', '.') . ' ' . $unit;
        }
        $value /= 1024;
    }

    return $bytes . ' B';
}
