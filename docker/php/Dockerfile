FROM php:8.2-fpm-alpine3.18

# Mise à jour
#RUN apk --no-cache update && \
#    apk upgrade \

# Installation paquets
RUN apk --update --no-cache add acl bash curl make nano supervisor zip

# Installation extensions php
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions bcmath gd intl ldap mcrypt mysqli opcache pdo_mysql pdo_pgsql sockets ssh2 yaml

#WORKDIR /usr/local/etc/php-fpm.d
#COPY php/log.conf /usr/local/etc/php-fpm.d/zz-log.conf

# Conf zz-app.ini
WORKDIR /usr/local/etc/php/conf.d/
ADD zz-app.ini /usr/local/etc/php/conf.d/

# Installation composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

#WORKDIR /etc/supervisor
#COPY supervisor/supervisord.conf /etc/supervisor/supervisord.conf

WORKDIR /etc/supervisor/conf.d
COPY supervisor/main.conf /etc/supervisor/conf.d/main.conf

# Logs
WORKDIR /var/log
RUN mkdir supervisor
RUN touch supervisor/supervisord.log
RUN chmod -R 755 supervisor

RUN mkdir php-fpm
RUN touch php-fpm/access.log
RUN touch php-fpm/error.log
RUN chmod -R 755 php-fpm

# Install Symfony CLI
RUN wget https://get.symfony.com/cli/installer -O - | bash && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

WORKDIR /var/www

USER www-data

#ENTRYPOINT ["/usr/bin/supervisord", "--nodaemon", "--configuration", "/etc/supervisor/supervisord.conf"]

EXPOSE 9000
