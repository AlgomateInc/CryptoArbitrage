<?php

require_once('ActionProcess.php');

class TransactionDataDumper extends ActionProcess {

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
            if(!$mkt instanceof BtceExchange)
                continue;

            foreach($mkt->transactionHistory() as $tx){

                if($tx instanceof Transaction)
                    $this->reporter->transaction(
                        $mkt->Name(),
                        $tx->id,
                        $tx->type,
                        $tx->currency,
                        $tx->amount,
                        $tx->timestamp
                    );
            }
        }
    }

    public function shutdown()
    {

    }
}

$tdDumper = new TransactionDataDumper();
$tdDumper->start();