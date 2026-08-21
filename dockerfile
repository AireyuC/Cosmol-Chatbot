FROM php:7.3-apache

WORKDIR /app

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

RUN docker-php-ext-install pdo pdo_mysql mysqli

# Asegurar que el archivo de logs sea escribible por Apache (Fase 5 — Seguridad)
RUN touch /var/log/cosmol_api.log && chown www-data:www-data /var/log/cosmol_api.log

# Cambiar el DocumentRoot de Apache para que apunte directamente a la carpeta /public
ENV APACHE_DOCUMENT_ROOT /app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

