<?php

class Magpie_Log {
	private DBQuery $dbq;
	private Memcached $mc;

	public function __construct(DBQuery $dbq, Memcached $mc) {
		$this->dbq = $dbq;
		$this->mc  = $mc;
	}

	public function get(int $count, int $offset, string $grade) {
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
				$x = $this->dbq->dbh->quote($x);
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

		$ret = $this->dbq->query($sql, [$count, $offset]);

		// Normalize OS names
		foreach ($ret as &$x) {
			$x['osname_fmt'] = os_normalize($x['osname']);
		}

		return $ret;
	}
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
