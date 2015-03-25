<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 10/4/2014
 * Time: 10:54 AM
 */

class MongoStrategyLoader implements IStrategyLoader{

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

        $strategyCollection = $this->mdb->strategies;
        $strategyList = $strategyCollection->find();
        foreach($strategyList as $dbStrategy){
            $s = new StrategyInstructions();
            $s->strategyId = $dbStrategy['_id'];
            $s->strategyName = $dbStrategy['Name'];
            $s->isActive = true;
            $s->data = $dbStrategy['Data'];

            if($s->isActive === true)
                $retArray[] = $s;
        }

        $arbCollection = $this->mdb->arbs;
        $arbList = $arbCollection->find();
        foreach($arbList as $arbStrategy){
            $s = new StrategyInstructions();
            $s->strategyId = $arbStrategy['_id'];
            $s->strategyName = 'ArbitrageStrategy';
            $s->isActive = true;
            $s->data = $arbStrategy;

            if($s->isActive === true)
                $retArray[] = $s;
        }

        return $retArray;
    }
}