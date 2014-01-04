<?php

namespace Model;

use \DibiConnection;

class TaskService extends DatabaseService {

	protected $table = "task";

	public function __construct(DibiConnection $connection) {
		parent::__construct($connection);
	}

	public function getAll() {
		return $this->connection->select("*")->from($this->table);
	}

	public function get($id) {
		$id = intval($id);
		return $this->connection->select("*")->from($this->table)->where("id = %i", $id)->fetch();
	}

	public function insert(array $data) {
		return $this->connection->insert($this->table, $data)->execute();
	}

	public function update(array $data, $id) {
		$id = intval($id);
		$this->connection->update($this->table, $data)->where("id = %i", $id)->execute();
	}

	public function delete($id) {
		$id = intval($id);
		$this->connection->delete($this->table)->where("id = %i", $id)->execute();
	}
}