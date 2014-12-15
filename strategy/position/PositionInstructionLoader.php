<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 12/8/2014
 * Time: 11:57 PM
 */

require_once(__DIR__ . '/../IStrategyInstructions.php');
require_once('SimpleOrderInstructions.php');
require_once('FragmentOrderInstructions.php');

class PositionInstructionLoader {
    public function load($data)
    {
        $instType = $data['InstructionType'];

        $inst = new $instType;
        if(!$inst instanceof IStrategyInstructions)
            throw new Exception('Could not load strategy instructions...');

        $inst->load($data);

        return $inst;
    }
} 