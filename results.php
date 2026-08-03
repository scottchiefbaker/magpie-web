<?php

require("include/magpie.inc.php");

sw();
$debug = $_GET['debug'] ?? 0;
$json  = $_GET['json']  ?? 0;
$uuid  = $_GET['uuid']  ?? 0;

if (!$uuid) {
	$parts = get_uri_parts();
	$uuid  = $parts[1] ?? "";
}

if (!$uuid) {
	error_out("Missing test UUID", 12931);
} elseif (!is_uuid($uuid)) {
	error_out("Invalid UUID", 13031);
}

////////////////////////////////////////////////////////

$info = get_test_info($uuid);
if (!$info) {
	error_out("Unable to find $uuid", 69035);
}

$ms = sw();
$s->assign('page_ms' , $ms);
$s->assign('uuid'    , $uuid);
$s->assign('info'    , $info);

///////////////////////////////////

if ($json) {
	send_json($s->tpl_vars);
} elseif ($debug) {
	$s->assign('debug_output', k($s->tpl_vars, KRUMO_RETURN));
}

print $s->fetch("tpls/results.stpl");

////////////////////////////////////////////////////////
////////////////////////////////////////////////////////

// Highlight via HTML some important strings in the test body
//
// Note: We need to be careful with these and anchor the regexps with ^ and $ if possible
// otherwise these can get pretty slow as we're parsing 8k of text for each one.
// As of 2025-03-31 I can parse a test output in about 4ms with the 11 rules that are in place.
function highlight_report(string $test_body) {
	// Green/success
	$test_body = preg_replace("/\b(Result: PASS)\b/","<span class=\"status_pass\">$1</span>", $test_body);
	$test_body = preg_replace("/^(All tests successful\.)$/m","<span class=\"status_pass\">$1</span>", $test_body);
	$test_body = preg_replace("/^(x?t.*\. ok)$/m","<span class=\"status_pass\">$1</span>", $test_body);

	// Red/fail
	$test_body = preg_replace("/^((#\s+)?Failed.*)/mi","<span class=\"status_fail\">$1</span>", $test_body);
	$test_body = preg_replace("/\b(Result: FAIL)\b/i","<span class=\"status_fail\">$1</span>", $test_body);
	$test_body = preg_replace("/(Parse errors.*)$/mi","<span class=\"status_fail\">$1</span>", $test_body);
	$test_body = preg_replace("/^(Compilation failed in require.*)$/mi","<span class=\"status_fail\">$1</span>", $test_body);

	// Gray/NA
	$test_body = preg_replace("/(Perl v.*required.*this is only.*)/mi","<span class=\"status_na\">$1</span>", $test_body);
	$test_body = preg_replace("/(! perl.*v\d.*)$/mi","<span class=\"status_na\">$1</span>", $test_body);
	$test_body = preg_replace("/^(x?t.*\. skipped:.*)$/m","<span class=\"status_na\">$1</span>", $test_body);

	// Orange/Unknown
	$test_body = preg_replace("/^(\s*[gdc]?make:.*error.*)$/mi","<span class=\"status_unknown\">$1</span>", $test_body);
	$test_body = preg_replace("/^(Warning:.*)/mi","<span class=\"status_unknown\">$1</span>", $test_body);

	return $test_body;
}

// Get information about a test from the DB
function get_test_info($uuid) {
	global $dbq;

	$ckey = "testinfo:$uuid";
	$data = $GLOBALS['mc']->get($ckey);

	if ($data) {
		$data['x_from_cache'] = true;
		return $data;
	}

	$sql = "SELECT test.*, distribution_name, distribution_version, arch_name,
		EXTRACT(EPOCH FROM test_ts) as test_unixtime, tester.name as tester_name,
		txt_zstd, dict_file
		FROM test
		LEFT  JOIN test_results USING (guid)
		INNER JOIN tester ON (test.tester = tester.uuid)
		INNER JOIN os_arch USING (arch_id)
		INNER JOIN distribution_info USING (distribution_id)
		LEFT  JOIN dict_info USING (dict_id)
		WHERE guid = ?;";

	$ret = $dbq->query($sql, [$uuid], 'one_row');
	if (!$ret) {
		error_out("Unable to find distribution for <code>$uuid</code>", 45328);
	}

	$raw = $ret['txt_zstd'] ?? null;
	$str = "";

	if ($raw) {
		$dict_file = "include/zstd-dict/" . $ret['dict_file'];
		if (!is_readable($dict_file)) {
			error_out("Unable to read zstd dictionary <code>$dict_file</code>", 95357);
		}

		$zst  = @stream_get_contents($raw);
		$dict = file_get_contents($dict_file);
		$str  = zstd_uncompress_dict($zst, $dict);
	}

	$ret['text_report'] = trim($str);
	// Remove the stream from the object
	unset($ret['txt_zstd']);

	// If we don't have the text report in the DB we fetch it via HTTP from CPT
	if (!$ret['text_report']) {
		$grade = strtoupper($ret['grade'] ?? "");
		$x     = fetch_test_info_from_cpt($uuid);

		// If we have valid data we should cache it
		if ($x) {
			$txt_body           = $x['result']['output']['uncategorized'] ?? "";
			$ret['text_report'] = $txt_body;
			$ok                 = $GLOBALS['mc']->set($ckey, $ret, 86400);

			// Save the test to the DB
			write_test_to_db($uuid, $txt_body, $ret);
		} else {
			error_out("Test <code>$uuid</code> not cached locally, and we are unable to fetch it from <code>api.cpantesters.org</code>.", 57202);
		}
	}

	$ret['x_from_cache'] = false;

	return $ret;
}

// Fetch HTML from a remote URL
function http_get_with_timeout(string $url, int $timeout, &$curl_errno, &$http_code) {
	$ch = curl_init();

	curl_setopt_array($ch, [
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => $timeout, // Timeout after 2 seconds
		CURLOPT_FOLLOWLOCATION => true,     // Follow redirects
		CURLOPT_SSL_VERIFYPEER => true,     // Verify SSL certificates
		CURLOPT_SSL_VERIFYHOST => 2,        // Verify host
		CURLOPT_USERAGENT      => 'Perl Magpie Client'
	]);

	$response   = curl_exec($ch);
	$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curl_errno = curl_errno($ch);

	if ($curl_errno) {
		$ret = "???";

		if (curl_errno($ch) == 28) {
			$ret = "Timeout fetching test from cpantesters.org";
		}

		if ($http_code >= 500 && $http_code < 600) {
			$ret = "cpantesters.org failed to fetch test";
		}

		//k(curl_error($ch), curl_errno($ch), $http_code);
		return $ret;
	}

	return $response;
}

// Fetch test information via API
function fetch_test_info_from_cpt($uuid) {
	$start = microtime(1);
	$url   = "http://api.cpantesters.org/v3/report/$uuid";
	$ckey  = "cptraw:$uuid";

	if ($data = $GLOBALS['mc']->get($ckey)) {
		$data['x_from_cache'] = true;
		return $data;
	};

	$curl_errno = 0;
	$http_code  = 0;

	$json = http_get_with_timeout($url, 2, $curl_errno, $http_code);
	$ms   = intval((microtime(1) - $start) * 1000);
	$ret  = @json_decode($json, true);

	// If we got valid JSON and the HTTP code is in the 2xx range
	$is_ok = !is_null($ret) && ($http_code >= 200 && $http_code < 300);

	if ($is_ok) {
		$ok  = $GLOBALS['mc']->set($ckey, $ret, time() + 86400);
	} else {
		$ret = null;
	}

	return $ret;
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
