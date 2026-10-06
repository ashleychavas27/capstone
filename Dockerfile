# syntax=docker/dockerfile:1

# ==============================================================================
# Stage 1: Build frontend assets (Vite + Tailwind CSS + Supabase JS)
# ==============================================================================
FROM node:20-alpine AS frontend
WORKDIR /app

# Install npm dependencies first for layer caching
COPY package.json package-lock.json ./
RUN npm ci

# Copy application source for frontend compilation
COPY . .

# Build arguments for Vite (Render passes env vars as build args automatically)
ARG VITE_SUPABASE_URL
ARG VITE_SUPABASE_ANON_KEY
ARG VITE_SUPABASE_SCHEMA=dental
ARG VITE_SUPABASE_READS=off

ENV VITE_SUPABASE_URL=$VITE_SUPABASE_URL \
    VITE_SUPABASE_ANON_KEY=$VITE_SUPABASE_ANON_KEY \
    VITE_SUPABASE_SCHEMA=$VITE_SUPABASE_SCHEMA \
    VITE_SUPABASE_READS=$VITE_SUPABASE_READS

RUN npm run build

# ==============================================================================
# Stage 2: Production PHP 8.2 Application Server with Apache
# ==============================================================================
FROM php:8.2-apache AS production

ENV DEBIAN_FRONTEND=noninteractive \
    APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PORT=80

# Install required system packages and PHP extensions for Laravel + PostgreSQL
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        zip \
        bcmath \
        intl \
        gd \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite and headers modules
RUN a2enmod rewrite headers

# Copy Composer binary from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure Apache virtual host
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Copy composer manifests first for Docker caching
COPY composer.json composer.lock ./

# Install production PHP dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader

# Copy application source code
COPY . .

# Complete composer autoload generation
RUN composer dump-autoload --optimize --no-dev

# Copy compiled frontend assets from frontend stage
COPY --from=frontend /app/public/build ./public/build

# Set ownership and permissions for Laravel storage and bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Configure entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
