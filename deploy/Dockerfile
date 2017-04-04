FROM ubuntu:16.04
RUN apt-get update && apt-get install -y locales software-properties-common \
 && rm -rf /var/lib/apt/lists/* \
 && localedef -i en_US -c -f UTF-8 -A /usr/share/locale/locale.alias en_US.UTF-8
ENV LANG en_US.utf8
RUN apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv EA312927 \
 && add-apt-repository -y ppa:ondrej/php \
# Install mongodb
 && echo "deb http://repo.mongodb.org/apt/ubuntu xenial/mongodb-org/3.2 multiverse" | tee /etc/apt/sources.list.d/mongodb-org-3.2.list \
 && apt-get update \
 && apt-get install -y mongodb-org=3.2.12 mongodb-org-server=3.2.12 mongodb-org-shell=3.2.12 mongodb-org-mongos=3.2.12 mongodb-org-tools=3.2.12 \
# Install PHP and dependencies:
 && apt-get install -y php5.6-cli php5.6-dev php5.6-curl php5.6-xml php5.6-bcmath php5.6-mbstring \
# Setup mbstring function overload for all str functions to use mb_ variants
 && echo "mbstring.func_overload 7" >> /etc/php/5.6/cli/php.ini \
 && echo "mbstring.language Neutral" >> /etc/php/5.6/cli/php.ini \
# Install PHP mongo libs (echo 'no' declines sasl option prompt)
 && echo "no" | pecl install mongo \
 && echo "extension=mongo.so" >> /etc/php/5.6/cli/php.ini \
# Install log4php, pear reports error on install if package already exists
 && apt-get install -y php-pear \
 && pear channel-discover pear.apache.org/log4php \
 && pear install log4php/Apache_log4php \
 && apt-get install -y phpunit
RUN mkdir -p /data/db
ENTRYPOINT ["/CryptoArbitrage/deploy/docker_start.sh"]
