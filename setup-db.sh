#!/bin/bash

# Configurações do Banco de Dados
CONTAINER_NAME="mysql-local"
DB_ROOT_PASSWORD="root_password_123"
DB_NAME="meu_sistema"
DB_USER="dev_user"
DB_PASSWORD="dev_password_123"
PORT="3306"

echo "Iniciando configuração do MySQL no Docker..."

# Remove container anterior se existir (mantendo o volume de dados)
if [ "$(docker ps -aq -f name=^/${CONTAINER_NAME}$)" ]; then
    echo "Container antigo encontrado. Recriando..."
    docker rm -f $CONTAINER_NAME
fi

# Sobe o MySQL 8.0 anunciando mysql_native_password no handshake para PHP 5.6
docker run -d \
    --name $CONTAINER_NAME \
    --restart unless-stopped \
    -e MYSQL_ROOT_PASSWORD=$DB_ROOT_PASSWORD \
    -e MYSQL_DATABASE=$DB_NAME \
    -e MYSQL_USER=$DB_USER \
    -e MYSQL_PASSWORD=$DB_PASSWORD \
    -p $PORT:3306 \
    -v mysql_dados_locais:/var/lib/mysql \
    mysql:8.0 \
    --default-authentication-plugin=mysql_native_password

echo "Aguardando o MySQL ficar pronto para conexões..."
until docker exec $CONTAINER_NAME mysqladmin ping -h "127.0.0.1" -u root -p$DB_ROOT_PASSWORD --silent >/dev/null 2>&1; do
    printf "."
    sleep 2
done
echo ""

echo "Banco de dados '$DB_NAME' pronto e compatível com PHP 5.6 na porta $PORT!"