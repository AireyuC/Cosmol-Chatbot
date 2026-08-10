FROM php:7.3-apache

# Habilitar mod_rewrite de Apache (esencial para APIs y enrutamiento)
RUN a2enmod rewrite

# Instalar extensiones necesarias de PDO MySQL para la conexión a la base de datos
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Cambiar el DocumentRoot de Apache para que apunte directamente a la carpeta /public
# (Protegiendo así el código fuente en /app)
ENV APACHE_DOCUMENT_ROOT /app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Directorio de trabajo
WORKDIR /app
