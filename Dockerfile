# PHP Card Authentication - Docker Configuration
FROM php:8.3-cli

# Set working directory
WORKDIR /app

# Install system dependencies and PHP extensions
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    git \
    unzip \
    wget \
    libzip-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    zip \
    dom \
    curl \
    mbstring \
    intl \
    fileinfo \
    opcache \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Configure PHP for production
RUN echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.revalidate_freq=2" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Security and performance settings
RUN echo "expose_php=Off" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "display_errors=Off" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "log_errors=On" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/performance.ini && \
    echo "max_execution_time=30" >> /usr/local/etc/php/conf.d/performance.ini && \
    echo "max_input_time=60" >> /usr/local/etc/php/conf.d/performance.ini && \
    echo "post_max_size=10M" >> /usr/local/etc/php/conf.d/performance.ini && \
    echo "upload_max_filesize=5M" >> /usr/local/etc/php/conf.d/performance.ini

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Copy application source code
COPY . .

# Create logs directory and set permissions
RUN mkdir -p /app/logs && \
    chmod 755 /app/logs

# Create non-root user for security
RUN useradd --create-home --shell /bin/bash --user-group app && \
    chown -R app:app /app

# Switch to non-root user
USER app

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD wget --quiet --tries=1 --spider http://localhost:${PORT:-8000}/config.php || exit 1

# Expose port
EXPOSE 8000

# Start command
CMD ["php", "-S", "0.0.0.0:8000"]

# Multi-stage build for production
FROM php:8.3-cli as production

WORKDIR /app

# Install production dependencies only
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    libzip-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    libonig-dev \
    && docker-php-ext-install \
    zip \
    dom \
    curl \
    mbstring \
    intl \
    fileinfo \
    opcache \
    && rm -rf /var/lib/apt/lists/*

# Copy Composer from official image
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Production PHP configuration
COPY docker/php.prod.ini /usr/local/etc/php/conf.d/

# Copy application
COPY --from=0 /app .

# Production optimizations
RUN composer dump-autoload --optimize --classmap-authoritative

# Security configurations
RUN useradd --create-home --shell /bin/bash --user-group app && \
    chown -R app:app /app && \
    chmod -R 755 /app && \
    chmod -R 644 /app/*.php

USER app

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000"]