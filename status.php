<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = '202.155.8.99';
$port = 50123;
$timeout = 3;

$start = microtime(true);
$connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
$time = round((microtime(true) - $start) * 1000, 0);

if ($connection) {
    fclose($connection);
    echo json_encode(['status' => 'up', 'time' => $time, 'message' => "LIVE! {$time}ms"]);
} else {
    echo json_encode(['status' => 'down', 'time' => $time, 'message' => "DOWN! {$time}ms"]);
}
?>