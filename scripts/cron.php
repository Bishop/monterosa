<?php

require __DIR__ . '/../etc/environment.php';

define('STATUS_BAD_MEMCACHE', 1);
define('STATUS_BAD_MYSQL', 2);
define('STATUS_NO_DATA', 3);
define('STATUS_OK', 4);

function terminate($code) {
	$codes = array(
        STATUS_BAD_MEMCACHE => 'MemCache Error',
        STATUS_BAD_MYSQL => 'MySQL Error',
        STATUS_NO_DATA => 'No Data',
        STATUS_OK => 'Success',
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

$pop = memcache_get($mc, 'pop') or terminate(STATUS_BAD_MEMCACHE);
$put = memcache_get($mc, 'put') or terminate(STATUS_BAD_MEMCACHE);

$put > $pop or terminate(STATUS_NO_DATA);

$data = array();

$pop++;
for ($i = $pop; $i <= $put; $i++) {
    $data[] = memcache_get($mc, $i);
    memcache_delete($mc, $i);
}
echo $pop . ' ' . $put;
memcache_set($mc, 'pop', $put) or terminate(STATUS_BAD_MEMCACHE);

isset($config['db']) or terminate(STATUS_BAD_MYSQL);
$db_config = parse_url($config['db']) or terminate(STATUS_BAD_MYSQL);

(mysql_connect("{$db_config['host']}:{$db_config['port']}", $db_config['user'], @$db_config['pass'])
    && mysql_select_db(trim($db_config['path'], '/'))) or terminate(STATUS_BAD_MYSQL);

$chunk_size = 100;
foreach (array_chunk($data, $chunk_size) as $chunk) {
    $values = array();
    $a_len = count($chunk);
    for ($i = 0; $i < $a_len; ++$i) {
        $values[] = sprintf('(%s, \'%s\')', $chunk[$i][0], mysql_real_escape_string($chunk[$i][1]));
    }

    $query = 'insert emails (id, email) values ' . implode(', ', $values) . ' on duplicate key update email=values(email)';

    mysql_query($query) or terminate(STATUS_BAD_MYSQL);
}

terminate(STATUS_OK);