<?php

require_once('ActionProcess.php');

class TradeDataDumper extends ActionProcess {

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
            if(!$mkt instanceof BitstampExchange)
                continue;

            foreach($mkt->tradeHistory() as $tx){

                if($tx instanceof Trade)
                    $this->reporter->trade(
                        $mkt->Name(),
                        $tx->currencyPair,
                        null,
                        null,
                        $tx->orderType,
                        $tx->price,
                        $tx->quantity,
                        $tx->timestamp
                    );
            }
        }
    }

    public function shutdown()
    {

    }
}

$tdDumper = new TradeDataDumper();
$tdDumper->start();