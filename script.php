<?php
declare(strict_types=1);
/*
 * @author Bc. Josef Jebavý
 * 2021-05-17
 */

require 'vendor/autoload.php';

use GuzzleHttp\Client;



if (!(count($argv) == 2)) {
    echo "please add one param: ID top-level group\n";

    exit(1);
}

$id = intval($argv[1]);



