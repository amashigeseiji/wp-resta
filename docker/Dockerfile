FROM wordpress:cli-php8.2

# Switch to root for installation
USER root

# Install build dependencies for PCOV
RUN apk add --no-cache \
    $PHPIZE_DEPS \
    autoconf \
    g++ \
    make

# Install PCOV
RUN pecl install pcov && \
    docker-php-ext-enable pcov && \
    echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

# Clean up build dependencies
RUN apk del $PHPIZE_DEPS autoconf g++ make && \
    rm -rf /tmp/pear

# Verify PCOV installation
RUN php -m | grep pcov

WORKDIR /var/www/html
