#!/bin/bash
# Servidor PHP com limites aumentados para importação de arquivos grandes
# Usa o servidor PHP embutido diretamente (não artisan serve)

cd "$(dirname "$0")"

echo "Iniciando servidor com limites de upload aumentados..."
echo "  upload_max_filesize: 500M"
echo "  post_max_size: 512M"
echo "  memory_limit: 512M"
echo ""
echo "Servidor rodando em http://0.0.0.0:8000"
echo ""

php -c php-server.ini -S 0.0.0.0:8000 -t public
