<?php

require("include/magpie.inc.php");

sw();
$debug = $_GET['debug'] ?? 0;
$json  = $_GET['json']  ?? 0;

////////////////////////////////////////////////////////

$memcached = new Memcached();
$memcached->addServer('127.0.0.1', 11211);

$stats = $memcached->getStats();
$stats = $stats['127.0.0.1:11211'] ?? [];

$stats['avg_item_size'] = intval($stats['bytes'] / $stats['total_items']);
$stats['avg_item_size'] = human_size($stats['avg_item_size']);

$total_hits            = $stats['get_hits'] + $stats['get_misses'];
$stats['hit_percent']  = sprintf("%0.2f",($stats['get_hits'] / $total_hits) * 100);
$stats['miss_percent'] = sprintf("%0.2f",($stats['get_misses'] / $total_hits) * 100);

$stats['used_percent'] = sprintf("%0.2f",($stats['bytes'] / $stats['limit_maxbytes']) * 100);

//$keys = $memcached->getAllKeys();
//k($keys);

$ms = sw();
$s->assign('page_ms', $ms);
$s->assign('stats'  , $stats);

///////////////////////////////////

if ($json) {
	send_json($s->tpl_vars);
} elseif ($debug) {
	$s->assign('debug_output', k($s->tpl_vars, KRUMO_RETURN));
}

print $s->fetch("tpls/mc_stats.stpl");

////////////////////////////////////////////////////////

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
