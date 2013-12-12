<?php

require_once('ActionProcess.php');

require('markets/btce.php');
require('markets/bitstamp.php');
require('markets/jpmchase.php');

class TransactionMonitor extends ActionProcess{

    private $exchanges;

    public function __construct($exchanges)
    {
        $this->exchanges = $exchanges;
    }

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
            if(!$mkt instanceof IAccount)
                continue;

            $txList = $mkt->transactions();

            foreach($txList as $tx)
                if($tx instanceof Transaction)
                    $this->reporter->transaction(
                        $tx->exchange,
                        $tx->id,
                        $tx->type,
                        $tx->currency,
                        $tx->amount,
                        $tx->timestamp
                    );
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

$txMon = new TransactionMonitor($exchanges);
$txMon->start();