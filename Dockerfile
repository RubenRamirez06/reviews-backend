FROM php:8.2-apache

# Instalar extensiones necesarias para conectar PHP con MySQL (PDO y MySQLi)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar el módulo rewrite de Apache (muy útil para APIs)
RUN a2enmod rewrite

# Cambiar la configuración de subida en PHP para que soporte imágenes Base64 grandes
RUN echo "upload_max_filesize = 64M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Copiar todos los archivos de tu backend dentro del servidor Apache
COPY . /var/www/html/

# Exponer el puerto 80 estándar de la web
EXPOSE 80