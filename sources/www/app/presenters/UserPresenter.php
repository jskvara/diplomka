<?php

use Nette\Application\UI\Form;
use Model\UserService;
use Model\MongoService;
use Model\WebDriverService;
use \Nette\Utils\Strings;

class UserPresenter extends BasePresenter {

	protected $userService;
	protected $webdriverService;
	protected $mongoService;

	public function __construct(UserService $userService, WebDriverService $webdriverService, MongoService $mongoService) {
		$this->userService = $userService;
		$this->webdriverService = $webdriverService;
		$this->mongoService = $mongoService;
	}

	public function renderDefault() {
		$users = $this->userService->getAll();

		$this->template->users = $users;
	}

	public function actionLogin($id = 0) {
		$user = $this->userService->get($id);
		$this->webdriverService->login($user["email"], $user["password"]);

		exit;
	}

	public function actionDownload($id = 0) {
		$user = $this->userService->get($id);
		$this->webdriverService->login($user["email"], $user["password"]);

		$friendRequests = $this->webdriverService->getRequestsCount();
		$messages = $this->webdriverService->getMessagesCount();
		$notifications = $this->webdriverService->getNotificationsCount();
		echo "Friend requests: ". $friendRequests ."<br />";
		echo "Messages: ". $messages ."<br />";
		echo "Notifications: ". $notifications ."<br />";

		$myWall = $this->webdriverService->getMyWall($user["username"]);
		$this->mongoService->insertStatuses($myWall, $user["username"]);

		// friends
		$friends = $this->webdriverService->getFriends($user["username"]);
		$this->mongoService->insertFriends($friends, $user["username"]);

		exit;
	}

	public function actionDownloadFriends($id = 0) {
		$this->webdriverService->reuseSession();
		$user = $this->userService->get($id);

		// friends
		$friends = $this->webdriverService->getFriends($user["username"]);

		foreach ($friends as $friend) {
			$url = $friend["url"];
			$url = $this->userIdFromUrl($url);

			echo "profile: " . $url . "<br />";
			$profile = $this->webdriverService->getProfile($url);
			$this->mongoService->insertProfile($profile);

			echo "wall: " . $url . "<br />";
			$wall = $this->webdriverService->getWall($url);
			$this->mongoService->insertStatuses($wall, $url);

			echo "friends: " . $url . "<br />";
			$stepFriends = $this->webdriverService->getFriends($url);
			$this->mongoService->insertFriends($stepFriends, $url);

			// foreach ($stepFriends as $stepFriend) {
			// 	$stepFriendUrl = $stepFriend["url"];
			// 	$stepFriendUrl = $this->userIdFromUrl($stepFriendUrl);

			// 	// $profile = $this->webdriverService->getProfile($friendUrl, FALSE);
			// 	// $this->mongoService->insertProfile($profile);
			// 	$this->mongoService->addToQueue($stepFriendUrl, "profile", array("fromUser" => $url));

			// 	// $wall = $this->webdriverService->getWall($friendUrl, FALSE);
			// 	// $this->mongoService->insertStatuses($wall, $friendUrl);
			// 	$this->mongoService->addToQueue($stepFriendUrl, "wall", array("fromUser" => $url));
			// }
		}
		exit;
	}

	protected function createComponentFriendsForm($name) {
		$form = new Form($this, $name);
		$form->addCheckboxList("friends", "Friends: ");
		$form->addSubmit("submit", "Add")
			->onClick[] = $this->friendsFormSubmitted;

		return $form;
	}

	public function friendsFormSubmitted($button) {
		$values = (array)$button->getForm()->getValues();
		$friends = $values["friends"];

		if ($friends !== NULL) {
			$this->webdriverService->reuseSession();
			$this->webdriverService->addFriends($friends);
		}
		exit;
	}

	public function renderFriends($id = 0) {
		$this->webdriverService->reuseSession();
		$friends = $this->webdriverService->findFriends();

		$this["friendsForm"]["friends"]->setItems($friends);
	}

