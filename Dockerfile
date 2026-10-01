FROM php:8.3-cli

RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html
COPY . /var/www/html

ENV PORT=8080
EXPOSE 8080

CMD ["sh", "-c", "exec php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]
