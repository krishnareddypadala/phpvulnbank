#!/bin/bash


# Start Apache


#mysql.server start

/etc/init.d/mysql start

mysql -u root -e  "source /usr/sbin/banktable.sql"
#php /usr/sbin/db.php



echo " * Starting Apache Server...! "
/usr/sbin/apachectl  start

echo "phpvulnbank is up .. You can access http://localhost:8090/phpvulnbank/login.php"

/bin/bash
#To start docker run -it -p 8090:80 phpvulnbank
#CMD ["/usr/sbin/dock.sh"]