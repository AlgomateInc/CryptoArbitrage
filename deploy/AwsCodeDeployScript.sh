#!/usr/bin/env bash
export LC_ALL=C.UTF-8

function add_line_to_file() {
    grep -q "$1" "$2"
    [ $? -ne 0 ] && echo "$1" | sudo tee -a "$2"
}

if [ "$LIFECYCLE_EVENT" == "ApplicationStop" ]
then
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "StrategyProcessorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "CryptoArbitrageAll" ]
    then
        # Stop the database
        sudo service mongod stop
    fi
fi

if [ "$LIFECYCLE_EVENT" == "BeforeInstall" ]
then
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "StrategyProcessorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "CryptoArbitrageAll" ]
    then
        # Install mongodb 3.6
        sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv 2930ADAE8CAF5059EE73BB4B58712A2291FA4AD5
        add_line_to_file "deb [ arch=amd64,arm64 ] https://repo.mongodb.org/apt/ubuntu xenial/mongodb-org/3.6 multiverse" /etc/apt/sources.list.d/mongodb-org-3.6.list
        sudo apt-get update
        sudo apt-get install -y mongodb-org
    fi

    # Install supervisor:
    sudo apt-get install -y supervisor
    sudo systemctl enable supervisor
    sudo systemctl start supervisor

    # Install PHP and dependencies:
    sudo apt-get install -y php7.0-cli php7.0-dev php7.0-curl php7.0-xml php7.0-bcmath php7.0-mbstring pkg-config openssl

    # Setup mbstring function overload for all str functions to use mb_ variants
    add_line_to_file "mbstring.func_overload 7" /etc/php/7.0/cli/php.ini
    add_line_to_file "mbstring.language Neutral" /etc/php/7.0/cli/php.ini

    # Configure pecl
    sudo pecl config-set php_ini /etc/php/7.0/cli/php.ini

    # Install PHP mongodb libs
    sudo pecl install mongodb
    add_line_to_file "extension=mongodb.so" /etc/php/7.0/mods-available/mongodb.ini
    sudo ln -s -T /etc/php/7.0/mods-available/mongodb.ini /etc/php/7.0/cli/conf.d/99-mongodb.ini

    # Make PHP mongodb libs accessible via Composer:
    # https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md
    EXPECTED_SIGNATURE=$(wget -q -O - https://composer.github.io/installer.sig)
    sudo php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_SIGNATURE=$(php -r "echo hash_file('SHA384', 'composer-setup.php');")

    if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]
    then
      >&2 echo 'ERROR: Invalid installer signature'
      sudo rm composer-setup.php
      exit 1
    fi

    sudo php composer-setup.php --quiet --install-dir=/usr/bin --filename=composer
    sudo rm composer-setup.php
fi

if [ "$LIFECYCLE_EVENT" == "AfterInstall" ]
then
    cd /home/ubuntu/CryptoArbitrage
    echo "$PWD"
    sudo cp ConfigDataExample.php ConfigData.php
    # Remove "Example" from class name
    sed -i "s#ConfigDataExample#ConfigData#" ConfigData.php

    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "StrategyProcessorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "CryptoArbitrageAll" ]
    then
        # Set the mongodb uri config to 'localhost' for local deployment
        sed -i "s#mongodb_uri = .*#mongodb_uri = 'mongodb://localhost';#" ConfigData.php
        # Omit bound ip so mongodb is accessible from the outside
        sudo sed -i "s/^  bindIp/#  bindIp/" /etc/mongod.conf
    fi

    # Install package libs using dependencies specified in composer files
    sudo composer install
    sudo composer dump-autoload -o

    # Install the supervisor configuration files
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitor" ] || [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "CryptoArbitrageAll" ]
    then
        sudo cp deploy/market_monitor.conf /etc/supervisor/conf.d/
        sudo cp deploy/report_server.conf /etc/supervisor/conf.d/
    fi

    if [ "$DEPLOYMENT_GROUP_NAME" == "StrategyProcessor" ] || [ "$DEPLOYMENT_GROUP_NAME" == "StrategyProcessorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "CryptoArbitrageAll" ]
    then
        sudo cp deploy/crypto_arbitrage.conf /etc/supervisor/conf.d/
    fi
fi

if [ "$LIFECYCLE_EVENT" == "ApplicationStart" ]
then
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "StrategyProcessorLocalDb" ] || [ "$DEPLOYMENT_GROUP_NAME" == "CryptoArbitrageAll" ]
    then
        sudo service mongod start
    fi
    sudo supervisorctl reload
fi
