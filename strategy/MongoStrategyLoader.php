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

    public function __construct($mongodbUri, $mongodbName){
        $this->mongo = new MongoDB\Client($mongodbUri);
        $this->mdb = $this->mongo->selectDatabase($mongodbName);
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
            $s->isActive = $dbStrategy['Active'];
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
