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

    public function __construct($mongodbUri, $mongodbName){
        $this->mongo = new MongoClient($mongodbUri);
        $this->mdb = $this->mongo->selectDB($mongodbName);
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

            $ai->buySideRole = $arb['BuySideRole'];
            $ai->sellSideRole = $arb['SellSideRole'];

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
