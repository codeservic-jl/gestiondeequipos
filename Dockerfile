FROM php:8.2-apache

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install pdo pdo_mysql mysqli gd

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Suprimir advertencia de ServerName
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Permitir .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copiar archivos del proyecto
COPY . /var/www/html/

# Crear carpeta de uploads y asignar permisos
RUN mkdir -p /var/www/html/uploads/ordenes && \
    chown -R www-data:www-data /var/www/html/uploads/ && \
    chmod -R 755 /var/www/html/uploads/

# Script de inicio para ajustar el puerto dinámico de Railway
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
