FROM ubuntu:18.04
RUN apt-get update
RUN apt-get upgrade -y
ENV DEBIAN_FRONTEND noninteractive
RUN apt install -y php php-mysql
RUN apt-get install apache2 libapache2-mod-php -y
RUN apt-get install php-dom -y
RUN apt install mysql-server -y

COPY /src/ /var/www/html/phpvulnbank/
COPY /dock/ /usr/sbin/
COPY /dbscript/ /usr/sbin/

RUN chmod +x /usr/sbin/dock.sh
RUN chmod +x /usr/sbin/db.php
RUN chown -R www-data:www-data /var/www/html

VOLUME /var/www/html
VOLUME /var/log/httpd

VOLUME /var/lib/mysql
VOLUME /var/log/mysql
VOLUME /etc/apache2

EXPOSE 80
CMD ["/usr/sbin/dock.sh"]


