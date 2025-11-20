FROM php:8.2-cli

# Install system dependencies and tools needed for Composer
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        zip \
        unzip \
        git \
        libzip-dev \
    && apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) pdo_mysql mysqli zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --prefer-dist

# Expose port
EXPOSE $PORT

# Start PHP server
CMD php -S 0.0.0.0:$PORT -t .

