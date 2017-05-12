<?php

$mongodb_uri = 'mongodb://mongo.caramila.capital';
$mongodb_db = 'coindata';

$log4phpConfig = array(
    'rootLogger' => array(
        'appenders' => array('default'),
    ),
    'appenders' => array(
        'default' => array(
            'class' => 'LoggerAppenderConsole',
            'layout' => array(
                'class' => 'LoggerLayoutPattern',
                'params' => array(
                    'conversionPattern' => '%date %logger %-5level %msg %e\Exception'
                )
            )
        )
    )
);

$accountsConfig = array(
//    'Btce' => array(
//        'key' => 'BTCEKEY',
//        'secret' => 'BTCESECRET'
//    ),
//    'Bitstamp'=> array(
//        'key' => 'STAMPKEY',
//        'secret' => 'STAMPSECRET',
//        'custid' => 'STAMPCUSTID'
//    ),
//    'Cryptsy' => array(
//        'key' => 'CRYPTSYKEY',
//        'secret' => 'CRYPTSYSECRET'
//    ),
//    'JPMChase' => array(
//        'name' => 'imapmailbox',
//        'username' => 'username',
//        'password' => 'password'
//    )
//    'Gdax' => array(
//        'key' => 'key',
//        'secret' => 'secret',
//        'passphrase' =>'passphrase'
//    )
//    'Yunbi'=> array(
//        'key' => 'key',
//        'secret' => 'secret',
//    ),
);

$strategyInstructions = array(
//    array(
//        'name' => 'strategyclassname',
//        'active' => true,
//        'data' => array(
//            'CurrencyPair'=>'BTCUSD',
//            'BuyExchange'=>'fromexchangename',
//            'BuySideRole'=>'Taker',
//            'SellExchange'=>'toexchangename',
//            'SellSideRole'=>'Taker',
//            'Factors'=>array(
//                array('TargetSpreadPct'=>INF, 'MaxUsdOrderSize'=>50, 'OrderSizeScaling'=> 1)
//            )
//        )
//    )
);
?>
