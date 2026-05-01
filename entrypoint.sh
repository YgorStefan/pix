#!/bin/sh
set -e

echo "Aguardando MySQL..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
  sleep 2
done

echo "Executando migrations..."
php bin/hyperf.php migrate --force

if [ "$APP_ENV" != "production" ]; then
  echo "Executando migrations de teste..."
  APP_ENV=testing php bin/hyperf.php migrate --force
fi

echo "Iniciando servidor..."
exec php bin/hyperf.php start
