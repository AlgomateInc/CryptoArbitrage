<?php

interface IReporter
{
  public function balance($exchange_name, $currency, $balance);
  public function market($exchange_name, $currencyPair, $bid, $ask, $last);
  public function spread($buy_market_name, $sell_market_name, $difference);
}

?>
