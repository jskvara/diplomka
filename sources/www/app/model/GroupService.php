<?php

namespace Model;

use \DibiConnection;

class GroupService extends DatabaseService {

	protected $table = "groups";
	protected $groupUserTable = "groups_users";

	public function __construct(DibiConnection $connection) {
		parent::__construct($connection);
	}

	public function getAll() {
		$query = $this->connection->select("*")->from($this->table);

		return $query->fetchAll();
	}

	public function get($id) {
		$id = intval($id);
		return $this->connection->select("*")->from($this->table)->where("id = %i", $id)->fetch();
	}

	public function getUserIds($id) {
		$id = intval($id);
		$query = $this->connection->select("users_id")->from($this->groupUserTable)->where("groups_id = %i", $id);

		return $query->fetchPairs();
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

	public function saveUsers($value) {
		$group = $value["group"];
		$users = $value["users"];
		if ($users === NULL) {
			$users = array();
		}
		$savedUsers = $this->getUserIds($group);

		if ($users == $savedUsers) {
			return;
		}

		foreach ($users as $user) {
			if (!in_array($user, $savedUsers)) {
				$data = array(
					"users_id" => $user, 
					"groups_id" => $group,
				);
				$this->connection->insert($this->groupUserTable, $data)->execute();
			}
		}

		foreach ($savedUsers as $user) {
			if (!in_array($user, $users)) {
				$this->connection->delete($this->groupUserTable)->
					where("users_id = %i", $user)->and("groups_id = %i", $group)->execute();
			}
		}
	}
}