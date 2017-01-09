#!/usr/bin/env bash

if [ "$LIFECYCLE_EVENT" == "BeforeInstall" ]
then
    # Install supervisor:
    sudo apt-get install -y supervisor
    sudo systemctl enable supervisor
    sudo systemctl start supervisor

    # Install PHP and dependencies:
    sudo add-apt-repository -y ppa:ondrej/php
    sudo apt-get update
    sudo apt-get install -y php5.6-cli php5.6-dev php5.6-curl php5.6-xml php5.6-bcmath

    # Install PHP mongo libs (echo 'no' declines sasl option prompt)
    echo "no" | sudo pecl install mongo
    grep -q "extension=mongo.so" /etc/php/5.6/cli/php.ini; [ $? -ne 0 ] && echo "extension=mongo.so" | sudo tee -a /etc/php/5.6/cli/php.ini

    sudo apt-get install -y php-pear
    sudo pear channel-discover pear.apache.org/log4php
    sudo pear install log4php/Apache_log4php
fi

if [ "$LIFECYCLE_EVENT" == "AfterInstall" ]
then
    cp config.example.php config.php

    # Install the supervisor configuration files
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitor" ]
    then
        sudo cp deploy/market_monitor.conf /etc/supervisor/conf.d/
        sudo cp deploy/report_serverinstructions - Ubuntu1604.txt.conf /etc/supervisor/conf.d/
    fi

    if [ "$DEPLOYMENT_GROUP_NAME" == "StrategyProcessor" ]
    then
        sudo cp deploy/crypto_arbitrage.conf /etc/supervisor/conf.d/
    fi
fi

if [ "$LIFECYCLE_EVENT" == "ApplicationStart" ]
then
    sudo supervisorctl reload
fi