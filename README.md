# AuraVault

Cofre privado de fotos e vídeos para uma conta titular, com compartilhamento por link temporário e revogável.

## Requisitos

- PHP 8.1 ou superior.
- PDO MySQL, `fileinfo`, OpenSSL e sessões PHP.
- MySQL/MariaDB com permissão para criar tabelas no banco escolhido.
- Apache com `.htaccess`/`mod_rewrite` na hospedagem HostGator.
- HTTPS ativo no domínio antes de instalar.

## Desenvolvimento local

No terminal, na pasta que contém `AuraVault/`, execute:

```powershell
php -S 127.0.0.1:8080 -t AuraVault
```

Abra `http://127.0.0.1:8080`. O instalador local permite HTTP somente em `localhost`/`127.0.0.1`; para produção, use HTTPS.

Teste o schema SQLite e a validação dos links:

```powershell
php AuraVault/tests/smoke.php
```

## Publicação

Siga o passo a passo em [docs/DEPLOY_HOSTGATOR.md](docs/DEPLOY_HOSTGATOR.md). O ZIP `AuraVault-HostGator.zip` contém os arquivos prontos para a raiz do domínio e o instalador cria o schema no primeiro acesso.

## Privacidade

- Não há registro público nem acesso público à biblioteca.
- Mídias são guardadas fora do document root quando o diretório da conta permite.
- Links compartilham somente uma mídia, expiram em 1, 7 ou 30 dias e podem ser revogados.
- Tokens são aleatórios e armazenados no banco somente como hash.
- O pacote não fornece criptografia de arquivos em repouso, antivírus, recuperação por e-mail ou armazenamento redundante.
- Arquivos e backups continuam sob a responsabilidade da conta HostGator.
