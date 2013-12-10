<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 11/15/13
 * Time: 1:58 PM
 */

class MongoArbInstructionLoader implements IArbInstructionLoader {

    private $mongo;
    private $mdb;

    public function __construct(){
        global $mongodb_uri, $mongodb_db;

        $this->mongo = new MongoClient($mongodb_uri);
        $this->mdb = $this->mongo->selectDB($mongodb_db);
    }

    public function load()
    {
        $retArray = array();

        $arbCollection = $this->mdb->arbs;

        $arbList = $arbCollection->find();
        foreach($arbList as $arb){
            $ai = new ArbInstructions();
            $ai->currencyPair = $arb['CurrencyPair'];
            $ai->buyExchange = $arb['BuyExchange'];
            $ai->sellExchange = $arb['SellExchange'];

            foreach($arb['Factors'] as $fctr){
                $fi = new ArbExecutionFactor();

                $fi->targetSpreadPct = $fctr['TargetSpreadPct'];
                $fi->maxUsdOrderSize = $fctr['MaxUsdOrderSize'];
                $fi->orderSizeScaling = $fctr['OrderSizeScaling'];

                $ai->arbExecutionFactorList[] = $fi;
            }

            $retArray[] = $ai;
        }

        return $retArray;
    }
}