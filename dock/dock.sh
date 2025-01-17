#!/bin/bash

# If you are editing this file ensure it is save in LF format-windows CRLF will fail this script
echo -n "Do you want to connect on SSH/Winscp to docker Instance? (Yes/No):"
read ch
if [[ "$ch" == "yes" || "$ch" = "y" || "$ch" = "Yes" || "$ch" = "Y" ]]; then
echo -n "Set username for ssh: "
read username
echo -n "Set the password for ssh: "
read -s password
adduser --gecos "" --disabled-password $username
chpasswd <<<"$username:$password"
chown -R $username /var/www/html
service ssh start 
fi

#mysql.server start

/etc/init.d/mysql start

mysql -u root -e  "source /usr/sbin/banktable.sql"
#php /usr/sbin/db.php



echo " * Starting Apache Server...! "
#/usr/sbin/apachectl  start
service apache2 restart

echo "phpvulnbank is up .. You can access http://localhost:8090/login.php"

/bin/bash
#To start docker run -it -p 8090:80 phpvulnbank
#CMD ["/usr/sbin/dock.sh"]