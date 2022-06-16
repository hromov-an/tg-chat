<?php

use Anton\Tg\DB;
use Anton\Tg\TelegramBot;

require_once('../vendor/autoload.php');

try {
    $db = new DB;
    $db->createTables();
}
catch (PDOException $e) {
    die("DB ERROR: " . $e->getMessage());
}


$bot = new TelegramBot();

while (true) {
    $bot->startChat();
    sleep(1);
}