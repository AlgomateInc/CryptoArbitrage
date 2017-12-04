<?php

class ConfigDataExample
{
    const MONGODB_URI = 'mongodb://localhost';
    const MONGODB_DBNAME = 'coindata';

    const LOG4PHP_CONFIG = array(
        'rootLogger' => array(
            'appenders' => array('default'),
        ),
        'appenders' => array(
            'default' => array(
                'class' => 'LoggerAppenderConsole',
                'layout' => array(
                    'class' => 'LoggerLayoutPattern',
                    'params' => array(
                        'conversionPattern' => '%date %logger %-5level %msg %exception'
                    )
                )
            )
        )
    );

    const ACCOUNTS_CONFIG = array(
//        'Btce' => array(
//            'key' => '',
//            'secret' => ''
//        ),
        'Bitfinex'=> array(
            'key' => '',
            'secret' => ''
        ),
        'Bitstamp'=> array(
            'key' => '',
            'secret' => '',
            'custid' => ''
        ),
//        'BitVC'=> array(
//            'key' => '',
//            'secret' => '',
//        ),
//      'JPMChase' => array(
//          'name' => 'imapmailbox',
//          'username' => 'username',
//          'password' => 'password'
//      ),
        'Gdax'=> array(
            'key' => '',
            'secret' => '',
            'passphrase' =>''
        ),
        'Gemini'=> array(
            'key' => '',
            'secret' => ''
        ),
        'Kraken'=> array(
            'key' => '',
            'secret' => ''
        ),
        'Poloniex'=> array(
            'key' => '',
            'secret' => ''
        ),
//        'Yunbi'=> array(
//            'key' => '',
//            'secret' => '',
//        ),
    );

    const STRATEGY_INSTRUCTIONS = array(
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
}

