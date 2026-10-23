# Changelog

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
