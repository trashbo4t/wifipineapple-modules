<?php namespace headers;

header('Content-Type: application/json');

require_once("API.php");
$api = new API();
echo $api->go();
