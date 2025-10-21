FROM dunglas/frankenphp
ENV APP_ENV=prod
ENV SERVER_NAME=localhost

# Copie du binaire dans l’image
WORKDIR /app

RUN install-php-extensions \
	pdo_mysql \
	gd \
	intl \
	zip \
	opcache \
    openssl


# install GIT and Composer in image
RUN apt-get update && apt-get install -y git unzip libnss3-tools nodejs npm && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /app

# Expose le port (adapte-le selon ce que ton binaire écoute)
EXPOSE 80
EXPOSE 443


# Install project dependencies
RUN composer require symfony/property-info
RUN composer require symfony/ai-platform:dev-main --dev
RUN composer install --no-dev --optimize-autoloader
RUN npm install
RUN npm run build

# Commande de démarrage
ENTRYPOINT ["/app/websocket", "run"]
