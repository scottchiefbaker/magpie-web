<?php
////////////////////////////////////////////////////////////////////////////////
// This is enabled/disabled in include/magpie.inc.php
////////////////////////////////////////////////////////////////////////////////

// X amounts of hits are allowed per X seconds, beyond that gets throttled
$hits_allowed = 5;
$time_period  = 30;

// If your user agent contains one of these strings it will be subject
// to rate limits
$agents = [
	'SemrushBot',
	'Claude-SearchBot',
	'SERankingBacklinksBot',
	'meta-webindexer',
	'Amzn-SearchBot',
	'Claude-SearchBot',
	'DataForSeoBot',
];

// Comment/uncomment this to do rate limit processing
check_rate_limit($hits_allowed, $time_period, $agents);

////////////////////////////////////////////////////////////////////////////////

function check_rate_limit($hits_allowed, $time_period, $agents) {
	global $mc;

	// Make sure we're in binary mode for increment()
	$mc->setOption(Memcached::OPT_BINARY_PROTOCOL, true);

	$agent   = $_SERVER['HTTP_USER_AGENT'] ?? "";
	$ip_addr = $_SERVER['REMOTE_ADDR']     ?? "";

	// We only consider rate limiting for certain user agents
	$agent_str   = join("|", $agents);
	$needs_check = preg_match("/\b($agent_str)\b/i", $agent);

	$debug = intval($_GET['debug'] ?? 0) > 1;

	if ($needs_check && !is_allowed($mc, $agent, $hits_allowed, $time_period, $debug)) {
		http_response_code(429);
		print "Rate limit exceeded... Check back later";

		mplog("RATE_LIMIT: $ip_addr/$agent has been rate limited");
		die;
	}
}

function is_allowed($memcached, $key, $allowed_hits, $bucket_size, $debug = false) {
	$start  = microtime(1);
	$bucket = time() - (time() % $bucket_size);

	// Get the current number of hits for this key from the cache
	$ckey = "RATE_LIMIT:$bucket:$key";
	// Memcached must be in binary mode for increment to work
	// $memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
	$hits = $memcached->increment($ckey, 1, 1, time() + $bucket_size);

	//Uncomment this if you can't use increment()
	//$hits = intval($memcached->get($ckey));
	//$ok     = $memcached->set($ckey, ++$hits, time() + $bucket_size);

	$remain = $bucket_size - (time() % $bucket_size);

	if ($hits > $allowed_hits) {
		$ret = false;
	} else {
		$ret = [
			'allowed'             => $allowed_hits,
			'current_hits'        => $hits,
			'seconds_until_reset' => $remain,
		];
	}

	if ($debug) {
		$out  = "<h1>Rate Limit information</h1>\n\n";
		$out .= "<p>You are allowed to hit this API <b>$allowed_hits</b> times every <b>$bucket_size</b> seconds</p>\n";
		$out .= "<p>You are <b>$key</b> and you've hit this API <b>$hits</b> times in the last <b>$bucket_size</b> seconds. Counter will reset in <b>$remain</b> seconds</p>\n";

		if (!$ret) {
			$out .= "<div style=\"color: red;\"><b>DENIED</b></div>\n";
		} else {
			$out .= "<div style=\"color: green;\"><b>Allowed</b></div>\n";
		}

		$out .= sprintf("<p>%0.2f ms to process</p>\n", (microtime(1) - $start) * 1000);

		$text_only = preg_match("/curl/", $_SERVER['HTTP_USER_AGENT']);
		if ($text_only) {
			$out = strip_tags($out);
		}

		print $out;
	}

	return $ret;
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
