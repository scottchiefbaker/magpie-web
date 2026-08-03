<?php

require("include/magpie.inc.php");

sw();
$debug = $_GET['debug'] ?? 0;
$json  = $_GET['json']  ?? 0;

////////////////////////////////////////////////////////

$stats = get_stats();
$ms    = intval(sw());

$s->assign('page_ms', $ms);
$s->assign('stats'  , $stats);

///////////////////////////////////

if ($json) {
	send_json($s->tpl_vars);
} elseif ($debug) {
	$s->assign('query_summary', $dbq->query_summary());
	$s->assign('debug_output', k($s->tpl_vars, KRUMO_RETURN));
}

print $s->fetch("tpls/index.stpl");

////////////////////////////////////////////////////////

function get_stats() {
	global $mc;

	// Allow me to pass in `?use_cache=0` to disable the cache temporarily
	$use_cache = $_GET['use_cache'] ?? 1;

	// TopX tables are lighter and don't need to be cached as long
	$ckey = 'index_stats_topx';
	$topx = $mc->get($ckey);
	$ret  = [];

	if ($use_cache && $topx) {
		$ret = $topx;
	} else {
		$ret["last_hour"] = get_top_x(10, time() - 3600);
		$ret["last_day"]  = get_top_x(10, time() - 86400);

		$ok = $mc->set($ckey, $ret, 60);
	}

	////////////////////////////////////////////////////////////////////////////////

	// The daily stats don't change that much and take longer to generate.
	// These take about 250ms to generate on 2025-05-22 with 1.5 million entries
	// in the DB
	$ckey = 'index_stats_counts';
	$cnts = $mc->get($ckey);

	if ($use_cache && $cnts) {
		$ret = array_merge($ret, $cnts);
	} else {
		$ret['count_hour']  = get_count(time() - 3600         , time());
		$ret['count_day']   = get_count(time() - 86400        , time());
		$ret['count_week']  = get_count(time() - 86400 * 7    , time());
		$ret['count_month'] = get_count(time() - 86400 * 30.41, time());
		$ret['count_total'] = get_count(0, time());

		// Cache for six hours
		$ok = $mc->set($ckey, $ret, 3600 * 6);
	}

	return $ret;
}

function get_count($start, $end) {
	global $dbq;

	$ds = date("Y-m-d H:i:s", $start);
	$de = date("Y-m-d H:i:s", $end);

	$sql = "SELECT count(guid)
		FROM test
		WHERE test_ts > ? and test_ts < ?";

	$ret = $dbq->query($sql, [$ds, $de], 'one_data');

	return $ret;
}

function get_top_x(int $number, int $time) {
	global $dbq;

	$time_str = date("Y-m-d H:i:s", $time);

	$sql = "SELECT count(guid), distribution_name
		FROM test
		INNER JOIN distribution_info USING (distribution_id)
		WHERE test_ts > ?
		GROUP BY distribution_name
		ORDER BY 1 desc
		LIMIT ?;";

	$ret = $dbq->query($sql, [$time_str, $number]);

	return $ret;
}

function get_test_results($dist, $time) {
	global $dbq;

	$time_str = date("Y-m-d H:i:s", $time);

	$sql = "SELECT *
		FROM test
		WHERE test_ts > ? AND distribution = ?
		ORDER BY 1 desc";

	$ret = $dbq->query($sql, [$time_str, $dist]);

	return $ret;
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
