<?php

namespace Model;

use \Nette\Object;

class MongoService extends Object {
	protected $profilesCollection;
	protected $friendsCollection;
	protected $statusesCollection;
	protected $queueCollection;

	public function __construct($params) {
		$mongo = new \MongoClient();
		$db = $mongo->selectDB($params["database"]);
		$this->profilesCollection = new \MongoCollection($db, $params["profilesCollection"]);
		$this->friendsCollection = new \MongoCollection($db, $params["friendsCollection"]);
		$this->statusesCollection = new \MongoCollection($db, $params["statusesCollection"]);
		$this->queueCollection = new \MongoCollection($db, $params["queueCollection"]);
	}

	public function getProfiles() {
		$cursor = $this->profilesCollection->find();
		foreach ($cursor as $doc) {
			var_dump($doc);
		}
	}

	public function insertProfile($profile) {
		$profile["downloaded"] = new \MongoDate();
		$this->profilesCollection->insert($profile);
	}

	public function insertFriends($friends, $user) {
		foreach ($friends as $friend) {
			$friend["fromUser"] = $user;
			$friend["downloaded"] = new \MongoDate();
			$this->friendsCollection->insert($friend);
		}
	}

	public function insertStatuses($statuses, $fromUser) {
		if ($statuses === NULL) {
			return;
		}

		foreach ($statuses as $status) {
			$status["fromUser"] = $fromUser;
			$friend["downloaded"] = new \MongoDate();
			$this->statusesCollection->insert($status);
		}
	}

	public function addToQueue($action, $user, $params = array()) {
		$q = array();
		$q["action"] = $action;

		if ($user === NULL) {
			$q["user"] = NULL;
		} else {
			$q["user"] = (int) $user;
		}

		$q["time"] = new \MongoDate();
		$q["done"] = FALSE;
		if ($params !== array()) {
			$q = array_merge($params, $q);
		}

		$this->queueCollection->insert($q);
	}

	public function queueSetDone($id, $userId = NULL) {
		$query = array(
			"_id" => new \MongoId($id),
		);
		$newData = array(
			"\$set" => array(
				"done" => TRUE,
				"time" => new \MongoDate(),
			)
		);

		if ($userId !== NULL) {
			$newData["\$set"]["user"] = $userId;
		}

		return $this->queueCollection->update($query, $newData);
	}

	public function getFromQueue($id) {
		if (is_array($id)) {
			$query = array(
				"_id" => array()
			);
			foreach ($id as $mid) {
				$query["_id"]["\$in"][] = new \MongoId($mid);
			}

			return $this->queueCollection->find($query);
		}

		$query = array(
			"_id" => new \MongoId($id),
		);

		return $this->queueCollection->findOne($query);
	}

	public function getFromQueueByUser($user = NULL) {
		$query = array(
			"done" => FALSE,
		);

		if ($user !== NULL) {
			$query["user"] = (int) $user;
		}

		$cursor = $this->queueCollection->find($query);
		return $cursor;
	}

	public function deleteFromQueue($id) {
		$query = array(
			"_id" => new \MongoId($id),
		);

		return $this->queueCollection->remove($query, array("justOne" => true));
	}
}