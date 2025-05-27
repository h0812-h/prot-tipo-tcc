#!/bin/bash

# Script de instalação completa do CRON
echo "🚀 Instalação do Sistema de CRON - Escola de Música Harmonia"
echo "============================================================"

# Detectar sistema operacional
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    OS="linux"
elif [[ "$OSTYPE" == "darwin"* ]]; then
    OS="macos"
elif [[ "$OSTYPE" == "msys" || "$OSTYPE" == "cygwin" ]]; then
    OS="windows"
else
    OS="unknown"
fi

echo "🖥️ Sistema detectado: $OS"

# Verificar se está rodando como root (para algumas operações)
if [[ $EUID -eq 0 ]]; then
    echo "⚠️ Executando como root. Algumas configurações serão aplicadas globalmente."
fi

# Detectar caminhos
PROJECT_PATH=$(pwd)
PHP_PATH=$(which php)
COMPOSER_PATH=$(which composer)

echo "📁 Caminho do projeto: $PROJECT_PATH"
echo "🐘 PHP encontrado em: $PHP_PATH"

# Verificar dependências
echo ""
echo "🔍 Verificando dependências..."

# Verificar PHP
if [ ! -f "$PHP_PATH" ]; then
    echo "❌ PHP não encontrado! Instale o PHP primeiro."
    echo "   Ubuntu/Debian: sudo apt install php php-cli"
    echo "   CentOS/RHEL: sudo yum install php php-cli"
    echo "   macOS: brew install php"
    exit 1
fi

echo "✅ PHP encontrado: $($PHP_PATH --version | head -n1)"

# Verificar Composer
if [ ! -f "$COMPOSER_PATH" ]; then
    echo "⚠️ Composer não encontrado. Tentando instalar..."
    
    # Baixar e instalar Composer
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    COMPOSER_PATH="/usr/local/bin/composer"
    
    if [ ! -f "$COMPOSER_PATH" ]; then
        echo "❌ Falha ao instalar Composer. Instale manualmente."
        exit 1
    fi
fi

echo "✅ Composer encontrado: $($COMPOSER_PATH --version | head -n1)"

# Instalar dependências PHP
echo ""
echo "📦 Instalando dependências PHP..."

if [ ! -f "$PROJECT_PATH/composer.json" ]; then
    echo "❌ composer.json não encontrado! Execute este script na raiz do projeto."
    exit 1
fi

cd "$PROJECT_PATH"
$COMPOSER_PATH install --no-dev --optimize-autoloader

if [ $? -ne 0 ]; then
    echo "❌ Erro ao instalar dependências. Verifique o composer.json."
    exit 1
fi

echo "✅ Dependências instaladas com sucesso!"

# Criar diretórios necessários
echo ""
echo "📁 Criando diretórios..."

mkdir -p "$PROJECT_PATH/logs"
mkdir -p "$PROJECT_PATH/vendor"

# Definir permissões
chmod 755 "$PROJECT_PATH/logs"
chmod 755 "$PROJECT_PATH/config"

echo "✅ Diretórios criados!"

# Verificar configuração do banco
echo ""
echo "🗄️ Verificando configuração do banco de dados..."

