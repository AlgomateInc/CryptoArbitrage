<?php
/**
 * User: jon
 * Date: 4/4/2017
 */

use CryptoMarket\Exchange\IExchange;

use CryptoMarket\Record\FeeSchedule;
use CryptoMarket\Record\TradingRole;

class ExchangeManager {

    private $reporter;

    private $feeSchedules = array(); // assoc Exchange -> FeeSchedule
    const ForceReportAfterSeconds = 28800; //8 hours
    private $lastReportTime = array();

    function __construct(IReporter $reporter)
    {
        $this->reporter = $reporter;
    }

    function getFees()
    {
        return $this->feeSchedules;
    }

    function fetch($mkt)
    {
        if (!$mkt instanceof IExchange) {
            return;
        }
        $logger = Logger::getLogger(get_class($this));

        if(!$this->reporter instanceof IReporter)
            throw new \Exception('Invalid reporter object');

        //initialize local data structures
        if(!array_key_exists($mkt->Name(), $this->feeSchedules)){
            $this->feeSchedules[$mkt->Name()] = new FeeSchedule();
            $this->lastReportTime[$mkt->Name()] = time();
        }

        //get fees
        $curFeeSchedule;
        $removeMarket = false;
        try{
            $curFeeSchedule = $mkt->currentFeeSchedule();
        }catch(\Exception $e){
            $logger->warn('Problem getting fees for market: ' . $mkt->Name(), $e);
            $removeMarket = true;
        }

        //update our running list of fees
        $forceReport = false;
        if (time() > $this->lastReportTime[$mkt->Name()] + self::ForceReportAfterSeconds){
            $forceReport = true;
            $this->lastReportTime[$mkt->Name()] = time();
        }

        if (isset($curFeeSchedule)) {
            foreach ($mkt->supportedCurrencyPairs() as $pair) {
                $newMakerFee = $curFeeSchedule->getFee($pair, TradingRole::Maker);
                $newTakerFee = $curFeeSchedule->getFee($pair, TradingRole::Taker);

                //report fee only on a change (or first run, or periodically)
                $reportFees = false;
                if ($forceReport ||
                    $this->feeSchedules[$mkt->Name()]->isEmpty()) {
                    $reportFees = true;
                } else {
                    $oldMakerFee = $this->feeSchedules[$mkt->Name()]->getFee($pair, TradingRole::Maker);
                    $oldTakerFee = $this->feeSchedules[$mkt->Name()]->getFee($pair, TradingRole::Taker);
                    if ($newMakerFee != $oldMakerFee || $newTakerFee != $oldTakerFee) {
                        $reportFees = true;
                    }
                }
                if ($reportFees)
                    $this->reporter->fees($mkt->Name(), $pair, $newTakerFee, $newMakerFee);
            }

            $this->feeSchedules[$mkt->Name()] = $curFeeSchedule;
        }

        //remove if flagged
        if($removeMarket)
            unset($this->feeSchedules[$mkt->Name()]);
    }
}
