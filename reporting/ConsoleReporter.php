<?php

require_once('IReporter.php');

class ConsoleReporter implements IReporter
{
  public function balance($exchange_name, $currency, $balance){
    print("$exchange_name $currency Balance: $balance\n");
  }

  public function market($exchange_name, $currencyPair, $bid, $ask, $last){
    print("$exchange_name $currencyPair: Bid: $bid, Ask: $ask, Last: $last\n");
  }

  public function spread($buy_market_name, $sell_market_name, $difference){
    print "Buy $buy_market_name, Sell $sell_market_name: $difference\n";
  }

}

?>
