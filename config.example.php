<?php

$btce_key = 'BTCEKEY'; // your API-key
$btce_secret = 'BTCESECRET'; // your Secret-key

$bitstamp_custid = 'STAMPCUSTID';
$bitstamp_key = 'STAMPKEY'; // your API-key
$bitstamp_secret = 'STAMPSECRET'; // your Secret-key

$mongodb_uri = 'mongodb://localhost';

$arbInstructions = array(
    array(
        'from'=>'fromexchangename',
        'to'=>'toexchangename',
        'factors'=>array(
            array('spreadPct'=>INF, 'maxUsdSize'=>50, 'sizeFactor'=> 1)
        )
    )
);
?>
