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

# install GIT and Composer in image
RUN apt-get update && apt-get install -y git unzip && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install project dependencies
RUN composer install --no-dev --optimize-autoloader


# Commande de démarrage
ENTRYPOINT ["/app/websocket", "run"]
