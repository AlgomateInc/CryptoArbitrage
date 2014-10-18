<?php

$mongodb_uri = 'mongodb://localhost';
$mongodb_db = 'coindata';

$accountsConfig = array(
    'Btce' => array(
        'key' => 'BTCEKEY',
        'secret' => 'BTCESECRET'
    ),
    'Bitstamp'=> array(
        'key' => 'STAMPKEY',
        'secret' => 'STAMPSECRET',
        'custid' => 'STAMPCUSTID'
    ),
    'Cryptsy' => array(
        'key' => 'CRYPTSYKEY',
        'secret' => 'CRYPTSYSECRET'
    ),
    'JPMChase' => array(
        'name' => 'imapmailbox',
        'username' => 'username',
        'password' => 'password'
    )
);

$strategyInstructions = array(
    array(
        'name' => 'strategyclassname',
        'data' => array(
            'currencyPair'=>'BTCUSD',
            'from'=>'fromexchangename',
            'buySideRole'=>'Taker',
            'to'=>'toexchangename',
            'sellSideRole'=>'Taker',
            'factors'=>array(
                array('spreadPct'=>INF, 'maxUsdSize'=>50, 'sizeFactor'=> 1)
            )
        )
    )
);
?>
