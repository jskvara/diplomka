<?php

use Nette\Application\UI\Form;
use Nette\Utils\Strings;
use Model\TaskService;
use Model\UserService;
use Model\GroupService;
use Model\MongoService;

class TaskPresenter extends BasePresenter {

	protected $taskTypes = array(
		"postStatus" => "postStatus(text, [attachement])",
		"likeGroup" => "likeGroup(id)",
		"addFriend" => "addFrined(id)",
		"rsvpEvent" => "rsvpEvent(id, [rsvpType])",
		"inviteEvent" => "inviteEvent(id)",
	);

	protected $taskService;
	protected $userService;
	protected $groupService;
	protected $mongoService;

	public function __construct(TaskService $taskService, UserService $userService,
		GroupService $groupService, MongoService $mongoService) {
		$this->taskService = $taskService;
		$this->userService = $userService;
		$this->groupService = $groupService;
		$this->mongoService = $mongoService;
	}

	public function renderDefault() {
		$tasks = $this->mongoService->getFromQueueByUser();
		$this->template->tasks = $tasks;
	}

	protected function createComponentTaskForm($name) {
		$form = new Form($this, $name);
		$form->addSelect("taskType", "Task type:", $this->taskTypes)
			->setRequired("You must select task type.");

		$form->addTextarea("param1", "Parameter 1:");

		$form->addText("param2", "Parameter 2:", 42);

		$users = $this->userService->getAll();
		$formUsers = array(
			0 => "",
		);
		foreach ($users as $user) {
			$formUsers[$user->id] = $user->firstName . " " . $user->lastName;
		}

		$groups = $this->groupService->getAll();
		foreach ($groups as $group) {
			$formUsers["g-" . $group->id] = $group->name;
		}

		$form->addMultiSelect("users", "Users:", $formUsers, 10);

		// $form->addText("day", "Day:")
		// 	->setDefaultValue(date("d-m-Y"))
		// 	->setRequired("You must select day.");

		$form->addSubmit("submit", "Save")
			->onClick[] = $this->taskFormSubmitted;

		return $form;
	}

	public function taskFormSubmitted($button) {
		$values = (array)$button->getForm()->getValues();
		$users = $values["users"];
		unset($values["users"]);

		if (empty($users) || $users[0] === "0") {
			$action = $values["taskType"];
			unset($values["taskType"]);
			$this->mongoService->addToQueue($action, NULL, $values);
		} else {
			$action = $values["taskType"];
			unset($values["taskType"]);
			foreach ($users as $user) {
				if (Strings::startsWith($user, "g-")) {
					$groupId = Strings::substring($user, 2);
					$groupUsers = $this->groupService->getUserIds($groupId);
					foreach ($groupUsers as $groupUser) {
						$this->mongoService->addToQueue($action, $groupUser, $values);
					}
				} else {
					$this->mongoService->addToQueue($action, $user, $values);
				}
			}
		}

		$this->flashMessage("Task has been added.");
		$this->redirect("default");
	}

	public function renderNew() {
	}

	public function renderDelete($id = "") {
		$this->template->task = $this->mongoService->getFromQueue($id);
		if (!$this->template->task) {
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
		$this->mongoService->deleteFromQueue($this->getParameter("id"));
		$this->flashMessage("Task has been deleted.");
		$this->redirect("default");
	}
}
