<?php

// Copy this file to `include/magpie/<name>.class.php` to add a new section.
// The Magpie facade loads the file and instantiates the class ONLY when you
// first touch `$magpie-><name>`, so nothing here runs until it's needed.

class Magpie_Skel {
	private DBQuery $dbq;
	private Memcached $mc;

	public function __construct(DBQuery $dbq, Memcached $mc) {
		$this->dbq = $dbq;
		$this->mc  = $mc;
	}

	// Each method is one function the page calls. Return data (an array);
	// the page is still responsible for $s->assign() and rendering.
	public function get($param) {
		return [];
	}
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
