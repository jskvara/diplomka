<?php

namespace Model;

use \DibiConnection;
use \Nette\Object;

abstract class DatabaseService extends Object {
	protected $connection;

	public function __construct(DibiConnection $connection) {
		$this->connection = $connection;
	}
}