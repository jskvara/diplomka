<?php

use Nette\Application\UI\Form;
use Model\GroupService;
use Model\UserService;

class GroupPresenter extends BasePresenter {

	protected $groupService;
	protected $userService;

	public function __construct(GroupService $groupService, UserService $userService) {
		$this->groupService = $groupService;
		$this->userService = $userService;
	}

	public function renderDefault() {
		$groups = $this->groupService->getAll();

		$this->template->groups = $groups;
	}

	protected function createComponentUsersForm($name) {
		$form = new Form($this, $name);
		$users = $this->userService->getAll();

		$formUsers = array();
		foreach ($users as $user) {
			$formUsers[$user->id] = $user->firstName . " " . $user->lastName;
		}

		$form->addHidden("group");

		$form->addCheckboxList("users", "Assign users", $formUsers);

		$form->addSubmit("submit", "Save")
			->onClick[] = $this->usersFormSubmitted;

		return $form;
	}

	public function usersFormSubmitted($button) {
		$values = (array)$button->getForm()->getValues();
		$this->groupService->saveUsers($values);
		$this->flashMessage("Users have been assigned.");

		$this->redirect("default");
	}

	public function renderUsers($id = 0) {
		$form = $this["usersForm"];
		$defaults = array(
			"group" => $id,
			"users" => $this->groupService->getUserIds($id),
		);
		$form->setDefaults($defaults);

		if (!$form->isSubmitted()) {
			$userIds = $this->groupService->getUserIds($id);
			if ($userIds === FALSE) {
				$this->error('Record not found');
			}
			$form->setDefaults($userIds);
		}

		$this->template->group = $this->groupService->get($id);
	}

	protected function createComponentGroupForm($name) {
		$form = new Form($this, $name);
		$form->addText("name", "Name:")
			->setRequired("You must insert group name.");
		$form->addSubmit("submit", "Save")
			->onClick[] = $this->groupFormSubmitted;

		return $form;
	}

	public function groupFormSubmitted($button) {
		$values = (array)$button->getForm()->getValues();
		$id = (int) $this->getParameter("id");
		if ($id) {
			$this->groupService->update($values, $id);
			$this->flashMessage("Group has been updated.");
		} else {
			$this->groupService->insert($values);
			$this->flashMessage("Group has been added.");
		}

		$this->redirect("default");
	}

	public function renderNew() {
	}

	public function renderEdit($id = 0) {
		$form = $this["groupForm"];

		if (!$form->isSubmitted()) {
			$group = $this->groupService->get($id);
			if (!$group) {
				$this->error('Record not found');
			}
			$form->setDefaults($group);
		}
	}

	public function renderDelete($id = 0) {
		$this->template->group = $this->groupService->get($id);
		if (!$this->template->group) {
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
		$this->groupService->delete($this->getParameter("id"));
		$this->flashMessage("Group has been deleted.");
		$this->redirect("default");
	}
}
