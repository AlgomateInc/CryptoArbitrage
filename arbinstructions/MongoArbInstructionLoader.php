<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 11/15/13
 * Time: 1:58 PM
 */

use CryptoMarket\AccountLoader\ConfigData;

class MongoArbInstructionLoader implements IArbInstructionLoader {

    private $mongo;
    private $mdb;

    public function __construct(){
        $this->mongo = new MongoClient(ConfigData::mongodb_uri);
        $this->mdb = $this->mongo->selectDB(ConfigData::mongodb_db);
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
