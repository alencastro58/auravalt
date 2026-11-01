#!/usr/bin/env bash
# Backup do AuraVault para hospedagem cPanel/HostGator (rodar por SSH ou pelo Cron do cPanel).
# Gera um arquivo .tar.gz (banco + mídias + configuração) FORA de public_html e mantém os N mais recentes.
#
# Uso:   bash backup-hostgator.sh
# Cron:  0 3 * * * /bin/bash $HOME/auravault_tools/backup-hostgator.sh >> $HOME/auravault_backups/backup.log 2>&1
#
# Variáveis opcionais:
#   AURAVAULT_PRIVATE_DIR   pasta privada (padrão: $HOME/auravault_private)
#   AURAVAULT_BACKUP_DIR    destino (padrão: $HOME/auravault_backups)
#   AURAVAULT_BACKUP_KEEP   quantidade de backups mantidos (padrão: 14)
#   AURAVAULT_BACKUP_PASSPHRASE  se definida, cifra o arquivo com AES-256 (openssl)
set -euo pipefail
umask 077

PRIVATE_DIR="${AURAVAULT_PRIVATE_DIR:-$HOME/auravault_private}"
BACKUP_DIR="${AURAVAULT_BACKUP_DIR:-$HOME/auravault_backups}"
KEEP="${AURAVAULT_BACKUP_KEEP:-14}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

[ -f "$PRIVATE_DIR/config.php" ] || { echo "config.php não encontrado em $PRIVATE_DIR" >&2; exit 1; }
mkdir -p "$BACKUP_DIR"

cfg() { php -r '$c = require $argv[1]; echo $c["db"][$argv[2]] ?? "";' "$PRIVATE_DIR/config.php" "$1"; }
DB_HOST="$(cfg host)"; DB_NAME="$(cfg name)"; DB_USER="$(cfg user)"; DB_PASS="$(cfg password)"

MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --no-tablespaces -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" > "$WORK/database.sql"
[ -s "$WORK/database.sql" ] || { echo "Dump do banco vazio" >&2; exit 1; }

ARCHIVE="$BACKUP_DIR/auravault-$STAMP.tar.gz"
tar -czf "$ARCHIVE" -C "$WORK" database.sql -C "$(dirname "$PRIVATE_DIR")" "$(basename "$PRIVATE_DIR")" \
    --exclude="$(basename "$PRIVATE_DIR")/thumbs" --exclude="$(basename "$PRIVATE_DIR")/install.key"

if [ -n "${AURAVAULT_BACKUP_PASSPHRASE:-}" ]; then
    openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:AURAVAULT_BACKUP_PASSPHRASE -in "$ARCHIVE" -out "$ARCHIVE.enc"
    rm -f "$ARCHIVE"
    ARCHIVE="$ARCHIVE.enc"
fi

# Retenção: remove os mais antigos além de $KEEP
ls -1t "$BACKUP_DIR"/auravault-*.tar.gz* 2>/dev/null | tail -n +"$((KEEP + 1))" | xargs -r rm -f --

echo "$(date -u +%FT%TZ) backup ok: $ARCHIVE ($(du -h "$ARCHIVE" | cut -f1))"
