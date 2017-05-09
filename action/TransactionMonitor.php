<?php

require_once('ActionProcess.php');

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

$txMon = new TransactionMonitor();
$txMon->start();