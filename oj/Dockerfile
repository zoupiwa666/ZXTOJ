FROM php:8.2-fpm

# 国内 apt 源（阿里云，直接覆盖 deb822 源文件）
RUN rm -f /etc/apt/sources.list.d/debian.sources && \
    echo "deb http://mirrors.aliyun.com/debian/ trixie main contrib non-free non-free-firmware" > /etc/apt/sources.list && \
    echo "deb http://mirrors.aliyun.com/debian/ trixie-updates main contrib non-free non-free-firmware" >> /etc/apt/sources.list && \
    echo "deb http://mirrors.aliyun.com/debian-security trixie-security main contrib non-free non-free-firmware" >> /etc/apt/sources.list

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx mariadb-server zip unzip curl python3 python3-pip \
    libpng-dev libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-install pdo_mysql gd zip mbstring mysqli \
    && printf 'upload_max_filesize = 1024M\npost_max_size = 1024M\nmax_execution_time = 600\nmax_input_time = 600\nmemory_limit = 512M\n' > /usr/local/etc/php/conf.d/upload.ini \
    && pip3 config set global.index-url https://mirrors.aliyun.com/pypi/simple/ \
    && pip3 install --break-system-packages pymysql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/oj
COPY site/ /var/www/oj/
COPY config_gen.php /var/www/oj/config_gen.php
COPY init.sql /var/www/oj/init.sql
COPY nginx.conf /etc/nginx/sites-enabled/default
COPY php-fpm-www.conf /usr/local/etc/php-fpm.d/www.conf
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
