<?php
declare(strict_types=1);

function av_page_start(string $title): void
{
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="robots" content="noindex,nofollow,noarchive">
      <title><?= av_e($title) ?> | AuraVault</title>
      <link rel="stylesheet" href="assets/style.css">
    </head>
    <body>
      <header class="topbar">
        <a class="brand" href="./" aria-label="AuraVault, início"><span class="brand-mark">A</span><span>AuraVault</span></a>
        <span class="topbar-caption">Cofre privado de mídia</span>
      </header>
      <main class="page-shell">
    <?php
}

function av_page_end(): void
{
    ?>
      </main>
      <footer class="site-footer">Seus arquivos permanecem privados até você criar um link de compartilhamento.</footer>
      <script src="assets/app.js" defer></script>
    </body>
    </html>
    <?php
}

function av_render_setup(array $errors = [], array $old = []): void
{
    av_page_start('Configuração inicial');
    ?>
    <section class="auth-panel setup-panel">
      <p class="eyebrow">PRIMEIRO ACESSO</p>
      <h1>Configure seu cofre</h1>
      <p class="lede">Crie o banco no cPanel e informe os dados abaixo. Eles serão gravados fora da pasta pública do site.</p>
      <?php av_render_errors($errors); ?>
      <form method="post" class="stack-form" autocomplete="on">
        <?= av_csrf_field() ?>
        <fieldset>
          <legend>Banco MySQL do cPanel</legend>
          <label for="db_host">Servidor do banco</label>
          <input id="db_host" name="db_host" value="<?= av_e($old['db_host'] ?? 'localhost') ?>" required autocomplete="off">
          <label for="db_name">Nome do banco</label>
          <input id="db_name" name="db_name" value="<?= av_e($old['db_name'] ?? '') ?>" required autocomplete="off">
          <label for="db_user">Usuário do banco</label>
          <input id="db_user" name="db_user" value="<?= av_e($old['db_user'] ?? '') ?>" required autocomplete="username">
          <label for="db_password">Senha do banco</label>
          <input id="db_password" name="db_password" type="password" required autocomplete="new-password">
        </fieldset>
        <fieldset>
          <legend>Titular e endereço</legend>
          <label for="owner_email">E-mail do titular</label>
          <input id="owner_email" name="owner_email" type="email" value="<?= av_e($old['owner_email'] ?? '') ?>" required autocomplete="email">
          <label for="owner_password">Senha do AuraVault</label>
          <input id="owner_password" name="owner_password" type="password" minlength="12" required autocomplete="new-password">
          <small>Use pelo menos 12 caracteres e não reutilize a senha do cPanel.</small>
          <label for="base_url">URL pública com HTTPS</label>
          <input id="base_url" name="base_url" type="url" value="<?= av_e($old['base_url'] ?? 'https://www.auravault.com.br') ?>" required>
        </fieldset>
        <button class="button button-primary" type="submit">Instalar AuraVault</button>
      </form>
      <p class="security-note">O instalador cria somente um titular. Não compartilhe as credenciais do banco nem deixe o site sem HTTPS.</p>
    </section>
    <?php
    av_page_end();
}

function av_render_login(array $errors = [], string $email = ''): void
{
    av_page_start('Entrar');
    ?>
    <section class="auth-panel login-panel">
      <p class="eyebrow">ACESSO DO TITULAR</p>
      <h1>Entre no seu cofre</h1>
      <p class="lede">Somente a conta titular pode organizar e compartilhar os arquivos.</p>
      <?php av_render_errors($errors); ?>
      <form method="post" action="?action=login" class="stack-form">
        <?= av_csrf_field() ?>
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" value="<?= av_e($email) ?>" required autocomplete="username">
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <button class="button button-primary" type="submit">Entrar</button>
      </form>
      <p class="security-note">Os links compartilhados dão acesso apenas ao arquivo escolhido e deixam de funcionar na data de expiração ou quando revogados.</p>
    </section>
    <?php
    av_page_end();
}

