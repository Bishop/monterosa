<?php

require __DIR__ . '/../etc/environment.php';

define('STATUS_BAD_MEMCACHE', 1);
define('STATUS_BAD_MYSQL', 2);
define('STATUS_NO_DATA', 3);
define('STATUS_OK', 4);
define('STATUS_ALREADY_RUNNING', 5);

function terminate($code)
{
	$codes = array(
		STATUS_BAD_MEMCACHE => 'MemCache Error',
		STATUS_BAD_MYSQL => 'MySQL Error',
		STATUS_NO_DATA => 'No Data',
		STATUS_OK => 'Success',
		STATUS_ALREADY_RUNNING => 'Already Running',
	);

	if ($f = fopen(__DIR__ . '/../var/log/cron.log', 'ab')) {
		fwrite($f, sprintf("%s: %s\n", date('Y-m-d H:i:s O'), $codes[$code]));
		fclose($f);
	}
	exit();
}

$pid_file = __DIR__ . '/../var/run/cron.pid';
if (file_exists($pid_file)) {
	$pid = (int)file_get_contents($pid_file);
	if (file_exists("/proc/$pid")) {
		terminate(STATUS_ALREADY_RUNNING);
	} else {
		unlink($pid_file);
	}
}

file_put_contents($pid_file, strval(posix_getpid()));
register_shutdown_function(function() use ($pid_file) { unlink($pid_file); });

$mc = memcache_connect(MC_HOST, MC_PORT) or terminate(STATUS_BAD_MEMCACHE);
$pop = 1;

memcache_add($mc, 'pop', $pop, false);

while (true) {
	$pop = memcache_get($mc, 'pop') or terminate(STATUS_BAD_MEMCACHE);
	$put = memcache_get($mc, 'put') or terminate(STATUS_BAD_MEMCACHE);
	memcache_set($mc, 'pop', $put + 1) or terminate(STATUS_BAD_MEMCACHE);

	$put >= $pop or terminate(STATUS_NO_DATA);

	$data = array();

	for ($i = $pop; $i <= $put; $i++) {
		$data[] = memcache_get($mc, $i);
		memcache_delete($mc, $i);
	}

	(mysql_connect(DB_HOST . ':' . DB_PORT, DB_USER, DB_PASS)
		&& mysql_select_db(DB_NAME)) or terminate(STATUS_BAD_MYSQL);

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
}
terminate(STATUS_OK);