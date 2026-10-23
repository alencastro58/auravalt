<?php
declare(strict_types=1);

function av_page_start(string $title, ?array $owner = null, string $active = ''): void
{
    $nav = ['library' => ['./', 'Dashboard de Mídias'], 'guide' => ['?action=guide', 'Recursos e como usar'], 'security' => ['?action=security', 'Segurança e LGPD']];
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="robots" content="noindex,nofollow,noarchive">
      <meta name="color-scheme" content="dark">
      <title><?= av_e($title) ?> | AuraVault</title>
      <link rel="stylesheet" href="assets/style.css?v=0.2.0">
    </head>
    <body>
      <header class="topbar">
        <div class="topbar-inner">
          <a class="brand" href="./" aria-label="AuraVault, início">
            <span class="brand-mark">A</span>
            <span class="brand-text"><strong>AuraVault</strong><small>Cofre privado de mídia</small></span>
          </a>
          <?php if ($owner): ?>
            <nav class="main-nav" aria-label="Seções">
              <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= av_e($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= av_e($label) ?></a>
              <?php endforeach; ?>
            </nav>
            <div class="topbar-actions">
              <span class="owner-name">Titular: <strong><?= av_e(explode('@', (string) $owner['email'])[0]) ?></strong></span>
              <a class="button button-primary" href="./#enviar" data-open-upload>+ Enviar mídia</a>
              <form method="post" action="?action=logout">
                <?= av_csrf_field() ?>
                <button class="button button-quiet" type="submit">Sair</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </header>
      <main class="page-shell">
    <?php
}

function av_page_end(): void
{
    ?>
      </main>
      <footer class="site-footer">Arquivos e banco ficam na conta HostGator do titular. Seus arquivos permanecem privados até você criar um link de compartilhamento.</footer>
      <script src="assets/app.js?v=0.2.0" defer></script>
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
          <legend>Autorização</legend>
          <label for="install_key">Chave de instalação</label>
          <input id="install_key" name="install_key" type="password" required autocomplete="off" spellcheck="false">
          <small>Conteúdo do arquivo install.key criado por você no cPanel. Ele é apagado ao final.</small>
        </fieldset>
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

function av_render_setup_locked(): void
{
    av_page_start('Instalação bloqueada');
    ?>
    <section class="auth-panel setup-panel">
      <p class="eyebrow">INSTALAÇÃO BLOQUEADA</p>
      <h1>Cofre ainda não configurado</h1>
      <p class="lede">A instalação só é liberada para o responsável pela hospedagem, mediante chave gravada no servidor.</p>
      <p class="security-note">Se você é o responsável, siga a seção “Chave de instalação” do guia de publicação.</p>
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

function av_render_notice(?array $notice): void
{
    if (!$notice) {
        return;
    }
    ?>
    <div class="alert <?= ($notice['type'] ?? '') === 'error' ? 'alert-error' : 'alert-success' ?>" role="status">
      <div><?= av_e($notice['message'] ?? '') ?></div>
      <?php if (!empty($notice['share_url'])): ?>
        <div class="share-created">
          <label for="share-created-url">Link gerado. Ele aparece só agora: copie e envie apenas à pessoa desejada.</label>
          <div class="share-created-row">
            <input id="share-created-url" type="text" readonly value="<?= av_e($notice['share_url']) ?>" data-select-on-click>
            <button class="button button-primary" type="button" data-copy="#share-created-url">Copiar link</button>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

function av_render_library(array $owner, array $media, array $activeShares, int $page, int $pages, int $matching, array $filters, array $stats, ?array $notice): void
{
    av_page_start('Dashboard de Mídias', $owner, 'library');
    $types = ['all' => ['Todos', $stats['total']], 'image' => ['Fotos', $stats['images']], 'video' => ['Vídeos', $stats['videos']]];
    $title = ['all' => 'Todas as mídias', 'image' => 'Fotos', 'video' => 'Vídeos'][$filters['type']];
    $filtered = $filters['q'] !== '' || $filters['type'] !== 'all';
    ?>
    <div class="dash">
      <aside class="panel dash-side">
        <div class="side-head">
          <span class="side-title">NAVEGAÇÃO</span>
          <span class="side-sub"><?= $stats['total'] ?> mídia<?= $stats['total'] === 1 ? '' : 's' ?> · <?= $stats['shares'] ?> link<?= $stats['shares'] === 1 ? '' : 's' ?> ativo<?= $stats['shares'] === 1 ? '' : 's' ?></span>
        </div>
        <form method="get" action="./" class="filter-form" data-filter-form>
          <label class="side-label" for="q">BUSCA</label>
          <input id="q" name="q" type="search" value="<?= av_e($filters['q']) ?>" placeholder="Buscar por nome do arquivo…" maxlength="100" autocomplete="off">
          <span class="side-label">TIPO DE MÍDIA</span>
          <div class="type-tabs" role="radiogroup" aria-label="Tipo de mídia">
            <?php foreach ($types as $key => [$label, $count]): ?>
              <label class="type-tab">
                <input type="radio" name="type" value="<?= $key ?>"<?= $filters['type'] === $key ? ' checked' : '' ?> data-autosubmit>
                <span><?= $label ?></span><small><?= $count ?></small>
              </label>
            <?php endforeach; ?>
          </div>
          <label class="side-label" for="sort">ORDENAR POR</label>
          <select id="sort" name="sort" data-autosubmit>
            <?php foreach (av_library_sorts() as $key => $sort): ?>
              <option value="<?= $key ?>"<?= $filters['sort'] === $key ? ' selected' : '' ?>><?= av_e($sort['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="filter-actions">
            <button class="button button-quiet button-small" type="submit">Aplicar</button>
            <?php if ($filtered || $filters['sort'] !== 'recent'): ?><a class="text-link" href="./">Limpar filtros</a><?php endif; ?>
          </div>
        </form>
        <div class="side-usage">
          <span class="side-label">ESPAÇO USADO</span>
          <strong><?= av_e(av_format_bytes($stats['bytes'])) ?></strong>
          <small>Limite de 100 MB por arquivo. O total depende do plano HostGator.</small>
        </div>
        <a class="button button-soft" href="?action=guide">Ver guia de recursos →</a>
      </aside>

      <section class="dash-main">
        <?php av_render_notice($notice); ?>
        <div class="panel dash-hero">
          <span class="hero-mark" aria-hidden="true">▦</span>
          <div class="hero-text">
            <h1><?= $title ?></h1>
            <span><?= $stats['images'] ?> foto<?= $stats['images'] === 1 ? '' : 's' ?> · <?= $stats['videos'] ?> vídeo<?= $stats['videos'] === 1 ? '' : 's' ?> · <?= av_e(av_format_bytes($stats['bytes'])) ?><?= $filtered ? ' · ' . $matching . ' no filtro atual' : '' ?></span>
          </div>
          <a class="button button-outline" href="#enviar" data-open-upload>↑ Enviar</a>
        </div>

        <details class="panel upload-panel" id="enviar"<?= $stats['total'] === 0 ? ' open' : '' ?>>
          <summary>
            <span class="eyebrow">ENVIAR AO COFRE</span>
            <span class="upload-summary-title">Envie fotos ou vídeos</span>
            <span class="upload-summary-sub">JPG, PNG, GIF, WebP, MP4, WebM e MOV. Até 100 MB por arquivo.</span>
          </summary>
          <form method="post" action="?action=upload" enctype="multipart/form-data" class="upload-form">
            <?= av_csrf_field() ?>
            <input type="hidden" name="MAX_FILE_SIZE" value="104857600">
            <label class="dropzone" for="media-files">
              <strong>Escolher arquivos</strong>
              <span data-file-label>Selecione um ou mais arquivos</span>
              <input id="media-files" name="media[]" type="file" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime" multiple required>
            </label>
            <button class="button button-primary" type="submit">Enviar ao cofre</button>
          </form>
        </details>

        <?php if ($stats['total'] === 0): ?>
          <ol class="tips">
            <li><span>1</span><div><strong>Envie</strong><p>Use “+ Enviar mídia” para adicionar fotos e vídeos ao cofre.</p></div></li>
            <li><span>2</span><div><strong>Compartilhe com prazo</strong><p>Crie um link de 1, 7 ou 30 dias para um arquivo específico.</p></div></li>
            <li><span>3</span><div><strong>Revogue quando quiser</strong><p>Cancele o link pelo card. A mídia continua guardada.</p></div></li>
          </ol>
        <?php endif; ?>

        <?php if ($media === []): ?>
          <section class="panel empty-state">
            <?php if ($stats['total'] === 0): ?>
              <h2>Sua biblioteca está vazia</h2>
              <p>Envie uma imagem ou vídeo para começar.</p>
            <?php else: ?>
              <h2>Nenhuma mídia encontrada</h2>
              <p>Ajuste a busca ou o tipo de mídia. <a href="./">Limpar filtros</a></p>
            <?php endif; ?>
          </section>
        <?php else: ?>
          <div class="media-grid">
            <?php foreach ($media as $item): ?>
              <?php
                $shares = $activeShares[$item['id']] ?? [];
                $isVideo = str_starts_with((string) $item['mime_type'], 'video/');
                $id = av_e($item['id']);
              ?>
              <article class="media-card">
                <a class="media-thumb" href="?action=view&amp;id=<?= $id ?>" aria-label="Ver <?= av_e($item['original_name']) ?>">
                  <?php if ($isVideo): ?>
                    <video src="?action=file&amp;id=<?= $id ?>#t=0.5" preload="metadata" muted playsinline tabindex="-1" aria-hidden="true"></video>
                    <span class="thumb-play" aria-hidden="true">▶</span>
                  <?php else: ?>
                    <img src="?action=file&amp;id=<?= $id ?>" alt="" loading="lazy" decoding="async">
                  <?php endif; ?>
                  <span class="thumb-ext"><?= av_e(av_media_extension($item)) ?></span>
                </a>
                <div class="media-body">
                  <h3 title="<?= av_e($item['original_name']) ?>"><?= av_e($item['original_name']) ?></h3>
                  <p class="media-meta"><?= av_e(av_format_bytes((int) $item['size_bytes'])) ?> · enviado em <?= av_e(date('d/m/Y', strtotime($item['created_at'] . ' UTC'))) ?></p>
                  <?php foreach ($shares as $share): ?>
                    <div class="share-strip">
                      <span>Link ativo · expira em <?= av_e(date('d/m/Y H:i', strtotime($share['expires_at'] . ' UTC'))) ?> UTC</span>
                      <form method="post" action="?action=revoke">
                        <?= av_csrf_field() ?>
                        <input type="hidden" name="share_id" value="<?= av_e($share['id']) ?>">
                        <button class="link-danger" type="submit">Revogar</button>
                      </form>
                    </div>
                  <?php endforeach; ?>
                  <div class="card-actions">
                    <a class="button button-card" href="?action=view&amp;id=<?= $id ?>">Ver</a>
                    <details class="share-pop">
                      <summary class="button button-card button-card-accent">Compartilhar</summary>
                      <form method="post" action="?action=share" class="share-form">
                        <?= av_csrf_field() ?>
                        <input type="hidden" name="media_id" value="<?= $id ?>">
                        <span class="side-label">VALIDADE DO LINK</span>
                        <div class="day-options">
                          <?php foreach ([1 => '1 dia', 7 => '7 dias', 30 => '30 dias'] as $days => $label): ?>
                            <label class="day-option"><input type="radio" name="days" value="<?= $days ?>"<?= $days === 7 ? ' checked' : '' ?>><span><?= $label ?></span></label>
                          <?php endforeach; ?>
                        </div>
                        <button class="button button-primary button-small" type="submit">Criar link</button>
                      </form>
                    </details>
                    <form method="post" action="?action=delete" data-confirm="Excluir este arquivo e todos os links associados? Esta ação não pode ser desfeita.">
                      <?= av_csrf_field() ?>
                      <input type="hidden" name="media_id" value="<?= $id ?>">
                      <button class="button button-card button-card-danger" type="submit">Excluir</button>
                    </form>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
          <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Paginação da biblioteca">
              <?php if ($page > 1): ?><a href="<?= av_e(av_library_url($filters, ['page' => $page - 1])) ?>">← Anterior</a><?php endif; ?>
              <span>Página <?= $page ?> de <?= $pages ?></span>
              <?php if ($page < $pages): ?><a href="<?= av_e(av_library_url($filters, ['page' => $page + 1])) ?>">Próxima →</a><?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </div>
    <?php
    av_page_end();
}

function av_features(): array
{
    return [
        ['id' => 'enviar', 'name' => 'Enviar mídias', 'on' => true, 'where' => 'DASHBOARD › ENVIAR MÍDIA', 'summary' => 'Adiciona fotos e vídeos ao cofre. Os arquivos ficam na sua conta HostGator, em pasta fora do acesso público.', 'steps' => ['Clique em “+ Enviar mídia” no topo ou em “↑ Enviar” no painel.', 'Clique em “Escolher arquivos” e selecione um ou mais arquivos JPG, PNG, GIF, WebP, MP4, WebM ou MOV.', 'Clique em “Enviar ao cofre”. Cada arquivo é validado pelo conteúdo, não só pela extensão.', 'Os arquivos aparecem no início da biblioteca.'], 'note' => 'Arquivos acima de 100 MB ou com formato não suportado são recusados com uma mensagem.'],
        ['id' => 'biblioteca', 'name' => 'Biblioteca, busca e filtros', 'on' => true, 'where' => 'DASHBOARD › PAINEL LATERAL', 'summary' => 'Lista as mídias com miniatura, tamanho, data e links ativos. O painel lateral filtra por nome e tipo e muda a ordenação.', 'steps' => ['Digite parte do nome do arquivo em “Busca” e pressione Enter.', 'Escolha Todos, Fotos ou Vídeos em “Tipo de mídia”.', 'Mude a ordem em “Ordenar por”: mais recentes, mais antigos, maior tamanho ou nome.', 'Use “Limpar filtros” para voltar à lista completa.']],
        ['id' => 'ver', 'name' => 'Visualizar', 'on' => true, 'where' => 'CARD › VER', 'summary' => 'Mostra a mídia em tamanho grande. O arquivo é entregue pelo AuraVault após conferir a sessão, nunca por endereço público.', 'steps' => ['Clique na miniatura ou em “Ver”.', 'Fotos abrem em tela ampla. Vídeos tocam no reprodutor do navegador.', 'Use “Baixar arquivo” para salvar uma cópia.']],
        ['id' => 'compartilhar', 'name' => 'Link temporário', 'on' => true, 'where' => 'CARD › COMPARTILHAR', 'summary' => 'Gera um endereço único para um arquivo. Quem recebe vê só aquele arquivo, sem login, até a validade escolhida.', 'steps' => ['No card da mídia, clique em “Compartilhar”.', 'Escolha a validade: 1, 7 ou 30 dias.', 'Clique em “Criar link” e depois em “Copiar link” no aviso verde.', 'Envie o link pelo canal que preferir. Ao vencer o prazo, ele para de funcionar sozinho.'], 'note' => 'O link aparece uma única vez. O cofre guarda apenas o hash dele; se perder, crie outro.'],
        ['id' => 'revogar', 'name' => 'Revogar link', 'on' => true, 'where' => 'CARD › REVOGAR', 'summary' => 'Desativa um link temporário antes do prazo. A mídia continua no cofre.', 'steps' => ['Localize a faixa verde “Link ativo” no card.', 'Clique em “Revogar”.', 'Quem abrir o link passa a ver a página “Este link não está disponível”.']],
        ['id' => 'excluir', 'name' => 'Excluir mídia', 'on' => true, 'where' => 'CARD › EXCLUIR', 'summary' => 'Remove o registro e o arquivo do servidor. Os links daquela mídia deixam de funcionar.', 'steps' => ['No card, clique em “Excluir”.', 'Confirme a exclusão.', 'Não há lixeira: guarde uma cópia antes, se precisar.']],
        ['id' => 'acesso', 'name' => 'Acesso do titular', 'on' => true, 'where' => 'TELA DE ENTRADA', 'summary' => 'O cofre tem uma conta titular. A sessão usa cookie seguro e o acesso bloqueia temporariamente após várias senhas erradas.', 'steps' => ['Acesse auravault.com.br e informe e-mail e senha do titular.', 'Após tentativas erradas seguidas, aguarde alguns minutos.', 'Ao terminar, clique em “Sair”, principalmente em computadores compartilhados.']],
        ['id' => 'pastas', 'name' => 'Pastas', 'on' => false, 'where' => 'PREVISTO', 'summary' => 'Organização da biblioteca em pastas criadas pelo titular.', 'steps' => ['Criar, renomear e excluir pastas.', 'Mover uma ou várias mídias entre pastas.', 'Compartilhar uma pasta inteira com link temporário.']],
        ['id' => 'criptografia', 'name' => 'Criptografia em repouso', 'on' => false, 'where' => 'PREVISTO', 'summary' => 'Cada arquivo será cifrado antes de gravado, com chave fora da pasta pública. Uma cópia vazada do disco não exporá o conteúdo.', 'steps' => ['Novos envios passam a ser gravados cifrados.', 'Uma rotina migra os arquivos existentes.', 'A visualização decifra sob demanda, só para sessão autorizada ou link válido.']],
        ['id' => 'lgpd', 'name' => 'Painel do titular (LGPD)', 'on' => false, 'where' => 'PREVISTO', 'summary' => 'Direitos do titular previstos na LGPD (art. 18): acesso, portabilidade e eliminação dos dados.', 'steps' => ['Baixar um pacote com todas as mídias e metadados.', 'Consultar o histórico de entradas e de links criados.', 'Solicitar a exclusão completa da conta e dos arquivos.']],
        ['id' => 'backup', 'name' => 'Backup automático', 'on' => false, 'where' => 'PREVISTO', 'summary' => 'Cópias automáticas, cifradas e com retenção definida, para recuperar o cofre em caso de falha.', 'steps' => ['Backup diário do banco e semanal das mídias.', 'Cópias cifradas armazenadas fora do servidor.', 'Teste de restauração documentado.']],
    ];
}

function av_render_guide(array $owner): void
{
    av_page_start('Recursos e como usar', $owner, 'guide');
    $features = av_features();
    ?>
    <div class="dash">
      <aside class="panel dash-side guide-nav">
        <span class="side-label">RECURSOS</span>
        <?php foreach ($features as $feature): ?>
          <a href="#<?= $feature['id'] ?>"><span><?= av_e($feature['name']) ?></span><i class="dot <?= $feature['on'] ? 'dot-on' : 'dot-planned' ?>" aria-hidden="true"></i></a>
        <?php endforeach; ?>
        <div class="legend"><span><i class="dot dot-on"></i>Disponível</span><span><i class="dot dot-planned"></i>Em desenvolvimento</span></div>
      </aside>
      <section class="dash-main">
        <?php foreach ($features as $feature): ?>
          <article class="panel feature" id="<?= $feature['id'] ?>">
            <div class="feature-head">
              <div>
                <span class="eyebrow"><?= av_e($feature['where']) ?></span>
                <h2><?= av_e($feature['name']) ?></h2>
                <p><?= av_e($feature['summary']) ?></p>
              </div>
              <span class="badge <?= $feature['on'] ? 'badge-on' : 'badge-planned' ?>"><?= $feature['on'] ? 'Disponível' : 'Em desenvolvimento' ?></span>
            </div>
            <ol class="steps">
              <?php foreach ($feature['steps'] as $index => $step): ?>
                <li><span><?= $index + 1 ?></span><p><?= av_e($step) ?></p></li>
              <?php endforeach; ?>
            </ol>
            <?php if (!empty($feature['note'])): ?><p class="feature-note"><strong>Observação:</strong> <?= av_e($feature['note']) ?></p><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </section>
    </div>
    <?php
    av_page_end();
}

function av_render_security(array $owner): void
{
    av_page_start('Segurança e LGPD', $owner, 'security');
    $active = ['HTTPS obrigatório, com cabeçalhos de segurança e política de conteúdo restritiva.', 'Instalador protegido por chave gravada fora da pasta pública.', 'Senha do titular com hash forte e bloqueio após tentativas erradas.', 'Proteção CSRF em todas as ações e consultas parametrizadas no banco.', 'Mídias fora da pasta pública, entregues só com sessão ou link válido.', 'Links temporários guardados como hash, com validade e revogação.', 'Nenhum recurso de terceiros (fontes, scripts ou rastreadores) é carregado.'];
    $planned = ['Criptografia das mídias em repouso.', 'Painel do titular: exportação, histórico e exclusão da conta.', 'Política de privacidade e registro das operações de tratamento.', 'Backup automático cifrado com teste de restauração.', 'Verificação em duas etapas no login.'];
    ?>
    <section class="narrow">
      <h1>Segurança e LGPD</h1>
      <p class="lede">O que já protege o cofre hoje e o que está sendo implementado. Nenhum item em desenvolvimento é apresentado como ativo.</p>
      <div class="sec-grid">
        <div class="panel sec-list">
          <span class="eyebrow">ATIVO NESTA VERSÃO</span>
          <ul><?php foreach ($active as $item): ?><li class="ok"><?= av_e($item) ?></li><?php endforeach; ?></ul>
        </div>
        <div class="panel sec-list">
          <span class="eyebrow eyebrow-amber">EM DESENVOLVIMENTO</span>
          <ul><?php foreach ($planned as $item): ?><li class="todo"><?= av_e($item) ?></li><?php endforeach; ?></ul>
        </div>
      </div>
      <p class="panel data-note">Hoje o AuraVault não criptografa as mídias em repouso. Use HTTPS, senha forte e mantenha cópias de segurança protegidas.</p>
    </section>
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
    <section class="viewer-heading narrow-wide">
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