function av_render_errors(array $errors): void
{
    if ($errors === []) {
        return;
    }
    ?>
    <div class="alert alert-error" role="alert">
      <strong>Não foi possível continuar.</strong>
      <ul>
        <?php foreach ($errors as $error): ?>
          <li><?= av_e($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php
}

function av_render_library(array $owner, array $media, array $activeShares, int $page, int $pages, ?array $notice): void
{
    av_page_start('Minha biblioteca');
    ?>
    <div class="dashboard-heading">
      <div>
        <p class="eyebrow">BIBLIOTECA PRIVADA</p>
        <h1>Olá, <?= av_e(explode('@', $owner['email'])[0]) ?></h1>
        <p class="lede">Imagens e vídeos ficam privados. Crie um link temporário somente para os arquivos que deseja compartilhar.</p>
      </div>
      <form method="post" action="?action=logout">
        <?= av_csrf_field() ?>
        <button class="button button-quiet" type="submit">Sair</button>
      </form>
    </div>

    <?php if ($notice): ?>
      <div class="alert <?= ($notice['type'] ?? '') === 'error' ? 'alert-error' : 'alert-success' ?>" role="status">
        <div><?= av_e($notice['message'] ?? '') ?></div>
        <?php if (!empty($notice['share_url'])): ?>
          <div class="share-created">
            <label for="share-created-url">Link gerado. Copie e envie apenas à pessoa desejada:</label>
            <input id="share-created-url" type="text" readonly value="<?= av_e($notice['share_url']) ?>" data-select-on-click>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <section class="upload-strip" aria-labelledby="upload-title">
      <div>
        <p class="eyebrow">ADICIONAR ARQUIVOS</p>
        <h2 id="upload-title">Envie fotos ou vídeos</h2>
        <p>JPG, PNG, GIF, WebP, MP4, WebM e MOV. Até 100 MB por arquivo, sujeito ao limite do plano de hospedagem.</p>
      </div>
      <form method="post" action="?action=upload" enctype="multipart/form-data" class="upload-form">
        <?= av_csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="104857600">
        <label for="media-files">Escolher um ou mais arquivos</label>
        <input id="media-files" name="media[]" type="file" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime" multiple required>
        <button class="button button-primary" type="submit">Enviar ao cofre</button>
      </form>
    </section>

    <div class="library-heading">
      <div>
        <p class="eyebrow">SEUS ARQUIVOS</p>
        <h2>Biblioteca</h2>
      </div>
      <span class="item-count"><?= count($media) ?> neste resultado<?= count($media) === 1 ? '' : 's' ?></span>
    </div>

    <?php if ($media === []): ?>
      <section class="empty-state">
        <span class="empty-mark" aria-hidden="true">+</span>
        <h2>Sua biblioteca está vazia</h2>
        <p>Envie uma imagem ou vídeo para começar. Arquivos de outros titulares não são visíveis nesta conta.</p>
      </section>
    <?php else: ?>
      <div class="media-grid">
        <?php foreach ($media as $item): ?>
          <?php $shares = $activeShares[$item['id']] ?? []; ?>
          <article class="media-item">
            <div class="media-item-heading">
              <span class="media-type"><?= str_starts_with($item['mime_type'], 'video/') ? 'VÍDEO' : 'IMAGEM' ?></span>
              <span class="media-size"><?= av_e(av_format_bytes((int) $item['size_bytes'])) ?></span>
            </div>
            <h3 title="<?= av_e($item['original_name']) ?>"><?= av_e($item['original_name']) ?></h3>
            <p class="media-date">Adicionado em <?= av_e(date('d/m/Y H:i', strtotime($item['created_at'] . ' UTC'))) ?> UTC</p>
            <div class="media-actions">
              <a class="button button-small button-outline" href="?action=view&amp;id=<?= av_e($item['id']) ?>">Visualizar</a>
              <a class="text-link" href="?action=file&amp;id=<?= av_e($item['id']) ?>&amp;download=1">Baixar</a>
            </div>
            <form method="post" action="?action=share" class="share-form">
              <?= av_csrf_field() ?>
              <input type="hidden" name="media_id" value="<?= av_e($item['id']) ?>">
              <label for="days-<?= av_e($item['id']) ?>">Link válido por</label>
              <div class="share-controls">
                <select id="days-<?= av_e($item['id']) ?>" name="days">
                  <option value="1">1 dia</option>
                  <option value="7" selected>7 dias</option>
                  <option value="30">30 dias</option>
                </select>
                <button class="button button-small button-share" type="submit">Criar link</button>
              </div>
            </form>
            <?php if ($shares !== []): ?>
              <div class="active-shares">
                <h4>Links ativos</h4>
                <?php foreach ($shares as $share): ?>
                  <div class="active-share-row">
                    <span>Expira <?= av_e(date('d/m/Y H:i', strtotime($share['expires_at'] . ' UTC'))) ?> UTC</span>
                    <form method="post" action="?action=revoke">
                      <?= av_csrf_field() ?>
                      <input type="hidden" name="share_id" value="<?= av_e($share['id']) ?>">
                      <button class="text-link text-danger" type="submit">Revogar</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <form method="post" action="?action=delete" class="delete-form" data-confirm="Excluir este arquivo e todos os links associados? Esta ação não pode ser desfeita.">
              <?= av_csrf_field() ?>
              <input type="hidden" name="media_id" value="<?= av_e($item['id']) ?>">
              <button class="text-link text-danger" type="submit">Excluir arquivo</button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Paginação da biblioteca">
          <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">← Anterior</a><?php endif; ?>
          <span>Página <?= $page ?> de <?= $pages ?></span>
          <?php if ($page < $pages): ?><a href="?page=<?= $page + 1 ?>">Próxima →</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
    <p class="data-note">Arquivos e banco ficam na sua conta HostGator. O AuraVault não criptografa mídias em repouso; use HTTPS e mantenha cópias de segurança protegidas.</p>
    <?php
    av_page_end();
}

function av_render_media_view(array $media, ?string $shareToken = null): void
{
    av_page_start('Visualizar mídia');
    $source = $shareToken !== null
        ? '?action=file&amp;token=' . rawurlencode($shareToken)
        : '?action=file&amp;id=' . rawurlencode((string) $media['id']);
    ?>
    <section class="viewer-heading">
      <a class="back-link" href="<?= $shareToken === null ? './' : '#' ?>" <?= $shareToken !== null ? 'data-history-back' : '' ?>>← <?= $shareToken === null ? 'Voltar à biblioteca' : 'Voltar' ?></a>
      <p class="eyebrow">MÍDIA <?= str_starts_with($media['mime_type'], 'video/') ? 'COMPARTILHADA' : 'PRIVADA' ?></p>
      <h1><?= av_e($media['original_name']) ?></h1>
    </section>
    <div class="viewer-frame">
      <?php if (str_starts_with($media['mime_type'], 'image/')): ?>
        <img class="viewer-image" src="<?= $source ?>" alt="<?= av_e($media['original_name']) ?>">
      <?php else: ?>
        <video class="viewer-video" controls preload="metadata" src="<?= $source ?>"></video>
      <?php endif; ?>
    </div>
    <div class="viewer-actions">
      <a class="button button-outline" href="<?= str_replace('&amp;', '&', $source) ?>&amp;download=1">Baixar arquivo</a>
      <?php if (!empty($media['share_expires_at'])): ?>
        <span>Link expira em <?= av_e(date('d/m/Y H:i', strtotime($media['share_expires_at'] . ' UTC'))) ?> UTC</span>
      <?php endif; ?>
    </div>
    <?php
    av_page_end();
}

function av_render_share_unavailable(): void
{
    http_response_code(404);
    av_page_start('Link indisponível');
    ?>
    <section class="empty-state">
      <p class="eyebrow">LINK PRIVADO</p>
      <h1>Este link não está disponível</h1>
      <p>Ele pode ter expirado ou sido revogado pelo titular.</p>
    </section>
    <?php
    av_page_end();
}

function av_render_failure(string $message, int $status = 500): void
{
    http_response_code($status);
    av_page_start('Não foi possível concluir');
    ?>
    <section class="empty-state">
      <p class="eyebrow">AURAVAULT</p>
      <h1>Não foi possível concluir</h1>
      <p><?= av_e($message) ?></p>
    </section>
    <?php
    av_page_end();
}
