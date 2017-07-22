<?php

namespace CryptoArbitrage\Helper;

use CryptoArbitrage\ActionProcess;

class CommandLineProcessor
{
    public static function processCommandLine($ap)
    {
        $objOptions = $ap->getAllProgramOptions();

        $shortOpts = '';
        $options = getopt($shortOpts, $objOptions);
        return $options;
    }
}
