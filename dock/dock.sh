#!/bin/bash


# Time zone for php
#/bin/sed -i "s/\;date\.timezone\ \=/date\.timezone\ \=\ ${DATE_TIMEZONE}/" /etc/php/7.0/apache2/php.ini
# Start Apache
#/usr/sbin/apachectl start
/usr/sbin/apachectl start
#mysql.server start
/etc/init.d/mysql start
mysql -u root -e "SET PASSWORD FOR root@'localhost' = PASSWORD('bose123$');"
mysql -u root -e "CREATE DATABASE bankdb";
mysql -u root -e  "source /usr/sbin/banktable.sql"
mysql -u root -e "CREATE USER 'groot'@'%' IDENTIFIED BY 'bose123$';"
mysql -u root -e "GRANT ALL PRIVILEGES ON *.* TO 'groot'@'%' WITH GRANT OPTION;"

#php /usr/sbin/db.php

/bin/bash


#To start docker run -it -p 8090:80 phpvulnbank
#CMD ["/usr/sbin/dock.sh"]