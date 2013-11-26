<?php

require_once('ActionProcess.php');

require('markets/btce.php');
require('markets/bitstamp.php');
require('markets/jpmchase.php');

class TransactionMonitor extends ActionProcess{

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
        $exchanges = array();
        $exchanges[Exchange::Btce] = new BtceExchange();
        $exchanges[Exchange::Bitstamp] = new BitstampExchange();
        //$exchanges[Exchange::JPMChase] = new JPMChase($mailbox_name, $mailbox_username, $mailbox_password);

        foreach($exchanges as $mkt)
        {
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

$txMon = new TransactionMonitor();
$txMon->start();