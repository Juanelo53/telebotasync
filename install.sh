#!/bin/bash

# ══════════════════════════════════════════════════════════════
#  TeleBot Async - Instalador automático
#
#  Restaura TODO desde cero: código, config, webhook, deploy.
#
#  Uso:
#    bash install.sh
#    bash install.sh --restore backup_telebot_XXXXX.tar.gz
# ══════════════════════════════════════════════════════════════

set -e

# ── Configuración ─────────────────────────────────────────────
REPO="https://github.com/Juanelo53/telebotasync.git"
REPO_SSH="git@github.com-telebot:Juanelo53/telebotasync.git"
BOT_TOKEN="8271269735:AAEihXMCzMGRDdzGRA3E5i3m0JoJUBbRTh8"
BOT_SECRET="telebot_$(echo -n '8271269735' | md5sum | cut -d' ' -f1)"
WEBHOOK_URL="https://juaneloserver.com/telebot/webhook.php"
DEPLOY_SECRET="37a8a27fc2416ed07dfc62ffd6f6c1da3ef8eb4d"
INSTALL_DIR="/home/juanelo/public_html/telebot"
SSH_KEY_DIR="/home/juanelo/.ssh"
OWNER="juanelo:juanelo"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
fail() { echo -e "${RED}[✗]${NC} $1"; exit 1; }

echo ""
echo "══════════════════════════════════════════════"
echo "  TeleBot Async - Instalador"
echo "══════════════════════════════════════════════"
echo ""

# ── Modo restaurar desde backup ───────────────────────────────
if [[ "$1" == "--restore" && -n "$2" ]]; then
    BACKUP="$2"
    [[ -f "$BACKUP" ]] || fail "Backup no encontrado: $BACKUP"

    log "Restaurando desde backup: $BACKUP"

    mkdir -p "$INSTALL_DIR"
    tar -xzf "$BACKUP" -C "$INSTALL_DIR" --strip-components=1 2>/dev/null || \
    tar -xzf "$BACKUP" -C "$INSTALL_DIR" 2>/dev/null

    # Restaurar SSH keys si están en el backup
    if [[ -f "$INSTALL_DIR/.backup/telebot_deploy" ]]; then
        mkdir -p "$SSH_KEY_DIR"
        cp "$INSTALL_DIR/.backup/telebot_deploy" "$SSH_KEY_DIR/"
        cp "$INSTALL_DIR/.backup/telebot_deploy.pub" "$SSH_KEY_DIR/"
        chmod 600 "$SSH_KEY_DIR/telebot_deploy"
        chmod 644 "$SSH_KEY_DIR/telebot_deploy.pub"

        cat > "$SSH_KEY_DIR/config" <<'SSHEOF'
Host github.com-telebot
    HostName github.com
    User git
    IdentityFile /home/juanelo/.ssh/telebot_deploy
    IdentitiesOnly yes
    StrictHostKeyChecking no
SSHEOF
        chmod 600 "$SSH_KEY_DIR/config"
        chown -R $OWNER "$SSH_KEY_DIR"
        log "SSH deploy key restaurada"
    fi

    rm -rf "$INSTALL_DIR/.backup"
    chown -R $OWNER "$INSTALL_DIR"
    log "Archivos restaurados"

    # Configurar git remote
    cd "$INSTALL_DIR"
    if [[ -d .git ]]; then
        git remote set-url origin "$REPO_SSH" 2>/dev/null || true
    else
        git init
        git remote add origin "$REPO_SSH"
        git fetch origin main
        git reset --hard origin/main
    fi
    log "Git configurado"

    # Configurar webhook
    PHP_BIN=$(which php 2>/dev/null || echo "/usr/local/bin/php")
    $PHP_BIN -r "
        require_once '$INSTALL_DIR/autoload.php';
        \$config = require '$INSTALL_DIR/config.php';
        \$api = new TeleBot\Api(\$config['token']);
        \$opt = [];
        if (!empty(\$config['secret_token'])) \$opt['secret_token'] = \$config['secret_token'];
        \$r = \$api->setWebhook('$WEBHOOK_URL', \$opt);
        echo (\$r['ok'] ?? false) ? 'Webhook OK' : 'Webhook FAIL: ' . json_encode(\$r);
        echo PHP_EOL;
    "
    log "Webhook configurado"

    chown -R $OWNER "$INSTALL_DIR"
    log "Restauración completa!"
    exit 0
