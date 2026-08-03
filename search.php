<?php

require("include/magpie.inc.php");

sw();
$debug = $_GET['debug'] ?? 0;
$json  = $_GET['json']  ?? 0;
$str   = $_GET['str']   ?? "";

if (!$str) {
	$parts = get_uri_parts();
	$str   = $parts[1] ?? "";
}

// Allow searching for Foo::Bar and Foo-Bar
$str = preg_replace("/::/", "-", $str);

if (is_uuid($str)) {
	header("Location: /results/$str");
	exit;
}

////////////////////////////////////////////////////////

$x       = get_all_dists();
$res     = filter_results($x, $str);
$res_fmt = highlight_results($res, $str);

$ms = sw();
$s->assign('page_ms'    , $ms);
$s->assign('search_str' , $str);
$s->assign('results'    , $res);
$s->assign('results_fmt', $res_fmt);

///////////////////////////////////

if ($json) {
	send_json($s->tpl_vars);
} elseif ($debug) {
	$s->assign('debug_output', k($s->tpl_vars, KRUMO_RETURN));
}

print $s->fetch("tpls/search.stpl");

////////////////////////////////////////////////////////

function filter_results($all, $filter) {
	if (!$filter) { return []; }

	$filter = preg_quote($filter);

	// Filter the big list down to the matches
	$ret = preg_grep("/$filter/i", $all);

	// Put the ones that START with the filter at the front
	// to prioritize them
	$startw = [];
	$other  = [];
	foreach ($ret as $x) {
		// See if the string starts with the filter (case-insensitive)
		if (preg_match("/^$filter/i", $x)) {
			$startw[] = $x;
		} else {
			$other[] = $x;
		}
	}

	sort($startw);
	sort($other);

	$ret = array_merge($startw, $other);
	// Limit results to the first 50
	$ret = array_slice($ret, 0, 50);

	return $ret;
}

function highlight_results($input, $filter = "ran") {
	$ret = preg_replace("/($filter)/i", "<span class=\"search_highlight\">$1</span>", $input);

	return $ret;
}

function get_all_dists() {
	global $dbq, $mc;

	$ckey = "all_modules";

	if (!empty($_GET['no_cache'])) {
		$data = [];
	} else {
		$data = $mc->get($ckey);
	}

	// Items are in the DB with `-` separator
	// Find all the entries in the DB that match
	if (!$data) {
		$sql = "SELECT distinct(distribution_name) FROM distribution_info;";
		$ret = $dbq->query($sql, 'one_column');
	} else {
		$ret = $data;
	}

	$mc->set($ckey, $ret, 900);

	return $ret;
}

function module_search($str) {
	global $dbq, $mc;

	$start = microtime(1);

	$ckey = "mod_search:$str";
	$data = $mc->get($ckey);

	$dist   = preg_replace("/::/", "-", $str);
	$module = preg_replace("/-/", "::", $str);

	// Items are in the DB with `-` separator
	// Find all the entries in the DB that match
	if (!$data) {
		$sql  = "SELECT distinct(distribution) FROM test WHERE distribution ILIKE '%$dist%';";
		$data = $dbq->query($sql, 'one_column');
	}

	// Loop through and sort the results so anything that STARTS with the
	// search time gets prioritized over matches with the search in the
	// middle of the string
	$start_with = [];
	$other      = [];
	foreach ($data as $name) {
		$name = dist_to_human($name);
		if (preg_match("/^$module/i", $name)) {
			$start_with[] = $name;
		} else {
			$other[] = $name;
		}
	}

	sort($start_with);
	sort($other);
	$ret = array_merge($start_with, $other);

	$total_ms = intval((microtime(1) - $start) * 1000);

	$count = count($ret);
	$msg   = "Found $count entries for '$str' in $total_ms ms";
	mplog($msg);

	$mc->set($ckey, $ret, 900);

	// Limit results to 50
	$ret = array_slice($ret, 0, 50);
	kd($ret);

	return $ret;
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
