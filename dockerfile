FROM php:7.3-alpine

# Instalar extensiones necesarias de PDO MySQL para PHP 7.3
RUN docker-php-ext-install pdo pdo_mysql mysqli

WORKDIR /app

