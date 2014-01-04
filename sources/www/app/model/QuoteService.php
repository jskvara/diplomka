<?php

namespace Model;

class QuoteService {

	const URL = "http://citaty.net/nahodny-citat/";

	public function getRandomQuote() {
		$doc = new \DomDocument();
		libxml_use_internal_errors(true);
		$doc->loadHTMLFile(self::URL);
		libxml_clear_errors();

		$divs = $doc->getElementsByTagName("div");
		foreach ($divs as $div) {
			if ($div->getAttribute("class") === "well well-small quote-body") {
				return $div->nodeValue;
			}
		}

		return "";
	}
}