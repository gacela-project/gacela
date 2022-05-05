FROM php:8.1-fpm-alpine
WORKDIR /app
VOLUME /app

# install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"

COPY . /app

ENV COMPOSER_ALLOW_SUPERUSER 1
RUN composer install
