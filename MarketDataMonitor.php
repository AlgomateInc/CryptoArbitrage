<?php

require_once('ActionProcess.php');

require('markets/btce.php');
require('markets/bitstamp.php');
require('markets/jpmchase.php');

class MarketDataMonitor extends ActionProcess {

    public function getProgramOptions()
    {
    }

    public function processOptions($options)
    {
    }

    public function init()
    {
    }

    public function run()
    {
        if(!$this->reporter instanceof IReporter)
            throw new Exception('Reporter is not the right type!');

        foreach($this->exchanges as $mkt)
        {
            if(!$mkt instanceof IExchange)
                continue;

            foreach($mkt->supportedCurrencyPairs() as $pair){
                $tickData = $mkt->ticker($pair);

                if($tickData instanceof Ticker)
                    $this->reporter->market(
                        $mkt->Name(),
                        $tickData->currencyPair,
                        $tickData->bid,
                        $tickData->ask,
                        $tickData->last
                    );
            }
        }
    }

    public function shutdown()
    {
    }
}

$exchanges = array();
$exchanges[Exchange::Btce] = new BtceExchange($btce_key, $btce_secret);
$exchanges[Exchange::Bitstamp] = new BitstampExchange($bitstamp_custid, $bitstamp_key, $bitstamp_secret);
$exchanges[Exchange::JPMChase] = new JPMChase($mailbox_name, $mailbox_username, $mailbox_password);

$txMon = new MarketDataMonitor($exchanges);
$txMon->start();