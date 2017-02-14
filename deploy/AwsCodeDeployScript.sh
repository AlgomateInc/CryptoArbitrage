#!/usr/bin/env bash
export LC_ALL=C.UTF-8

if [ "$LIFECYCLE_EVENT" == "ApplicationStop" ]
then
    # Blast the directory if it exists before deploying
    if [ -d /home/ubuntu/CryptoArbitrage ]
    then
       rm -rf /home/ubuntu/CryptoArbitrage
    fi

    # Stop the database
    sudo service mongod stop
fi

if [ "$LIFECYCLE_EVENT" == "BeforeInstall" ]
then
    # Install mongodb 3.2
    sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv EA312927
    echo "deb http://repo.mongodb.org/apt/ubuntu xenial/mongodb-org/3.2 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-3.2.list
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

    # Install the supervisor configuration files
    if [ "$DEPLOYMENT_GROUP_NAME" == "MarketDataMonitor" ]
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
    sudo service mongod start
    sudo supervisorctl reload
fi
