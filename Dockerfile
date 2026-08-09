FROM php:8.3-apache

# 官方 PHP Apache 镜像已包含 PDO、pdo_sqlite、sqlite3、DOM 和 SimpleXML。
RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html/
RUN mkdir -p storage/images \
    && chown -R www-data:www-data storage

EXPOSE 80
