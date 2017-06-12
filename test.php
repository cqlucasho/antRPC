<?php
require_once('client.php');

$client = new Client();
$client->setServiceServer('tcp://127.0.0.1:8089');
$result = $client->testData(array(1,2,3));
print_r(json_decode($result));
echo "\r\n";

$result1  = $client->testData1(array(1));
print_r(json_decode($result1));
echo "\r\n";