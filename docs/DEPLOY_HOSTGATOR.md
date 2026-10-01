# Publicar AuraValt na HostGator

Este pacote é para hospedagem compartilhada HostGator com cPanel, Apache, PHP 8.1 ou superior, PDO MySQL, `fileinfo` e `mod_rewrite`. O instalador usa um banco MySQL vazio e cria a primeira conta titular. Não é necessário Composer nem Node.js no servidor.

## Antes de enviar o ZIP

1. No cPanel, confirme que `www.auravalt.com.br` aponta para a hospedagem contratada.
2. Ative o certificado SSL/AutoSSL para `auravalt.com.br` e `www.auravalt.com.br`. Aguarde o HTTPS abrir sem aviso no navegador. O arquivo `.htaccess` redireciona o domínio para `https://www.auravalt.com.br`.
3. No cPanel, abra **Assistente de banco de dados MySQL** (ou **Bancos de dados MySQL**). Crie um banco e um usuário com senha forte; conceda ao usuário todos os privilégios nesse banco. Anote o nome completo do banco e do usuário, inclusive o prefixo da conta cPanel.
4. Confirme no cPanel que a versão selecionada do PHP é 8.1 ou superior e que `PDO MySQL`, `fileinfo` e sessões estão disponíveis.

## Enviar e extrair

1. Baixe `AuraValt-HostGator.zip` para o computador.
2. No cPanel, abra **Gerenciador de arquivos** e entre em `public_html`. Se AuraValt for instalado em um domínio adicional, entre na pasta-raiz atribuída àquele domínio.
3. Se já existe um site nesse diretório, faça backup e use uma pasta/subdomínio dedicado. O pacote define `index.php` como página inicial preferida.
4. Clique em **Carregar**, envie o ZIP e volte ao Gerenciador de arquivos.
5. Selecione o ZIP e clique em **Extrair** no diretório-raiz do domínio. Confira que `index.php`, `.htaccess`, `app/`, `assets/` e `docs/` ficaram diretamente na raiz, sem uma pasta extra `AuraValt/` envolvendo tudo.
6. Abra `https://www.auravalt.com.br`. O instalador aparece automaticamente enquanto ainda não houver configuração.

## Instalação inicial

1. Informe o servidor MySQL indicado pela HostGator (normalmente `localhost`), o nome completo do banco, o usuário completo e a senha criados anteriormente.
2. Crie o e-mail e a senha da conta titular do AuraValt. Use pelo menos 12 caracteres e não reutilize a senha do cPanel.
3. Deixe a URL como `https://www.auravalt.com.br` e clique em **Instalar AuraValt**.
4. Após a confirmação, entre com a conta titular. Não existe cadastro público de usuários.
5. O instalador cria `auravalt_private` fora do document root, com `config.php` e `media/`; a senha do banco não fica dentro do ZIP nem em arquivo público. Se a HostGator impedir a criação dessa pasta, não coloque os arquivos em uma pasta pública alternativa: abra um chamado para confirmar permissões PHP no diretório-pai de `public_html`.

## Limite de upload

O aplicativo aceita arquivos de até 100 MB cada. A hospedagem também precisa aceitar esse tamanho. Se o cPanel permitir, abra **MultiPHP INI Editor** e ajuste valores compatíveis com o plano, por exemplo `upload_max_filesize = 100M`, `post_max_size = 110M`, `max_execution_time = 300` e `max_input_time = 300`. Os limites disponíveis variam por plano; não tente contorná-los. O upload informa quando o PHP ou o provedor rejeita um arquivo.

Formatos aceitos: JPEG, PNG, GIF, WebP, MP4, WebM e MOV/QuickTime. Arquivos com ícone de nuvem do OneDrive precisam estar baixados/sincronizados antes do upload.

## Teste de publicação

- Envie uma foto e um vídeo pequenos; abra ambos na biblioteca e teste o download.
- Crie um link com validade de 1 dia; teste em uma janela anônima e confirme que somente a mídia escolhida fica acessível.
- Revogue o link e confirme que ele passa a exibir “link não disponível”.
- Confira que HTTP redireciona para HTTPS e que `/app/` e `/docs/` não exibem arquivos.

## Privacidade e cópias de segurança

Por padrão, a biblioteca exige a conta titular. Links de compartilhamento são credenciais: qualquer pessoa que os receba poderá ver aquela mídia até a expiração ou revogação. O token completo é mostrado somente quando o link é criado; salve-o nesse momento se necessário.

Os arquivos ficam fora da pasta pública, mas **não são criptografados pelo AuraValt em repouso**. O HTTPS protege o tráfego, não o armazenamento no servidor nem as cópias de segurança. Não use este pacote para conteúdo cuja exposição seria irreversível sem antes avaliar criptografia, política de privacidade, retenção e obrigações legais. Faça backup do banco MySQL e da pasta privada `auravalt_private/media` em local protegido; não coloque backups em `public_html`.

## Atualizações e recuperação

Antes de substituir os arquivos, faça backup do banco e de `auravalt_private`. Preserve o `config.php` privado. Nunca envie esse arquivo por e-mail nem o inclua em outro ZIP público. Se esquecer a senha do titular, o pacote inicial não inclui recuperação por e-mail; altere o hash da senha diretamente no banco por procedimento administrativo seguro ou restaure uma cópia confiável.
