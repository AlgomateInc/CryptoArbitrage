<?php

$btce_key = 'BTCEKEY'; // your API-key
$btce_secret = 'BTCESECRET'; // your Secret-key

$bitstamp_custid = 'STAMPCUSTID';
$bitstamp_key = 'STAMPKEY'; // your API-key
$bitstamp_secret = 'STAMPSECRET'; // your Secret-key

$cryptsy_key = 'CRYPTSYKEY';
$cryptsy_secret = 'CRYPTSYSECRET';

$mongodb_uri = 'mongodb://localhost';
$mongodb_db = 'coindata';

$mailbox_name = 'imapmailbox';
$mailbox_username = 'username';
$mailbox_password = 'password';

$arbInstructions = array(
    array(
        'currencyPair'=>'BTCUSD',
        'from'=>'fromexchangename',
        'to'=>'toexchangename',
        'factors'=>array(
            array('spreadPct'=>INF, 'maxUsdSize'=>50, 'sizeFactor'=> 1)
        )
    )
);
?>