fi

# ── Instalación desde cero ────────────────────────────────────

# 1. Clonar repo
if [[ -d "$INSTALL_DIR/.git" ]]; then
    warn "Ya existe un repo en $INSTALL_DIR"
    cd "$INSTALL_DIR"
    git pull origin main 2>/dev/null || true
else
    log "Clonando repositorio..."
    git clone "$REPO" "$INSTALL_DIR" 2>/dev/null || fail "No se pudo clonar"
    cd "$INSTALL_DIR"
fi
log "Código instalado"

# 2. Crear config.php
if [[ ! -f "$INSTALL_DIR/config.php" ]]; then
    cat > "$INSTALL_DIR/config.php" <<PHPEOF
<?php

return [
    'token' => getenv('TELEBOT_TOKEN') ?: '$BOT_TOKEN',
    'secret_token' => getenv('TELEBOT_SECRET') ?: '$BOT_SECRET',
    'webhook_url' => '$WEBHOOK_URL',
    'command_prefixes' => ['/', '.', '!', '@', '\$', '#'],
    'max_async_tasks' => 10,
    'debug' => false,
    'admin_ids' => [],
];
PHPEOF
    log "config.php creado"
else
    warn "config.php ya existe, no se tocó"
fi

# 3. Crear directorios
mkdir -p "$INSTALL_DIR/logs" "$INSTALL_DIR/storage/tasks"
log "Directorios creados"

# 4. SSH deploy key
if [[ ! -f "$SSH_KEY_DIR/telebot_deploy" ]]; then
    mkdir -p "$SSH_KEY_DIR"
    ssh-keygen -t ed25519 -C "deploy@$(hostname)" -f "$SSH_KEY_DIR/telebot_deploy" -N "" -q
    cat > "$SSH_KEY_DIR/config" <<'SSHEOF'
Host github.com-telebot
    HostName github.com
    User git
    IdentityFile /home/juanelo/.ssh/telebot_deploy
    IdentitiesOnly yes
    StrictHostKeyChecking no
SSHEOF
    chmod 600 "$SSH_KEY_DIR/config" "$SSH_KEY_DIR/telebot_deploy"
    chown -R $OWNER "$SSH_KEY_DIR"
    log "SSH deploy key generada"
    echo ""
    warn "IMPORTANTE: Agrega esta key como Deploy Key en GitHub:"
    warn "https://github.com/Juanelo53/telebotasync/settings/keys/new"
    echo ""
    cat "$SSH_KEY_DIR/telebot_deploy.pub"
    echo ""
else
    log "SSH deploy key ya existe"
fi

# 5. Cambiar remote a SSH
cd "$INSTALL_DIR"
git remote set-url origin "$REPO_SSH" 2>/dev/null || true
log "Remote configurado con SSH"

# 6. Configurar webhook de Telegram
PHP_BIN=$(which php 2>/dev/null || echo "/usr/local/bin/php")
log "Configurando webhook de Telegram..."
$PHP_BIN -r "
    require_once '$INSTALL_DIR/autoload.php';
    \$config = require '$INSTALL_DIR/config.php';
    \$api = new TeleBot\Api(\$config['token']);
    \$opt = [];
    if (!empty(\$config['secret_token'])) \$opt['secret_token'] = \$config['secret_token'];
    \$r = \$api->setWebhook('$WEBHOOK_URL', \$opt);
    echo (\$r['ok'] ?? false) ? 'Webhook configurado OK' : 'ERROR: ' . json_encode(\$r);
    echo PHP_EOL;
    \$me = \$api->getMe();
    if (\$me['ok'] ?? false) echo 'Bot: @' . \$me['result']['username'] . PHP_EOL;
"

# 7. Permisos
chown -R $OWNER "$INSTALL_DIR"
chmod 770 "$INSTALL_DIR/logs" "$INSTALL_DIR/storage" "$INSTALL_DIR/storage/tasks"
log "Permisos configurados"

echo ""
log "Instalación completa!"
echo ""
echo "  Comandos útiles:"
echo "    php setup.php test       - Probar conexión"
echo "    php setup.php info       - Ver info del webhook"
echo "    php setup.php commands   - Registrar comandos en BotFather"
echo ""
