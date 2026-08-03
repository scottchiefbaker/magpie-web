<?php

////////////////////////////////////////////////////////

require("../../include/magpie.inc.php");
include "lib/Server.php";

$server = new Lightbulb\Json\Rpc2\Server;

$raw_post    = trim(file_get_contents('php://input', false, null, 0, 15));
$is_json_rpc = (substr($raw_post, 0, 1) == "{");

if (!$is_json_rpc) {
	print $s->fetch("tpls/index.stpl");
	exit;
}

// Methods to expose
$server->echo_data = 'echo_data';
$server->math      = new math;
$server->dist      = new dist;

// jsonrpc-client.pl --url https://scottchiefbaker.perl-magpie.org/api/json-rpc/ --method dist.add_test \
// --params "1768521720,3d07c2b3-b3f7-4a16-8ca7-acadc3059188,Acme::Foo,v0.45,Scott Baker,PASS,v5.4.32,Linux,x86_64_test,Test Body"

// Process any JSON-RPC requests
$server->handle();

////////////////////////////////////////////////////////

function echo_data() {
	$data = func_get_args();

	return $data;
}

#[\AllowDynamicProperties]
class math {
    public function pi() {
        return 3.1415926;
    }
}

#[\AllowDynamicProperties]
class dist {
	private function error_out(string $msg, int $num) {
		$msg = trim($msg);

		$obj = [
			'error_count'   => 1,
			'error_message' => $msg,
			'error_num'     => $num,
		];

		$output = json_encode($obj, JSON_INVALID_UTF8_SUBSTITUTE);

		// http://tools.ietf.org/html/rfc4627
		header('Content-type: application/json');
		print $output;
		exit;
	}

	public function get_test($uuid) {
		global $dbq;

		$sql = "SELECT (test_ts, guid, tester, grade, perl_version, osname, arch_id, distribution_id)
			FROM test
			WHERE guid = ?";

		$x = $dbq->query($sql, [$uuid], 'one_row');

		return $x;
	}

    public function add_test($test_epoch, $test_uuid, $dist_name, $dist_version, $tester_name, $grade, $perl_version, $os_name, $os_version, $test_body) {
		global $dbq;

		if (preg_match("/::/", $dist_name)) {
			$this->error_out("Distribution names should be Foo-Bar not Foo::Bar", 59821);
		}

		////////////////////////////////////////////////////////////////////////////////

		$grade         = strtoupper($grade);
		$allowed_grades = ['PASS', 'FAIL', 'N/A', 'UNKNOWN'];
		if (!in_array($grade, $allowed_grades)) {
			$grade_str = join(", ", $allowed_grades);
			$this->error_out("Invalid grade. Allowed grades: $grade_str", 49190);
		}

		////////////////////////////////////////////////////////////////////////////////

		$diff = abs(time() - $test_epoch);
		if ($diff > 86400 * 365 * 2) {
			$this->error_out("Test date should be within two years of current day ($diff seconds)", 32178);
		}

		////////////////////////////////////////////////////////////////////////////////

		$os_name       = strtolower($os_name);
		$allowed_oses = ['bsd', 'darwin', 'linux', 'solaris', 'windows'];
		if (!in_array($os_name, $allowed_oses)) {
			$os_str = join(", ", $allowed_oses);
			$this->error_out("Invalid OS. Allowed OSs: $os_str", 98471);
		}

		////////////////////////////////////////////////////////////////////////////////

		$exists = $this->get_test($test_uuid);
		if ($exists) {
			$this->error_out("Test '$test_uuid' already in DB", 19340);
		}

		////////////////////////////////////////////////////////////////////////////////

		$test_time_str = date("Y-m-d H:i:s", $test_epoch);
		$tester_uuid   = get_tester_uuid($tester_name);
		$dist_id       = get_distribution_id($dist_name, $dist_version);
		$arch_id       = get_arch_id($os_version);

		$sql = "INSERT INTO test (test_ts, guid, tester, grade, perl_version, osname, arch_id, distribution_id)
			VALUES (?,?,?,?,?,?,?,?);";

		$obj = [
			$test_time_str,
			$test_uuid,
			$tester_uuid,
			$grade,
			$perl_version,
			$os_name,
			$arch_id,
			$dist_id,
		];

		# This is test metadata
		$ok = $dbq->query($sql, $obj);

		# This is the test text results
		$ok2 = write_test_to_db($test_uuid, $test_body, $obj);

		if ($ok && $ok2) {
			$ret = "https://matrix.perl-magpie.org/results/$test_uuid";
		} else {
			$this->error_out("Unknown DB error", 48189);
		}

		return $ret;
    }
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
