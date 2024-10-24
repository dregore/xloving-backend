FROM php:8.3-fpm

COPY php.ini /usr/local/etc/php/
COPY docker.conf /usr/local/etc/php-fpm.d/docker.conf
COPY .bashrc /root/

# mix
RUN apt-get update \
  && apt-get install -y build-essential zlib1g-dev default-mysql-client curl gnupg procps nano git unzip libzip-dev libpq-dev cron wget \
  && docker-php-ext-install zip pdo_mysql pdo_pgsql pgsql

# intl
RUN apt-get install -y libicu-dev \
  && docker-php-ext-configure intl \
  && docker-php-ext-install intl

# gd
RUN apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev && \
  docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ && \
  docker-php-ext-install gd

# redis
RUN pecl install redis && docker-php-ext-enable redis

# pcov
RUN pecl install pcov && docker-php-ext-enable pcov

# Xdebug
# RUN pecl install xdebug \
# && docker-php-ext-enable xdebug \
# && echo ";zend_extension=xdebug" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Node.js, NPM, Yarn
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash -
RUN apt-get install -y nodejs
RUN npm install npm@latest -g
RUN npm install yarn -g

# Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"
RUN mv composer.phar /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_HOME /composer
ENV PATH $PATH:/composer/vendor/bin
RUN composer config --global process-timeout 3600
RUN composer global require "laravel/installer"

WORKDIR /root
RUN git clone https://github.com/seebi/dircolors-solarized

RUN mkdir -p /home/dregore

RUN echo "* * * * * cd /var/www/html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1" >> /etc/cron.d/crontab
# RUN echo "* * * * * echo \"USERNAME_DATA='SUPERADMIN'\" >> /var/www/html/cron.log"  >> /etc/cron.d/crontab
RUN crontab /etc/cron.d/crontab
RUN touch /var/www/html/cron.log

# EXPOSE 5173
WORKDIR /var/www/html

RUN groupadd -g 1000 dregore
RUN useradd -u 1000 -g dregore dregore
COPY --chown=www-data:www-data . /var/www
# USER www-data

EXPOSE 9000

CMD bash -c "cron && php-fpm"
