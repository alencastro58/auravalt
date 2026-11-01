# Changelog

## v0.2.1

Segurança
- Bloqueio de login no servidor (tabela `login_attempts`, por IP+e-mail, por IP e por e-mail, janela de 15 min). Não depende mais do cookie de sessão, então descartar o cookie não zera as tentativas. A senha é verificada em tempo semelhante exista ou não o e-mail.
- HSTS (`Strict-Transport-Security`) em respostas HTTPS e `Cross-Origin-Resource-Policy: same-origin`.
- Troca de senha pelo titular (página **Conta**) e validação de qualidade de senha também no instalador.
- Links públicos (`shared` e `file&token`) não iniciam sessão nem enviam cookie.
- Links vencidos ou revogados há mais de 30 dias são removidos no login.
- Instalador compara o host com `SERVER_NAME` (não com o cabeçalho `Host`).

Desempenho
- Miniaturas JPEG geradas no servidor (GD, 480 px, sem EXIF), guardadas em `auravault_private/thumbs`, com `ETag`/304 e cache privado de 24 h. A biblioteca deixou de baixar os arquivos originais; vídeos não são mais pré-carregados nos cards.
- CSS/JS com cache de 30 dias (versionados por `?v=`) e compressão gzip.

Banco e operação
- Migrações numeradas (`schema_migrations`); v1 = esquema original, v2 = `login_attempts`. Aplicadas automaticamente, de forma idempotente, em bancos já instalados.
- Conexão MySQL fixa em UTC (`SET time_zone = '+00:00'`); SQLite dos testes liga `foreign_keys`.
- `.user.ini` com limites de envio (100 MB); a tela de envio mostra o limite efetivo.
- Log com ID de requisição (`av_log`); `AURAVAULT_PRIVATE_DIR` permite definir a pasta privada por variável de ambiente.
- Página **Segurança e LGPD** mostra diagnóstico do servidor (PHP, limite de envio, GD, HTTPS, versão).
- `scripts/backup-hostgator.sh` (backup com retenção e cifragem opcional) e `verify-production.ps1` ampliado (HSTS, CSP, `.user.ini`, `install.key`).
- GitHub Actions (`.github/workflows/ci.yml`): sintaxe, testes de fumaça e de integração HTTP em PHP 8.1, 8.3 e 8.4.

Correções
- Rótulo "MÍDIA COMPARTILHADA/PRIVADA" passou a depender do acesso por link, não do tipo de mídia.
- Exclusão sem transação inútil, removendo também a miniatura; `SELECT *` substituído por colunas explícitas; título do painel escapado.
- `Content-Disposition` com nome ASCII de reserva além do `filename*` UTF-8.
- Acessibilidade: link "Ir para o conteúdo", botão de copiar com `aria-live`.
- Testes: smoke ampliado e novo `tests/http.php` (login, bloqueio, CSRF, upload, XSS, miniatura, Range, links, revogação, senha, exclusão). `build-deploy.ps1` valida todos os `.php` e roda os dois testes.

## v0.2.0

- Nova interface escura: Dashboard de Mídias com painel lateral, miniaturas, faixa de link ativo e ações por card.
- Busca por nome, filtro por tipo (fotos/vídeos) e ordenação (recentes, antigos, tamanho, nome), com paginação que preserva os filtros.
- Contadores de mídias, links ativos e espaço usado.
- Novas páginas para o titular: "Recursos e como usar" (`?action=guide`) e "Segurança e LGPD" (`?action=security`), separando recursos disponíveis dos em desenvolvimento.
- Botão "Copiar link" ao criar link temporário.
- Nenhuma fonte ou script de terceiros: a política de conteúdo continua restrita a `self`.
- Testes de fumaça cobrem filtros, busca com curingas literais e estatísticas.

## v0.1.1

- Segurança (C1): instalador exige chave `install.key` gravada fora do document root, bloqueia após 5 tentativas inválidas por 15 minutos e apaga a chave ao concluir. Sem a chave, exibe "Instalação bloqueada" (HTTP 403).
- Segurança (C1): impede que duas instalações simultâneas gravem `config.php`.
- Segurança (C2): repositório unificado com `.htaccess`, `.gitignore` e `.gitattributes`; pacote de deploy gerado por `git archive` a partir de tag.
- `.htaccess` bloqueia dotfiles, Markdown, chaves, backups e as pastas `scripts/` e `dist/`.
- Nome e domínio corrigidos para AuraVault / auravault.com.br; pasta privada renomeada para `auravault_private`.
- Testes de fumaça cobrem leitura da chave e bloqueio por tentativas.

## v0.1.0

- Versão inicial (publicada no GitHub como "AuraValt").
