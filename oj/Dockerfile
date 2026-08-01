FROM php:8.2-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx mariadb-server zip unzip curl python3 python3-pip \
    libpng-dev libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-install pdo_mysql gd zip mbstring mysqli \
    && pip3 install --break-system-packages pymysql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/oj
COPY site/ /var/www/oj/
COPY config_gen.php /var/www/oj/config_gen.php
COPY init.sql /var/www/oj/init.sql
COPY nginx.conf /etc/nginx/sites-enabled/default
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
