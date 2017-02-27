<?php

require __DIR__ . '/vendor/autoload.php'; // get auto-loading

// Mongo timestamps are in milliseconds, while PHP timestamps are in seconds
function mongoDateOfPHPDate($datetime)
{
    return $datetime * 1000;
}

?>
