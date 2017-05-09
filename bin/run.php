#!/usr/local/bin/php
<?php

use Dotenv\Dotenv;
use Packaged\Figlet\Figlet;
use XStalker\Listener;

require __DIR__.'/../vendor/autoload.php';

$version = "1.0.0";

// initialize environment variables
$dotenv = new Dotenv(__DIR__.'/..');
$dotenv->load();

// banner
$figlet = new Figlet('shadow', 'shadow');
$banner = $figlet->render('Tokenly Bitcoin Stalker');
$sep = str_repeat('-', strlen(explode("\n", $banner)[0]))."\n";
print $sep.$banner."v{$version}\n".$sep;


// init listener
$listener = new Listener();

// run
$listener->run();
echo "done\n";

