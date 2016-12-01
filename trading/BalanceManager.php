<?php
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 7/16/2015
 * Time: 10:22 AM
 */

class BalanceManager {

    private $reporter;

    private $balances = array();
    private $lastReportTime;
    const ForceReportAfterSeconds = 28800; //8 hours

    function __construct(IReporter $reporter)
    {
        $this->reporter = $reporter;
        $this->lastReportTime = time();
    }

    function get($marketName, $currencyPair)
    {
        return $this->balances[$marketName][$currencyPair];
    }

    function getBalances()
    {
        return $this->balances;
    }

    function fetch(IAccount $mkt)
    {
        $logger = Logger::getLogger(get_class($this));

        if(!$this->reporter instanceof IReporter)
            throw new Exception('Invalid reporter object');

        //initialize local data structures
        if(!array_key_exists($mkt->Name(), $this->balances))
            $this->balances[$mkt->Name()] = array();

        //get balances
        $balList = array();
        $removeMarket = false;
        try{
            $balList = $mkt->balances();
        }catch(Exception $e){
            $logger->warn('Problem getting balances for market: ' . $mkt->Name(), $e);
            $removeMarket = true;
        }

        //update our running list of balances
        $forceReport = false;
        if(time() > $this->lastReportTime + self::ForceReportAfterSeconds){
            $forceReport = true;
            $this->lastReportTime = time();
        }
        foreach($balList as $cur => $bal){
            //report balance only on balance change (or first run, or periodically)
            if(!isset($this->balances[$mkt->Name()][$cur]) ||
                $this->balances[$mkt->Name()][$cur] != $bal || $forceReport)
                $this->reporter->balance($mkt->Name(), $cur, $bal);

            $this->balances[$mkt->Name()][$cur] = $bal;
        }

        //check to see if there are old balances that don't exist anymore
        //happens if a balance becomes zero after a sale. we need to report this
        foreach($this->balances[$mkt->Name()] as $cur => $bal){
            if(!array_key_exists($cur, $balList))
            {
                unset($this->balances[$mkt->Name()][$cur]);
                $this->reporter->balance($mkt->Name(), $cur, 0);
            }
        }

        //remove if flagged
        if($removeMarket)
            unset($this->balances[$mkt->Name()]);
    }
}