#!/usr/bin/env bash
export LC_ALL=C.UTF-8

function add_line_to_file() {
    grep -q "$1" "$2"
    [ $? -ne 0 ] && echo "$1" | sudo tee -a "$2"
}

if [ "$LIFECYCLE_EVENT" == "ApplicationStop" ]
then
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "CryptoArbitrageAll" ]
    then
        # Stop the database
        sudo service mongod stop
    fi
fi

if [ "$LIFECYCLE_EVENT" == "BeforeInstall" ]
then
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "CryptoArbitrageAll" ]
    then
        # Install mongodb 3.4
        sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv 0C49F3730359A14518585931BC711F9BA15703C6
        add_line_to_file "deb [ arch=amd64,arm64 ] http://repo.mongodb.org/apt/ubuntu xenial/mongodb-org/3.4 multiverse" /etc/apt/sources.list.d/mongodb-org-3.4.list
        sudo apt-get update
        sudo apt-get install -y mongodb-org
    fi

    # Install supervisor:
    sudo apt-get install -y supervisor
    sudo systemctl enable supervisor
    sudo systemctl start supervisor

    # Install PHP and dependencies:
    sudo apt-get install -y php7.0-cli php7.0-dev php7.0-curl php7.0-xml php7.0-bcmath php7.0-mbstring

    # Setup mbstring function overload for all str functions to use mb_ variants
    add_line_to_file "mbstring.func_overload 7" /etc/php/7.0/cli/php.ini
    add_line_to_file "mbstring.language Neutral" /etc/php/7.0/cli/php.ini

    # Configure pecl
    sudo pecl config-set php_ini /etc/php/7.0/cli/php.ini

    # Install PHP mongodb libs
    sudo pecl install mongodb
    add_line_to_file "extension=mongodb.so" /etc/php/7.0/mods-available/mongodb.ini
    sudo ln -s -T /etc/php/7.0/cli/conf.d/99-mongodb.ini /etc/php/7.0/mods-available/mongodb.ini

    # Make PHP mongodb libs accessible via Composer
    sudo php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    sudo php -r "if (hash_file('SHA384', 'composer-setup.php') === '55d6ead61b29c7bdee5cccfb50076874187bd9f21f65d8991d46ec5cc90518f447387fb9f76ebae1fbbacf329e583e30') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
    sudo php composer-setup.php --install-dir=/usr/bin --filename=composer
    sudo php -r "unlink('composer-setup.php');"

    # Install log4php, pear reports error on install if package already exists
    sudo apt-get install -y php-pear
    sudo pear channel-discover pear.apache.org/log4php
    sudo pear install log4php/Apache_log4php
    if [ $? -ne 0 ]
    then
        sudo pear upgrade log4php/Apache_log4php
    fi
fi

if [ "$LIFECYCLE_EVENT" == "AfterInstall" ]
then
    cd /home/ubuntu/CryptoArbitrage
    echo $PWD
    cp config.example.php config.php

    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "CryptoArbitrageAll" ]
    then
        # Set the mongodb uri config to 'localhost' for local deployment
        sed -i "s#mongodb_uri = .*#mongodb_uri = 'mongodb://localhost';#" config.php
    fi

    # Install package libs using dependencies specified in composer files
    sudo composer install

    # Install the supervisor configuration files
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitor" ] || [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "CryptoArbitrageAll" ]
    then
        sudo cp deploy/market_monitor.conf /etc/supervisor/conf.d/
        sudo cp deploy/report_server.conf /etc/supervisor/conf.d/
    fi

    if [ "$DEPLOYMENT_GROUP_NAME" == "StrategyProcessor" ] || [ "$DEPLOYMENT_GROUP_NAME" == "CryptoArbitrageAll" ]
    then
        sudo cp deploy/crypto_arbitrage.conf /etc/supervisor/conf.d/
    fi
fi

if [ "$LIFECYCLE_EVENT" == "ApplicationStart" ]
then
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "CryptoArbitrageAll" ]
    then
        sudo service mongod start
    fi
    sudo supervisorctl reload
fi
