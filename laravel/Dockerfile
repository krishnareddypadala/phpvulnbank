# Pinned by minor version. NEVER use `latest`, and never push this image to a
# registry without `vulnerable` in the tag name. See ../SECURITY.md.
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libicu-dev libxml2-dev unzip git \
    && docker-php-ext-install pdo_mysql zip gd intl dom \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Document root is public/ -- the correct configuration.
#
# NOTE: pointing DocumentRoot at the project root instead is VULN-37 in
# docs/proposed-lessons.md (exposing .env and storage/logs over HTTP). That
# lesson is NOT enabled here. If you enable it later, do it in a clearly named
# separate Dockerfile, never as the default build.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html

RUN composer install --no-interaction --prefer-dist --no-progress \
    && chown -R www-data:www-data /var/www/html

# VULN-04 depends on PHP being EXECUTED in the uploads directory. Under
# mod_php that is the default for public/images, which is what makes an
# uploaded .php file a webshell. On a stock nginx setup it would be served as
# text and the RCE half of that lesson would silently disappear.
RUN mkdir -p public/images && chown -R www-data:www-data public/images

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
CMD ["/usr/local/bin/docker-entrypoint.sh"]
