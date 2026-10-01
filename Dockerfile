FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && find /etc/apache2/mods-enabled -maxdepth 1 -type l -name 'mpm_*.load' -delete \
    && find /etc/apache2/mods-enabled -maxdepth 1 -type l -name 'mpm_*.conf' -delete \
    && a2enmod mpm_prefork headers \
    && sed -ri 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri 's#<VirtualHost \\*:80>#<VirtualHost *:8080>#' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . /var/www/html

ENV PORT=8080
EXPOSE 8080

CMD ["sh", "-c", "port=${PORT:-8080}; sed -ri \"s/Listen 8080/Listen ${port}/\" /etc/apache2/ports.conf; sed -ri \"s#<VirtualHost \\*:8080>#<VirtualHost *:${port}>#\" /etc/apache2/sites-available/000-default.conf; exec apache2-foreground"]
