<?php

require __DIR__ . '/../etc/environment.php';

$config = array(
	'id_min' => 1,
	'id_max' => 65535,
	'email_max' => 100,
);

define('RESPONSE_OK', 200);
define('RESPONSE_BAD_REQUEST', 400);
define('RESPONSE_BAD_SERVER', 500);

function response($code)
{
	$response_codes = array(
		RESPONSE_OK => 'HTTP/1.1 200 OK',
		RESPONSE_BAD_REQUEST => 'HTTP/1.1 400 Bad Request',
		RESPONSE_BAD_SERVER => 'HTTP/1.1 500 Internal Server Error',
	);

	header($response_codes[$code]);
	exit();
}

$_SERVER['REQUEST_METHOD'] === 'GET' or response(RESPONSE_BAD_REQUEST);

(isset($_GET['id']) && isset($_GET['email'])) or response(RESPONSE_BAD_REQUEST);

$id = (int)$_GET['id'];
$email = (string)$_GET['email'];
$email_strlen = mb_strlen($email);

($id >= $config['id_min'] && $id <= $config['id_max']) or response(RESPONSE_BAD_REQUEST);
($email_strlen > 0 && $email_strlen <= $config['email_max']) or response(RESPONSE_BAD_REQUEST);

$mc = memcache_connect(MC_HOST, MC_PORT) or response(RESPONSE_BAD_SERVER);

memcache_add($mc, 'put', 0, false);

$key = memcache_increment($mc, 'put') or response(RESPONSE_BAD_SERVER);

memcache_set($mc, $key, array(&$id, &$email)) or response(RESPONSE_BAD_SERVER);

response(RESPONSE_OK);