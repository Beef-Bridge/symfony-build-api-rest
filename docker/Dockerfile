FROM php:8.1-apache

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    \
    &&  apt-get update \
    &&  apt-get install -y --no-install-recommends \
    locales apt-utils git libicu-dev g++ libpng-dev libxml2-dev libzip-dev libonig-dev libxslt-dev unzip \
    && apt-get install -y wget \
    \
    &&  echo "en_US.UTF-8 UTF-8" > /etc/locale.gen  \
    &&  echo "fr_FR.UTF-8 UTF-8" >> /etc/locale.gen \
    &&  locale-gen \
    \
    &&  curl -sS https://getcomposer.org/installer | php -- \
    &&  mv composer.phar /usr/local/bin/composer \
    \
    && curl -sL https://deb.nodesource.com/setup_18.x | bash \
    && apt-get install nodejs \
    \
    && npm install --global yarn \
    \
    &&  docker-php-ext-configure \
    intl \
    &&  docker-php-ext-install \
    pdo pdo_mysql opcache intl zip calendar dom mbstring gd xsl \
    \
    &&  pecl install apcu && docker-php-ext-enable apcu

# Default powerline10k theme, no plugins installed
RUN sh -c "$(wget -O- https://github.com/deluan/zsh-in-docker/releases/download/v1.1.5/zsh-in-docker.sh)"

WORKDIR /var/www/