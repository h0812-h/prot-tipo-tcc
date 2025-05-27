#!/bin/bash

# Script para configurar CRON automaticamente
# Execute com: bash scripts/setup_cron.sh

echo "🚀 Configurando CRON para envio automático de emails..."

# Detectar o caminho atual do projeto
PROJECT_PATH=$(pwd)
PHP_PATH=$(which php)

echo "📁 Caminho do projeto: $PROJECT_PATH"
echo "🐘 Caminho do PHP: $PHP_PATH"

# Verificar se o PHP existe
if [ ! -f "$PHP_PATH" ]; then
    echo "❌ PHP não encontrado! Instale o PHP primeiro."
    exit 1
fi

# Verificar se o arquivo de CRON existe
CRON_FILE="$PROJECT_PATH/config/email_cron.php"
if [ ! -f "$CRON_FILE" ]; then
    echo "❌ Arquivo email_cron.php não encontrado em: $CRON_FILE"
    exit 1
fi

# Criar backup do crontab atual
echo "💾 Fazendo backup do crontab atual..."
crontab -l > crontab_backup_$(date +%Y%m%d_%H%M%S).txt 2>/dev/null || echo "Nenhum crontab existente encontrado."

# Criar entrada do CRON
CRON_ENTRY="# Escola de Música Harmonia - Envio automático de emails
0 9 * * * $PHP_PATH $CRON_FILE >> $PROJECT_PATH/logs/cron_email.log 2>&1
30 18 * * * $PHP_PATH $CRON_FILE >> $PROJECT_PATH/logs/cron_email.log 2>&1"

echo "📝 Entrada do CRON que será adicionada:"
echo "$CRON_ENTRY"
echo ""

# Perguntar confirmação
read -p "Deseja adicionar essas entradas ao crontab? (s/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Ss]$ ]]; then
    # Criar diretório de logs se não existir
    mkdir -p "$PROJECT_PATH/logs"
    
    # Adicionar ao crontab
    (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
    
    echo "✅ CRON configurado com sucesso!"
    echo "📊 Logs serão salvos em: $PROJECT_PATH/logs/cron_email.log"
    echo ""
    echo "📋 Horários configurados:"
    echo "  • 09:00 - Envio principal (lembretes, vencimentos, atrasos)"
    echo "  • 18:30 - Envio secundário (verificação adicional)"
    echo ""
    echo "🔍 Para verificar se foi adicionado, execute: crontab -l"
else
    echo "❌ Configuração cancelada."
fi
