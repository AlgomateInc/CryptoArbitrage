#!/usr/bin/env bash
/usr/bin/mongod & sleep 5
/usr/bin/php /CryptoArbitrage/MarketDataMonitor.php --mongodb --monitor=10 --discard-depth &
/usr/bin/php /CryptoArbitrage/StrategyProcessor.php --mongodb --monitor --live &
/usr/bin/php /CryptoArbitrage/ReportingServer.php --mongodb --monitor=0
