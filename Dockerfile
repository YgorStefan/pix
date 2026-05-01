FROM hyperf/hyperf:8.2-alpine-v3.19-swoole-slim

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --optimize-autoloader --no-scripts

COPY . .

RUN mkdir -p runtime/logs && chmod +x entrypoint.sh

EXPOSE 9501

ENTRYPOINT ["sh", "entrypoint.sh"]
