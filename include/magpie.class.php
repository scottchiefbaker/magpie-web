<?php

class Magpie {
	private array $sections = [];

	private DBQuery $dbq;
	private Memcached $mc;

	public function __construct(DBQuery $dbq, Memcached $mc) {
		$this->dbq = $dbq;
		$this->mc  = $mc;
	}

	public function __get(string $name): object {
		if (isset($this->sections[$name])) {
			return $this->sections[$name];
		}

		// Load the section file ONLY on first access
		$file = __DIR__ . "/magpie/$name.class.php";
		if (is_readable($file)) {
			require_once $file;
		}

		$class = 'Magpie_' . ucfirst($name);
		if (!class_exists($class)) {
			error_out("Unknown Magpie section: $name", 76123);
		}

		return $this->sections[$name] = new $class($this->dbq, $this->mc);
	}
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
