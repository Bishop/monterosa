<?php

require __DIR__ . '/../etc/environment.php';

define('STATUS_BAD_MEMCACHE', 1);
define('STATUS_BAD_MYSQL', 2);
define('STATUS_NO_DATA', 3);

function terminate($code) {
	$codes = array(
        STATUS_BAD_MEMCACHE => 'MemCache Error',
        STATUS_BAD_MYSQL => 'MySQL Error',
        STATUS_NO_DATA => 'No Data',
	);

	if ($f = fopen(__DIR__ . '/../var/log/cron.log', 'ab')) {
        fwrite($f, sprintf("%s: %s\n", date('Y-m-d H:i:s O'), $codes[$code]));
        fclose($f);
    }
    exit();
}

isset($config['cache']) or terminate(STATUS_BAD_MEMCACHE);
$cache_config = parse_url($config['cache']) or terminate(STATUS_BAD_MEMCACHE);

$mc = memcache_connect($cache_config['host'], $cache_config['port']) or terminate(STATUS_BAD_MEMCACHE);

$pop = 0;

memcache_add($mc, 'pop', $pop, false);

$put = memcache_get($mc, 'put') or terminate(STATUS_BAD_MEMCACHE);

$put > $pop or terminate(STATUS_NO_DATA);

$data = array();

$pop++;
for ($i = $pop; $i <= $put; $i++) {
    $data[] = memcache_get($mc, $i);
}

memcache_set($mc, 'pop', $put) or terminate(STATUS_BAD_MEMCACHE);

var_dump($data);