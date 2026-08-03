<?php

require("include/magpie.inc.php");

sw();
$debug   = $_GET['debug']   ?? 0;
$dist    = $_GET['dist']    ?? "";
$version = $_GET['version'] ?? "";
$json    = $_GET['json']    ?? 0;
$action  = $_GET['action']  ?? "";
$filter  = $_GET['filter']  ?? null;

// Build the URI link for the JSON API
$uri = $_SERVER['REQUEST_URI'] ?? "";
if (str_contains($uri, '?')) {
	$json_link = "$uri&json=1";
} else {
	$json_link = "$uri?json=1";
}

// This is the mod_rewrite URI format
if (!$dist) {
	$parts = get_uri_parts();

	$dist    = $parts[1] ?? "";
	$version = $parts[2] ?? "";
	$filter  = $filter   ?? $parts[3] ?? "";
	$filter  = urldecode($filter);

	// If the URI is: /dist/Foo-Bar/v0.1.2/Linux
	if ($filter) {
		$action = 'show_tests';
		$filter = "\b$filter\b"; // word boundaries
	}
}

// Allow Module::Sub in the URL also
$dist  = str_replace("::", "-", $dist);

if (!$dist) {
	error_out("Module not specified", 19034);
}

////////////////////////////////////////////////////////

$version_list = get_dist_versions($dist);
if (!$version) {
	$version = $version_list[0] ?? "";
}

$results    = get_test_results($dist, $version);
$test_stats = get_testing_stats($results);
$grade_cnt  = get_grade_count($results);
$grade_per  = get_grade_percent($results);
$os_cnt     = get_os_count($results);
$group      = group_test_results($results);
$dist_fmt   = str_replace("-", "::", $dist);

if (!$results) {
	error_out("No results found for distribution $dist", 12823);
}

$s->assign('dist', $dist);
$s->assign('dist_fmt', $dist_fmt);
$s->assign('dist_ver', $version);
$s->assign('grade_count', $grade_cnt);
$s->assign('grade_percent', $grade_per);
$s->assign('os_count', $os_cnt);
$s->assign('version_list', $version_list);
$s->assign('results', $group);
$s->assign('result_count', count($results));
$s->assign('json_link', $json_link);
$s->assign('stats', $test_stats);

// Use the raw results (not grouped)
if ($action === 'show_tests') {
	if ($filter) {
		$results = filter_results($filter, $results);
	}

	$s->assign('results', $results);
}

$ms = intval(sw());
$s->assign('page_ms', $ms);

////////////////////////////////////////////

if ($debug) {
	$s->assign('debug_output', k($s->tpl_vars, KRUMO_RETURN));
	$s->assign('query_summary', $dbq->query_summary());
} elseif ($json) {
	send_json($s->tpl_vars);
}

if ($action === 'show_tests') {
	print $s->fetch("tpls/dist_results.stpl");
} else {
	print $s->fetch("tpls/dist.stpl");
}

////////////////////////////////////////////////////////

function get_dist_versions($dist) {
	global $dbq;

	$sql = "SELECT distinct(distribution_version) as distribution_version
		FROM distribution_info
		WHERE distribution_name = ?
		ORDER BY distribution_version DESC;";
	$ret = $dbq->query($sql, [$dist], 'one_column');

	usort($ret, 'version_compare');
	$ret = array_reverse($ret);

	return $ret;
}

function group_test_results($data) {
	$ret = [];
	foreach ($data as $x) {
		$pver  = $x['perl_version'];
		$os    = $x['osname'];
		$grade = $x['grade'];

		incr($ret[$pver][$os][$grade], 1);
	}

	foreach ($ret as $pv => $x) {
		foreach ($x as $os => $y) {
			$total = array_sum($y);
			foreach (array_keys($y) as $grade) {
				$num = $ret[$pv][$os][$grade];
				$per = sprintf("%0.1f", ($num / $total) * 100);
				$ret[$pv][$os][$grade] = $per;
			}
		}
	}

	uksort($ret, 'version_compare');
	$ret = array_reverse($ret);

	return $ret;
}

