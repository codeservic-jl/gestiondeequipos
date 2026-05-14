FROM php:8.2-cli

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli gd

WORKDIR /app

COPY . /app/

RUN mkdir -p /app/uploads/ordenes && chmod -R 755 /app/uploads/

EXPOSE 8080

CMD sh -c "php -S 0.0.0.0:${PORT:-8080} -t /app"
