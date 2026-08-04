<?php

$BASE_DIR = __DIR__;

$checks = [];

//////////////////////////////////////////////////////////////////////////

$checks[] = [
	'name'   => 'PHP version',
	'status' => 'ok',
	'detail' => PHP_VERSION . ' (' . PHP_SAPI . ')',
];

//////////////////////////////////////////////////////////////////////////

$extensions = ['pdo', 'pdo_pgsql', 'memcached', 'zstd'];
foreach ($extensions as $ext) {
	if (extension_loaded($ext)) {
		$ver = phpversion($ext);

		$checks[] = [
			'name'   => "PHP extension: $ext",
			'status' => 'ok',
			'detail' => $ver ? "loaded (version $ver)" : 'loaded',
		];
	} else {
		$checks[] = [
			'name'   => "PHP extension: $ext",
			'status' => 'fail',
			'detail' => 'NOT loaded',
		];
	}
}

//////////////////////////////////////////////////////////////////////////

if (function_exists('apache_get_modules')) {
	$mods    = apache_get_modules();
	$rewrite = in_array('mod_rewrite', $mods);
	$ver     = function_exists('apache_get_version') ? apache_get_version() : '';

	$checks[] = [
		'name'   => 'Apache mod_rewrite',
		'status' => $rewrite ? 'ok' : 'fail',
		'detail' => ($ver ? "$ver - " : '') . ($rewrite ? 'mod_rewrite enabled' : 'mod_rewrite NOT enabled'),
	];
} else {
	$out = @shell_exec("httpd -M 2>/dev/null");

	if ($out !== null && trim($out) !== '') {
		$rewrite = preg_match('/rewrite_module/i', $out);

		$checks[] = [
			'name'   => 'Apache mod_rewrite',
			'status' => $rewrite ? 'ok' : 'fail',
			'detail' => ($rewrite ? 'mod_rewrite enabled' : 'mod_rewrite NOT enabled') . ' (httpd -M)',
		];
	} else {
		$checks[] = [
			'name'   => 'Apache mod_rewrite',
			'status' => 'unknown',
			'detail' => 'Cannot verify (PHP running as ' . PHP_SAPI . ' and <code>httpd -M</code> returned nothing)',
		];
	}
}

$htaccess = "$BASE_DIR/.htaccess";
if (is_readable($htaccess)) {
	$checks[] = [
		'name'   => '.htaccess',
		'status' => 'ok',
		'detail' => 'present and readable',
	];
} else {
	$checks[] = [
		'name'   => '.htaccess',
		'status' => 'fail',
		'detail' => 'missing or unreadable',
	];
}

//////////////////////////////////////////////////////////////////////////

if (extension_loaded('memcached')) {
	$mc = new Memcached();
	$mc->addServer('127.0.0.1', 11211);
	$mc->setOption(Memcached::OPT_CONNECT_TIMEOUT, 1000);

	$ver = $mc->getVersion();
	$ver = is_array($ver) ? reset($ver) : false;

	$key   = 'health_check_' . getmypid();
	$mc->set($key, 'ok', 10);
	$rc_set = $mc->getResultCode();
	$mc->get($key);
	$rc_get = $mc->getResultCode();
	$mc->delete($key);

	if ($ver && $rc_set === Memcached::RES_SUCCESS && $rc_get === Memcached::RES_SUCCESS) {
		$checks[] = [
			'name'   => 'Memcached',
			'status' => 'ok',
			'detail' => "127.0.0.1:11211 running (version $ver), set/get round-trip OK",
		];
	} elseif ($ver) {
		$checks[] = [
			'name'   => 'Memcached',
			'status' => 'fail',
			'detail' => "server reachable (version $ver) but set/get round-trip failed (rc=$rc_set/$rc_get)",
		];
	} else {
		$checks[] = [
			'name'   => 'Memcached',
			'status' => 'fail',
			'detail' => 'not running / unreachable at 127.0.0.1:11211',
		];
	}
}

//////////////////////////////////////////////////////////////////////////

$ini_file = "$BASE_DIR/include/magpie.config.ini";

if (!is_readable($ini_file)) {
	$checks[] = [
		'name'   => 'PostgreSQL',
		'status' => 'fail',
		'detail' => 'config file missing/unreadable - copy include/magpie.config.sample.ini to include/magpie.config.ini',
	];
} elseif (!extension_loaded('pdo_pgsql')) {
	$checks[] = [
		'name'   => 'PostgreSQL',
		'status' => 'fail',
		'detail' => 'pdo_pgsql extension not loaded',
	];
} else {
	$x   = parse_ini_file($ini_file, true);
	$dsn = "pgsql:host={$x['db']['host']};port={$x['db']['port']};dbname={$x['db']['dbname']}";

	try {
		$dbh = new PDO($dsn, $x['db']['username'], $x['db']['password'], [
			PDO::ATTR_TIMEOUT => 3,
		]);

		$ver = $dbh->query("SELECT version()")->fetchColumn();

		$checks[] = [
			'name'   => 'PostgreSQL',
			'status' => 'ok',
			'detail' => "connected to {$x['db']['dbname']} on {$x['db']['host']}:{$x['db']['port']} - $ver",
		];
	} catch (PDOException $e) {
		$checks[] = [
			'name'   => 'PostgreSQL',
			'status' => 'fail',
			'detail' => 'connection failed: ' . $e->getMessage(),
		];
	}
}

//////////////////////////////////////////////////////////////////////////

$dict = "$BASE_DIR/include/zstd-dict/magpie-dict-2025";
if (is_readable($dict)) {
	$checks[] = [
		'name'   => 'zstd dictionary',
		'status' => 'ok',
		'detail' => basename($dict) . ' present and readable',
	];
} else {
	$checks[] = [
		'name'   => 'zstd dictionary',
		'status' => 'fail',
		'detail' => basename($dict) . ' missing or unreadable',
	];
}

//////////////////////////////////////////////////////////////////////////

$fails = $unknowns = 0;
foreach ($checks as $c) {
	if ($c['status'] === 'fail') { $fails++; }
	if ($c['status'] === 'unknown') { $unknowns++; }
}

if ($fails) {
	$banner    = "$fails problem(s) found";
	$banner_bg = 'danger';
} elseif ($unknowns) {
	$banner    = "$unknowns check(s) could not be verified";
	$banner_bg = 'warning';
} else {
	$banner    = 'All systems go';
	$banner_bg = 'success';
}

$badge = [
	'ok'      => 'bg-success',
	'unknown' => 'bg-secondary',
	'fail'    => 'bg-danger',
];

$rows = '';
foreach ($checks as $c) {
	$status = strtoupper($c['status']);
	$rows  .= "					<tr>
						<td>{$c['name']}</td>
						<td><span class=\"badge {$badge[$c['status']]}\">$status</span></td>
						<td>{$c['detail']}</td>
					</tr>\n";
}

print <<<HTML
<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Magpie health check</title>
		<link href="/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
		<link href="/css/dist.css" rel="stylesheet">
	</head>
	<body class="">
		<h1 class="border-bottom mx-0 px-3 bg-primary-subtle pb-2 mb-3">Magpie health check</h1>

		<div class="container-lg">
			<div class="alert alert-$banner_bg">$banner</div>

			<table class="table">
				<thead>
					<tr>
						<th class="w-25">Check</th>
						<th>Status</th>
						<th>Details</th>
					</tr>
				</thead>
				<tbody>
$rows				</tbody>
			</table>
		</div>
	</body>
</html>
HTML;

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