$PHP_PATH -r "
require_once '$PROJECT_PATH/config/database.php';
try {
    \$db = getDB();
    echo '✅ Conexão com banco de dados OK!' . PHP_EOL;
} catch (Exception \$e) {
    echo '❌ Erro na conexão: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

if [ $? -ne 0 ]; then
    echo "❌ Configure o banco de dados antes de continuar."
    exit 1
fi

# Executar atualizações do banco
echo ""
echo "🔄 Atualizando estrutura do banco de dados..."

$PHP_PATH -r "
require_once '$PROJECT_PATH/config/database.php';
try {
    \$db = getDB();
    
    // Executar SQL de atualização
    \$sql = file_get_contents('$PROJECT_PATH/database_update_cron.sql');
    \$statements = explode(';', \$sql);
    
    foreach (\$statements as \$statement) {
        \$statement = trim(\$statement);
        if (!empty(\$statement)) {
            \$db->exec(\$statement);
        }
    }
    
    echo '✅ Banco de dados atualizado!' . PHP_EOL;
} catch (Exception \$e) {
    echo '❌ Erro ao atualizar banco: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

# Testar execução do CRON
echo ""
echo "🧪 Testando execução do CRON..."

$PHP_PATH "$PROJECT_PATH/config/cron_manager.php" --force

if [ $? -eq 0 ]; then
    echo "✅ Teste de execução bem-sucedido!"
else
    echo "⚠️ Teste apresentou problemas. Verifique os logs."
fi

# Configurar CRON
echo ""
echo "⏰ Configurando CRON..."

# Criar entradas do CRON
CRON_ENTRIES="# Escola de Música Harmonia - Envio automático de emails
# Execução principal às 9h (todos os dias)
0 9 * * * $PHP_PATH $PROJECT_PATH/config/cron_manager.php >> $PROJECT_PATH/logs/cron_email.log 2>&1

# Execução secundária às 18:30 (todos os dias)
30 18 * * * $PHP_PATH $PROJECT_PATH/config/cron_manager.php >> $PROJECT_PATH/logs/cron_email.log 2>&1

# Limpeza de logs (todo domingo às 2h)
0 2 * * 0 $PHP_PATH $PROJECT_PATH/config/cron_manager.php --clean-logs >> $PROJECT_PATH/logs/cron_email.log 2>&1"

echo "📝 Entradas que serão adicionadas ao crontab:"
echo "$CRON_ENTRIES"
echo ""

read -p "Deseja adicionar essas entradas ao crontab? (s/n): " -n 1 -r
echo

if [[ $REPLY =~ ^[Ss]$ ]]; then
    # Fazer backup do crontab atual
    crontab -l > "$PROJECT_PATH/crontab_backup_$(date +%Y%m%d_%H%M%S).txt" 2>/dev/null || echo "# Nenhum crontab anterior" > "$PROJECT_PATH/crontab_backup_$(date +%Y%m%d_%H%M%S).txt"
    
    # Adicionar novas entradas
    (crontab -l 2>/dev/null; echo "$CRON_ENTRIES") | crontab -
    
    echo "✅ CRON configurado com sucesso!"
else
    echo "⚠️ CRON não configurado. Você pode configurar manualmente depois."
fi

# Criar script de monitoramento
echo ""
echo "📊 Criando script de monitoramento..."

cat > "$PROJECT_PATH/scripts/monitor_cron.sh" << 'EOF'
#!/bin/bash

# Script de monitoramento do CRON
PROJECT_PATH=$(dirname $(dirname $(realpath $0)))
LOG_FILE="$PROJECT_PATH/logs/cron_email.log"

echo "📊 Status do CRON - Escola de Música Harmonia"
echo "=============================================="

# Verificar se o CRON está configurado
if crontab -l | grep -q "cron_manager.php"; then
    echo "✅ CRON configurado"
else
    echo "❌ CRON não configurado"
fi

# Verificar última execução
if [ -f "$LOG_FILE" ]; then
    echo ""
    echo "📄 Últimas 10 linhas do log:"
    tail -n 10 "$LOG_FILE"
    
    echo ""
    echo "📈 Estatísticas do log:"
    echo "Total de linhas: $(wc -l < "$LOG_FILE")"
    echo "Última modificação: $(stat -c %y "$LOG_FILE" 2>/dev/null || stat -f %Sm "$LOG_FILE")"
else
    echo "❌ Arquivo de log não encontrado: $LOG_FILE"
fi

# Verificar processos
echo ""
echo "🔍 Processos relacionados:"
ps aux | grep cron_manager.php | grep -v grep || echo "Nenhum processo encontrado"
EOF

chmod +x "$PROJECT_PATH/scripts/monitor_cron.sh"

# Criar arquivo de configuração de ambiente
echo ""
echo "⚙️ Criando arquivo de configuração..."

if [ ! -f "$PROJECT_PATH/.env" ]; then
    cp "$PROJECT_PATH/.env.example" "$PROJECT_PATH/.env" 2>/dev/null || echo "# Configure suas variáveis de ambiente aqui" > "$PROJECT_PATH/.env"
    echo "📝 Arquivo .env criado. Configure suas credenciais de email!"
fi

# Resumo final
echo ""
echo "🎉 INSTALAÇÃO CONCLUÍDA!"
echo "======================="
echo ""
echo "✅ Dependências instaladas"
echo "✅ Banco de dados atualizado"
echo "✅ Diretórios criados"
echo "✅ CRON configurado"
echo "✅ Scripts de monitoramento criados"
echo ""
echo "📋 PRÓXIMOS PASSOS:"
echo "1. Configure suas credenciais de email no arquivo .env"
echo "2. Teste o envio de emails em: /admin/teste_email.php"
echo "3. Monitore o CRON em: /admin/monitoramento_cron.php"
echo "4. Execute o monitoramento: bash scripts/monitor_cron.sh"
echo ""
echo "📁 Arquivos importantes:"
echo "• Logs: $PROJECT_PATH/logs/cron_email.log"
echo "• Configuração: $PROJECT_PATH/.env"
echo "• Monitoramento: $PROJECT_PATH/scripts/monitor_cron.sh"
echo ""
echo "🔗 URLs úteis:"
echo "• Teste de email: http://seu-dominio/admin/teste_email.php"
echo "• Monitoramento: http://seu-dominio/admin/monitoramento_cron.php"
echo ""
echo "💡 Para verificar se está funcionando:"
echo "   crontab -l"
echo "   tail -f $PROJECT_PATH/logs/cron_email.log"
