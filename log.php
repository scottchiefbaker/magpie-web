<?php

require("include/magpie.inc.php");

sw();
$debug  = $_GET['debug']  ?? 0;
$json   = $_GET['json']   ?? 0;
$count  = $_GET['count']  ?? 500;
$offset = $_GET['offset'] ?? 0;

if ($count > 10000) { die; }

////////////////////////////////////////////////////////

$grade = $_GET['grade'] ?? '';
$log   = $magpie->log->get($count, $offset, $grade);

$ms = intval(sw());
$s->assign('page_ms', $ms);
$s->assign('log', $log);

///////////////////////////////////

if ($json) {
	send_json($s->tpl_vars);
} elseif ($debug) {
	$s->assign('debug_output', k($s->tpl_vars, KRUMO_RETURN));
}

print $s->fetch("tpls/log.stpl");

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
