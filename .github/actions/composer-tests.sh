#!/bin/sh
docker exec --user www-data glpi /bin/bash -c "cd /var/www/glpi/plugins/jamf && vendor/bin/phpunit -c tests/phpunit.xml --testsuite \"PHP Unit Tests\" --do-not-cache --verbose"
