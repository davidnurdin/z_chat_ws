#!/bin/bash
# RunCommandInDev.sh
# Exécute une commande dans le conteneur en cours nommé "chatwsdev"

# Récupère l'ID du conteneur en cours d'exécution portant "chatwsdev"
CONTAINER_ID=$(docker ps --filter "name=chatwsdev" --format "{{.ID}}")

if [ -z "$CONTAINER_ID" ]; then
    echo "Erreur : aucun conteneur en cours trouvé avec le nom 'chatwsdev'."
    exit 1
fi

# Exécute la commande passée en argument dans le conteneur
docker exec -it "$CONTAINER_ID" bash -c "$*"

