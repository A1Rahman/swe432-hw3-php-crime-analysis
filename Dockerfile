# Render does not run PHP natively, so we deploy with Docker.
FROM php:8.3-apache

# Raise upload/memory limits so the 11 MB dataset can be uploaded.
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Copy the app into Apache's web root.
COPY index.php style.css /var/www/html/
COPY classes/ /var/www/html/classes/

EXPOSE 80