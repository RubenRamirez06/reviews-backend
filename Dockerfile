FROM php:8.2-fpm

RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN apt-get update && apt-get install -y nginx

COPY . /var/www/html/

RUN cat > /etc/nginx/sites-available/default <<'EOF'
server {
    listen 80;
    root /var/www/html;
    index index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
EOF

RUN chown -R www-data:www-data /var/www/html

CMD php-fpm -D && nginx -g 'daemon off;'
