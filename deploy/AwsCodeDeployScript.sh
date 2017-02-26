#!/usr/bin/env bash
export LC_ALL=C.UTF-8

function add_line_to_file() {
    grep -q "$1" "$2"
    [ $? -ne 0 ] && echo "$1" | sudo tee -a "$2"
}

if [ "$LIFECYCLE_EVENT" == "ApplicationStop" ]
then
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ]
    then
        # Stop the database
        sudo service mongod stop
    fi
fi

if [ "$LIFECYCLE_EVENT" == "BeforeInstall" ]
then
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ]
    then
        # Install mongodb 3.2
        sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv EA312927
        add_line_to_file "deb http://repo.mongodb.org/apt/ubuntu xenial/mongodb-org/3.2 multiverse" /etc/apt/sources.list.d/mongodb-org-3.2.list
        sudo apt-get update
        sudo apt-get install -y mongodb-org=3.2.12 mongodb-org-server=3.2.12 mongodb-org-shell=3.2.12 mongodb-org-mongos=3.2.12 mongodb-org-tools=3.2.12
        echo "[Unit]
        Description=High-performance, schema-free document-oriented database
        After=network.target
        Documentation=https://docs.mongodb.org/manual

        [Service]
        User=mongodb
        Group=mongodb
        ExecStart=/usr/bin/mongod --quiet --config /etc/mongod.conf

        [Install]
        WantedBy=multi-user.target" > /lib/systemd/system/mongod.service
    fi

    # Install supervisor:
    sudo apt-get install -y supervisor
    sudo systemctl enable supervisor
    sudo systemctl start supervisor

    # Install PHP and dependencies:
    sudo add-apt-repository -y ppa:ondrej/php
    sudo apt-get update
    sudo apt-get install -y php5.6-cli php5.6-dev php5.6-curl php5.6-xml php5.6-bcmath php5.6-mbstring

    # Setup mbstring function overload for all str functions to use mb_ variants
    add_line_to_file "mbstring.func_overload 7" /etc/php/5.6/cli/php.ini
    add_line_to_file "mbstring.language Neutral" /etc/php/5.6/cli/php.ini

    # Install PHP mongo libs (echo 'no' declines sasl option prompt)
    echo "no" | sudo pecl install mongo
    add_line_to_file "extension=mongo.so" /etc/php/5.6/cli/php.ini

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

    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ]
    then
        # Set the mongodb uri config to 'localhost' for local deployment
        sed -i "s#mongodb_uri = .*#mongodb_uri = 'mongodb://localhost';#" config.php
    fi

    # Install the supervisor configuration files
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitor" ] || [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ]
    then
        sudo cp deploy/market_monitor.conf /etc/supervisor/conf.d/
        sudo cp deploy/report_server.conf /etc/supervisor/conf.d/
    fi

    if [ "$DEPLOYMENT_GROUP_NAME" == "StrategyProcessor" ]
    then
        sudo cp deploy/crypto_arbitrage.conf /etc/supervisor/conf.d/
    fi
fi

if [ "$LIFECYCLE_EVENT" == "ApplicationStart" ]
then
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitorLocalDb" ]
    then
        sudo service mongod start
    fi
    sudo supervisorctl reload
fi
