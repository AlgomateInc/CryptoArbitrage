<?php

function curl_query($url){
    static $ch = null;
    if (is_null($ch)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $res = curl_exec($ch);
    if ($res === false) 
        throw new Exception('Could not get reply: '.curl_error($ch));
    $dec = json_decode($res, true);
    if ($dec === NULL)
        throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
    return $dec;
}

?>