	protected function createComponentTasksForm($name) {
		$form = new Form($this, $name);
		$form->addCheckboxList("tasks", "Tasks: ");
		$form->addSubmit("submit", "Add")
			->onClick[] = $this->tasksFormSubmitted;

		return $form;
	}

	public function tasksFormSubmitted($button) {
		$values = (array)$button->getForm()->getValues();
		$userId = (int) $this->getParameter("id");
		$taskIds = $values["tasks"];

		if ($taskIds !== NULL) {
			$tasks = iterator_to_array($this->mongoService->getFromQueue($taskIds));
			$this->webdriverService->reuseSession();
			$this->webdriverService->executeTasks($tasks, $userId);
		}
		exit;
	}

	public function renderTasks($id = 0) {
		$userTasks = $this->mongoService->getFromQueueByUser($id);
		$unassginedTasks = $this->mongoService->getFromQueueByUser();
		$emptyTask = array("empty" =>
			array(
				"_id" => "",
				"action" => "---------------------------------------------------------------------",
			),
		);

		$tasks = array_merge(iterator_to_array($userTasks), $emptyTask, iterator_to_array($unassginedTasks));

		$formTasks = array();
		foreach ($tasks as $task) {
			$t = $task["action"];

			if (isset($task["param1"])) {
				$t .= " (" . $task["param1"];
			}

			if (isset($task["param2"]) && $task["param2"] !== "") {
				$t .= ", " . $task["param2"];
			}

			if (isset($task["param1"])) {
				$t .= ")";
			}

			$id = $task["_id"];
			if (is_object($task["_id"])) {
				$id = $task["_id"]->__toString();
			}
			$formTasks[$id] = $t;
		}

		$this["tasksForm"]["tasks"]->setItems($formTasks);
	}

	protected function userIdFromUrl($url) {
		if (Strings::startsWith($url, "http://www.facebook.com/")) {
			return Strings::substring($url, 24);
		}

		return $url;
	}

	protected function createComponentUserForm($name) {
		$form = new Form($this, $name);
		$form->addText("email", "E-mail:")
			->setRequired("You must insert user email.")
			->addRule(Form::EMAIL, "Not valid e-mail address.");
		$form->addText("password", "Password:")
			->setRequired("You must insert password.");
		$form->addText("firstName", "First name:")
			->setRequired("You must insert first name.");
		$form->addText("lastName", "Last name:")
			->setRequired("You must insert last name.");
		$form->addText("username", "Username:");
		$form->addText("fbId", "Facebook Id:");
		$form->addSubmit("submit", "Save")
			->onClick[] = $this->userFormSubmitted;

		return $form;
	}

	public function userFormSubmitted($button) {
		$values = (array)$button->getForm()->getValues();
		$id = (int) $this->getParameter("id");
		if ($id) {
			$this->userService->update($values, $id);
			$this->flashMessage("User has been updated.");
		} else {
			$this->userService->insert($values);
			$this->flashMessage("User has been added.");
		}

		$this->redirect("default");
	}

	public function renderNew() {
	}

	public function renderEdit($id = 0) {
		$form = $this["userForm"];
		if (!$form->isSubmitted()) {
			$user = $this->userService->get($id);
			if (!$user) {
				$this->error('Record not found');
			}
			$form->setDefaults($user);
		}
	}

	public function renderDelete($id = 0) {
		$this->template->user = $this->userService->get($id);
		if (!$this->template->user) {
			$this->error("Record not found");
		}
	}

	protected function createComponentDeleteForm() {
		$form = new Form;
		$form->addSubmit("delete", "Delete")
			->onClick[] = $this->deleteFormSucceeded;

		return $form;
	}

	public function deleteFormSucceeded() {
		$this->userService->delete($this->getParameter("id"));
		$this->flashMessage("User has been deleted.");
		$this->redirect("default");
	}
}