function get_os_count($data) {
	$oses = [
		'windows' => 0,
		'bsd'     => 0,
		'darwin'  => 0,
		'linux'   => 0,
		'solaris' => 0,
	];

	foreach ($data as $x) {
		$y = $x['osname'];

		incr($oses[$y]);
	}

	return $oses;
}

function get_grade_count($data) {
	$grades = [
		'PASS'    => 0,
		'FAIL'    => 0,
		'NA'      => 0,
		'UNKNOWN' => 0,
	];

	foreach ($data as $x) {
		$y = $x['grade'];

		incr($grades[$y]);
	}

	return $grades;
}

function get_grade_percent($data) {
	$total = count($data);

	$grades = [];
	foreach ($data as $x) {
		$y = $x['grade'];

		incr($grades[$y]);
	}

	$ret = [];
	foreach ($grades as $grade => $count) {
		$grade       = strtolower($grade);
		$per         = sprintf("%0.1f", (($count / $total) * 100));
		$ret[$grade] = $per;
	}

	$sort['pass']    = $ret['pass']    ?? null;
	$sort['fail']    = $ret['fail']    ?? null;
	$sort['na']      = $ret['na']      ?? null;
	$sort['unknown'] = $ret['unknown'] ?? null;

	$sort = array_filter($sort);

	return $sort;
}

function version_sort($a, $b) {
	$aver = $a['perl_version'];
	$bver = $b['perl_version'];

	// Sort by version first, and then date
	if ($aver != $bver) {
		return version_compare($bver, $aver);
	} else {
		return ($b['unixtime'] <=> $a['unixtime']);
	}
}

function get_test_results($dist, $version) {
	global $dbq;

	$sql = "SELECT arch_name, distribution_name, grade, guid, osname, perl_version, tester, EXTRACT(EPOCH FROM test_ts) as unixtime, tester.name as tester_name, octet_length(txt_zstd) as x_test_bytes
		FROM test
		INNER JOIN tester ON (test.tester = tester.uuid)
		LEFT  JOIN test_results USING (guid)
		INNER JOIN distribution_info USING (distribution_id)
		INNER JOIN os_arch USING (arch_id)
		WHERE distribution_name = ? AND distribution_version = ?
		ORDER BY perl_version desc";

	$ret = $dbq->query($sql, [$dist, $version]);

	usort($ret, 'version_sort');

	return $ret;
}

function filter_results($filter, $data) {
	$ret = [];

	foreach ($data as $x) {
		$os     = $x['osname'];
		$grade  = strtolower($x['grade']);
		$pver   = $x['perl_version'];
		$tester = $x['tester_name'];
		$arch   = $x['arch_name'];

		// Build a string of all the pieces and run the filter against the string
		// This is ghetto but it works
		$str = "$pver;$os;$grade;$tester";

		if (preg_match("/$filter/i", $str)) {
			$ret[] = $x;
		}
	}

	return $ret;
}

function get_bad_test_uuids($data) {
	$review = [];
	foreach ($data as $x) {
		$grade = $x['grade'];
		$uuid  = $x['guid'];
		$bytes = $x['x_test_bytes'];

		if ($grade !== 'PASS' && $bytes < 128) {
			$review[] = $uuid;
		}
	}

	$ret = array_unique($review);

	return $ret;
}

function get_testing_stats($results) {
	$first_ut = 0;
	$last_ut  = 0;
	$data     = [];

	foreach ($results as $x) {
		$uuid = $x['guid'];
		$ut   = $x['unixtime'];
		$pver = $x['perl_version'];
		$os   = $x['osname'];

		if (!$first_ut || $first_ut > $ut) {
			$first_ut = $ut;
		}

		if (!$last_ut || $last_ut < $ut) {
			$last_ut = $ut;
		}

		// Build an array of all the combinations we've tested
		$str        = "$pver/$os";
		$data[$str] = 1;
	}

	$ret['first_test_time'] = human_time_diff($first_ut) . " ago";
	$ret['last_test_time']  = human_time_diff($last_ut) . " ago";
	$ret['configs_tested']  = count($data);

	if ($ret['configs_tested'] == 0) {
		$ret['first_test_time'] = "N/A";
		$ret['last_test_time']  = "N/A";
	}

	return $ret;
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
