FROM php:7.3-apache

WORKDIR /app

RUN a2enmod rewrite 

# Sobrescribimos el sources.list para usar HTTPS, ya que el firewall/proxy parece estar bloqueando HTTP (puerto 80) y devolviendo 403 Forbidden.
RUN echo "deb https://deb.debian.org/debian bullseye main" > /etc/apt/sources.list \
    && echo "deb https://security.debian.org/debian-security bullseye-security main" >> /etc/apt/sources.list \
    && apt-get update \
    && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pdo_mysql mysqli \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN touch /var/log/cosmol_api.log && chown www-data:www-data /var/log/cosmol_api.log

# Cambiar el DocumentRoot de Apache para que apunte directamente a la carpeta /public
ENV APACHE_DOCUMENT_ROOT /app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

