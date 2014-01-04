<?php

use Nette\Application\UI\Form;
use Model\InstagramService;


class InstagramPresenter extends BasePresenter {

	protected $instagramService;

	public function __construct(InstagramService $instagramService) {
		$this->instagramService = $instagramService;
	}

	public function renderDefault($tag = "girls") {
		$this->template->image = $this->instagramService->getPhoto($tag);

		$this["tagForm"]["tag"]->setValue($tag);
	}

	protected function createComponentTagForm($name) {
		$form = new Form($this, $name);
		$form->setMethod("get");
		$form->addText("tag", "Tag: ");
		$form->addSubmit("submit", "Refresh");

		return $form;
	}
}
