<?php

function curl_query($url, $post_data = null, $headers = array()){
    static $ch = null;
    if (is_null($ch)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    if($post_data == null)
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    else
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $res = curl_exec($ch);
    if ($res === false) 
        throw new Exception('Could not get reply: '.curl_error($ch));

    $dec = json_decode($res, true);
    $err = json_last_error();
    if ($err !== JSON_ERROR_NONE) {
        throw new Exception("Invalid data received\nError: $err\nServer returned:\n $res");
    }
    return $dec;
}

?>
