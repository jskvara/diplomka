<?php

use Model\QuoteService;

class QuotePresenter extends BasePresenter {

	protected $quoteService;

	public function __construct(QuoteService $quoteService) {
		$this->quoteService = $quoteService;
	}

	public function renderDefault() {
		$this->template->quote = $this->quoteService->getRandomQuote();
	}
}
