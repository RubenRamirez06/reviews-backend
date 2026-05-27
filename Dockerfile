FROM php:8.2-fpm

RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN apt-get update && apt-get install -y nginx

COPY . /var/www/html/

COPY <<EOF /etc/nginx/sites-available/default
server {
    listen 80;
    root /var/www/html;
    index index.php;
    location / {
        try_files \$uri \$uri/ =404;
    }
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
}
EOF

RUN chown -R www-data:www-data /var/www/html

CMD service php8.2-fpm start && nginx -g 'daemon off;'
