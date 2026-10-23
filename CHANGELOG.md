# Changelog

## v0.1.1

- Segurança (C1): instalador exige chave `install.key` gravada fora do document root, bloqueia após 5 tentativas inválidas por 15 minutos e apaga a chave ao concluir. Sem a chave, exibe "Instalação bloqueada" (HTTP 403).
- Segurança (C1): impede que duas instalações simultâneas gravem `config.php`.
- Segurança (C2): repositório unificado com `.htaccess`, `.gitignore` e `.gitattributes`; pacote de deploy gerado por `git archive` a partir de tag.
- `.htaccess` bloqueia dotfiles, Markdown, chaves, backups e as pastas `scripts/` e `dist/`.
- Nome e domínio corrigidos para AuraVault / auravault.com.br; pasta privada renomeada para `auravault_private`.
- Testes de fumaça cobrem leitura da chave e bloqueio por tentativas.

## v0.1.0

- Versão inicial (publicada no GitHub como "AuraValt").
