#!/bin/sh

# install glpi database
docker exec --user www-data glpi /bin/bash -c "cd /var/www/glpi && php bin/console db:install -H db -u glpi -p glpi -d glpi -n -r"

# install our plugin
docker exec --user www-data glpi /bin/bash -c "cd /var/www/glpi && php bin/console plugin:install -u glpi jamf"
docker exec --user www-data glpi /bin/bash -c "cd /var/www/glpi && php bin/console plugin:activate jamf"
