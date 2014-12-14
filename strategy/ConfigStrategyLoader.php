<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 10/4/2014
 * Time: 10:51 AM
 */

require_once('IStrategyLoader.php');
require_once('StrategyInstructions.php');

class ConfigStrategyLoader implements IStrategyLoader{

    private $inst;

    public function __construct($configStrategyInstructions){
        $this->inst = array();

        foreach($configStrategyInstructions as $cfg)
        {
            $s = new StrategyInstructions();
            $s->strategyName = $cfg['name'];
            $s->isActive = $cfg['active'];
            $s->data = $cfg['data'];

            if($s->isActive === true)
                $this->inst[] = $s;
        }
    }

    public function load()
    {
        return $this->inst;
    }
}