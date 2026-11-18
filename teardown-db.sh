#!/bin/bash

CONTAINER_NAME="mysql-local"
VOLUME_NAME="mysql_dados_locais"

echo "=========================================="
echo "   FINALIZAÇÃO DO AMBIENTE DE ESTUDOS     "
echo "=========================================="
echo "Escolha uma opção:"
echo "1) Apenas PARAR o container (mantém tabelas e dados salvos)"
echo "2) REMOVER container e APAGAR todos os dados (limpeza total)"
echo "3) Cancelar"
read -p "Opção [1-3]: " OPCAO

case $OPCAO in
    1)
        if [ "$(docker ps -q -f name=^/${CONTAINER_NAME}$)" ]; then
            docker stop $CONTAINER_NAME
            echo "Container '$CONTAINER_NAME' parado com sucesso. Seus dados continuam salvos no volume '$VOLUME_NAME'."
        else
            echo "O container '$CONTAINER_NAME' já está parado."
        fi
        ;;
    2)
        if [ "$(docker ps -aq -f name=^/${CONTAINER_NAME}$)" ]; then
            docker rm -f $CONTAINER_NAME
            echo "Container '$CONTAINER_NAME' removido."
        fi
        if [ "$(docker volume ls -q -f name=^${VOLUME_NAME}$)" ]; then
            docker volume rm $VOLUME_NAME
            echo "Volume '$VOLUME_NAME' apagado com sucesso."
        fi
        echo "Limpeza completa concluída!"
        ;;
    *)
        echo "Operação cancelada. Nada foi alterado."
        ;;
esac
