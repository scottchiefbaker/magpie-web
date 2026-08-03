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
$log   = get_log($count, $offset, $grade);

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

////////////////////////////////////////////////////////

function get_log($count, $offset, $grade) {
	global $dbq;

	// If the grade string starts with a ! that means we invert the results
	// to only show the ones that DO NOT match the filter
	//
	// Note: This is all items or nothing
	if (str_starts_with($grade, "!")) {
		$grade  = substr($grade, 1);
		$invert = true;
	} else {
		$invert = false;
	}

	// Split at the commas and only get non-empty ones
	$grade = preg_split("/,/", $grade, 0, PREG_SPLIT_NO_EMPTY);

	if ($grade) {
		// preg_quote() each item
		foreach ($grade as &$x) {
			$x = $dbq->dbh->quote($x);
		}

		$grade_str = join(",", $grade);

		if ($invert) {
			$filter = "WHERE grade NOT IN ($grade_str)";
		} else {
			$filter = "WHERE grade IN ($grade_str)";
		}
	} else {
		$filter = "";
	}

	$sql = "SELECT distribution_name, grade, EXTRACT(EPOCH FROM test_ts) as unixtime, distribution_version,
					osname, guid, octet_length(txt_zstd) as test_bytes
		FROM test
		INNER JOIN distribution_info USING (distribution_id)
		LEFT  JOIN test_results USING (guid)
		$filter
		ORDER BY test_ts DESC
		LIMIT ?
		OFFSET ?;";

	$ret = $dbq->query($sql, [$count, $offset]);

	// Normalize OS names
	foreach ($ret as &$x) {
		$x['osname_fmt'] = os_normalize($x['osname']);
	}

	return $ret;
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
