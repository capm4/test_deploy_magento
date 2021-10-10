#!/bin/bash
php bin/magento maintenance:enable
#chown -R www-data:www-data var/
#chown -R www-data:www-data pub/
#chown -R www-data:www-data pub/static
#chmod 777 pub/static
composer dump-autoload -o --apcu
php bin/magento deploy:mode:set production -s
php bin/magento setup:upgrade --keep-
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy --jobs=10
#chown -R www-data:www-data var/
#chown -R www-data:www-data pub/
php bin/magento setup:di:compile
php bin/magento maintenance:disable
#chown -R www-data:www-data var/
#chown -R www-data:www-data pub/
php bin/magento cache:flush

#find . -type f -exec chmod 644 {} \;
#find . -type d -exec chmod 755 {} \;
#
#chmod 644 ./app/etc/*.xml
#
#chown -R :www-data .
#
#chmod u+x bin/magento
