FROM dunglas/frankenphp
ENV APP_ENV=prod
ENV SERVER_NAME=localhost

# Copie du binaire dans l’image
WORKDIR /app

# Assure-toi que le binaire est exécutable

# (optionnel) Copie du projet Symfony (si ton binaire a besoin des fichiers)
COPY . /app

# Expose le port (adapte-le selon ce que ton binaire écoute)
EXPOSE 80
EXPOSE 443

# Commande de démarrage
ENTRYPOINT ["/app/websocket", "run"]
