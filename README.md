# AuraVault

Cofre privado de fotos e vídeos em PHP (8.3 ou superior recomendado; mínimo técnico 8.1), para uma conta titular, publicado em https://www.auravault.com.br (HostGator, cPanel).

## Fonte única de verdade

Este repositório Git é a única origem do código. Não edite arquivos diretamente no servidor nem mantenha cópias paralelas. Toda publicação sai de uma tag.

## Estrutura

- `index.php`: roteamento e instalador protegido por chave.
- `app/`: inicialização e migrações (`bootstrap.php`), autenticação e bloqueio de login (`auth.php`), mídia e miniaturas (`media.php`) e telas (`views.php`). Bloqueado ao público.
- `assets/`: CSS e JS.
- `.user.ini`: limites de envio do PHP.
- `tests/`: `smoke.php` (unidade) e `http.php` (integração com servidor embutido). Não vão para produção.
- `scripts/`: geração do pacote, verificação da produção e backup (não vão para produção).
- `docs/DEPLOY_HOSTGATOR.md`: guia de publicação.

## Rodar localmente

Na pasta do repositório (a configuração privada fica em `AURAVAULT_PRIVATE_DIR`, se definida):

```
php -S 127.0.0.1:8080
```

## Testar

```
php tests/smoke.php
php tests/http.php
```

## Publicar

1. `git status` limpo e testes passando.
2. Registre a versão em `CHANGELOG.md` e em `AV_VERSION` (`app/bootstrap.php`), faça o commit e crie a tag: `git tag -a v0.2.1 -m "v0.2.1"` e `git push --follow-tags`.
3. Gere o pacote: `powershell -ExecutionPolicy Bypass -File scripts/build-deploy.ps1 -Tag v0.2.1`. O ZIP fica em `dist/`, sem testes, docs e scripts.
4. Siga [docs/DEPLOY_HOSTGATOR.md](docs/DEPLOY_HOSTGATOR.md), fazendo o backup antes.
5. Verifique: `powershell -ExecutionPolicy Bypass -File scripts/verify-production.ps1`.
