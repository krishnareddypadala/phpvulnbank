#!/bin/bash


# Start Apache
echo -n "Do you want to connect on SSH to docker Instance? (Yes/No):"
read ch
if [[ "$ch" == "yes" || "$ch" = "y" || "$ch" = "yes" || "$ch" = "Y" ]]; then
echo -n "Enter the username: "
read username
echo -n "Enter the password: "
read -s password
adduser --gecos "" --disabled-password $username
chpasswd <<<"$username:$password"
service ssh start
fi

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