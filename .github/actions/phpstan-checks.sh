#!/bin/sh
docker exec --user www-data glpi /bin/bash -c "cd /var/www/glpi/plugins/jamf && vendor/bin/phpstan analyze -c phpstan.neon --memory-limit 256M"
