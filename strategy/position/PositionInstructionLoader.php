<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 12/8/2014
 * Time: 11:57 PM
 */

require_once(__DIR__ . '/../IStrategyInstructions.php');
require_once('LimitOrderInstructions.php');
require_once('FragmentOrderInstructions.php');

class PositionInstructionLoader {
    public function load($data)
    {
        $instType = null;
        if(isset($data['InstructionType']))
            $instType = $data['InstructionType'];
        else
            $instType = $data['_t'];

        $inst = new $instType;
        if(!$inst instanceof IStrategyInstructions)
            throw new Exception('Could not load strategy instructions...');

        $inst->load($data);

        return $inst;
    }
} 