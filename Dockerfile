FROM php:8.2-apache

# Instalar dependencias del sistema necesarias para las extensiones PHP
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli gd

# Eliminar MPM conflictivos y asegurar solo mpm_prefork
RUN rm -f /etc/apache2/mods-enabled/mpm_event.conf \
          /etc/apache2/mods-enabled/mpm_event.load \
          /etc/apache2/mods-enabled/mpm_worker.conf \
          /etc/apache2/mods-enabled/mpm_worker.load \
    && a2enmod mpm_prefork rewrite

# Suprimir advertencia de ServerName
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Permitir .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copiar archivos del proyecto
COPY . /var/www/html/

# Crear carpeta de uploads con permisos correctos
RUN mkdir -p /var/www/html/uploads/ordenes && \
    chown -R www-data:www-data /var/www/html/uploads/ && \
    chmod -R 755 /var/www/html/uploads/

# Script de inicio para ajustar el puerto dinámico de Railway
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
