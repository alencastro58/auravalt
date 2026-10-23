# AuraVault

Cofre privado de fotos e vídeos em PHP 8.1, para uma conta titular, publicado em https://www.auravault.com.br (HostGator, cPanel).

## Fonte única de verdade

Este repositório Git é a única origem do código. Não edite arquivos diretamente no servidor nem mantenha cópias paralelas. Toda publicação sai de uma tag.

## Estrutura

- `index.php`: roteamento e instalador protegido por chave.
- `app/`: inicialização, mídia e telas (bloqueado ao público).
- `assets/`: CSS e JS.
- `tests/smoke.php`: teste de fumaça (não vai para produção).
- `scripts/`: geração do pacote e verificação da produção (não vão para produção).
- `docs/DEPLOY_HOSTGATOR.md`: guia de publicação.

## Rodar localmente

Na pasta que contém `AuraVault/`:

```
php -S 127.0.0.1:8080 -t AuraVault
```

## Testar

```
php AuraVault/tests/smoke.php
```

## Publicar

1. `git status` limpo e testes passando.
2. Registre a versão em `CHANGELOG.md`, faça o commit e crie a tag: `git tag -a v0.1.1 -m "v0.1.1"` e `git push --follow-tags`.
3. Gere o pacote: `powershell -ExecutionPolicy Bypass -File scripts/build-deploy.ps1 -Tag v0.1.1`. O ZIP fica em `dist/`, sem testes, docs e scripts.
4. Siga [docs/DEPLOY_HOSTGATOR.md](docs/DEPLOY_HOSTGATOR.md).
5. Verifique: `powershell -ExecutionPolicy Bypass -File scripts/verify-production.ps1`.
