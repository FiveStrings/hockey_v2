<?php

require_once('Zebra_Database.php');

$db = new Zebra_Database();
$db->debug = true;
$db->resource_path = 'hockey2/';
$db->connect('localhost', 'root', 'elias626', 'hockey_v2');
?>
