FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql

WORKDIR /var/www/html
COPY . /var/www/html

ENV PORT=8080
EXPOSE 8080

CMD ["sh", "-c", "if [ -n \"${RAILWAY_ENVIRONMENT:-}\" ]; then sh /var/www/html/scripts/seed-railway-db.sh; fi; exec php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]
