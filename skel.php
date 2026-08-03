<?php

require("include/magpie.inc.php");

sw();
$debug = $_GET['debug'] ?? 0;
$json  = $_GET['json']  ?? 0;

////////////////////////////////////////////////////////

$ms = sw();
$s->assign('page_ms', $ms);

///////////////////////////////////

if ($json) {
	send_json($s->tpl_vars);
} elseif ($debug) {
	$s->assign('debug_output', k($s->tpl_vars, KRUMO_RETURN));
}

print $s->fetch("tpls/skel.stpl");

////////////////////////////////////////////////////////

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
