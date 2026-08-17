#!/bin/sh
# Ne s'exécute qu'au démarrage du conteneur — c'est le seul moment où les
# variables d'environnement Render (APP_KEY, DB_*, PORT…) existent vraiment.
# Tout ce qui dépend d'elles (Listen d'Apache, caches Laravel) doit donc être
# fait ici, jamais dans le Dockerfile (qui, lui, ne voit rien de tout ça).
set -e

: "${PORT:=8080}"

sed -i "s/__PORT__/${PORT}/g" /etc/apache2/sites-available/000-default.conf
sed -i "s/^Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY absente : générez-la une fois en local (php artisan key:generate --show)" >&2
    echo "puis ajoutez-la comme variable d'environnement du service Render." >&2
    exit 1
fi

# Le lien public/storage ne survit jamais à un redéploiement (disque
# éphémère) : on le recrée à chaque démarrage, sans échouer s'il existe déjà
# (redémarrage du même conteneur plutôt que nouveau déploiement).
[ -L /var/www/html/public/storage ] || php artisan storage:link

php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Les migrations ne se lancent PAS ici automatiquement : sur une base de
# production, ce déclenchement mérite d'être un geste volontaire plutôt
# qu'un effet de bord de chaque déploiement. À lancer soit depuis le Shell
# Render (`php artisan migrate --force`), soit via la "Pre-Deploy Command"
# du service Render si vous préférez l'automatiser.

exec "$@"
