# syntax=docker/dockerfile:1
#
# Image de production pour l'API Laravel, pensée pour Render (Docker Web
# Service, un seul conteneur qui doit écouter sur $PORT). Apache + PHP-FPM
# intégré (php:8.4-apache) plutôt que nginx+php-fpm séparés : un seul
# processus web à superviser, suffisant pour une API sans usage massif.
#
# Le planificateur Laravel (`withSchedule` dans bootstrap/app.php — rapport
# hebdomadaire aux parents) a besoin d'un déclencheur externe : cron tourne
# dans le même conteneur, sous supervisord, plutôt que d'exiger un second
# service Render (Cron Job) rien que pour ça.

# ---------------------------------------------------------------------------
# Étape 1 — dépendances PHP (composer), dans une image jetable
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Suppression de --ignore-platform-reqs afin que Composer vérifie la compatibilité
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---------------------------------------------------------------------------
# Étape 2 — image d'exécution (Mise à jour vers PHP 8.4)
# ---------------------------------------------------------------------------
FROM php:8.4-apache AS app

# Bibliothèques système requises par les extensions PHP ci-dessous et par les
# packages composer qui en dépendent (mpdf, dompdf, phpoffice/phpword,
# maatwebsite/excel, endroid/qr-code génèrent des PDF/QR/Excel avec gd, zip,
# xml, mbstring…).
RUN apt-get update && apt-get install -y --no-install-recommends \
    cron \
    supervisor \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
    pdo_mysql \
    gd \
    zip \
    bcmath \
    intl \
    exif \
    mbstring \
    xml \
    opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Réglages de production raisonnables — le détail (mémoire, timeouts) reste
# ajustable via des variables d'env Render si besoin, sans reconstruire l'image.
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html

# Apache : document root sur public/, AllowOverride pour le .htaccess Laravel
# (réécriture vers index.php). Le port d'écoute n'est PAS fixé ici : Render
# n'injecte $PORT qu'au lancement du conteneur, jamais pendant `docker build` —
# la substitution se fait dans entrypoint.sh, juste avant de démarrer Apache.
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Planificateur Laravel : cron déclenche `schedule:run` chaque minute:
# Laravel décide lui-même, à cet instant, quelles tâches sont vraiment dues.
COPY docker/laravel-cron /etc/cron.d/laravel-cron
RUN chmod 0644 /etc/cron.d/laravel-cron

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# storage/ et bootstrap/cache/ doivent rester inscriptibles par le compte qui
# exécute PHP (www-data sous Apache).
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]