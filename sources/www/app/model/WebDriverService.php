<?php
// https://github.com/facebook/php-webdriver/wiki/Example-command-reference

namespace Model;

use \Nette\Utils\Strings;
use \Model\MongoService;

class WebDriverService {
	protected $session = NULL;
	protected $webSession;
	protected $mongoService;

	const RSVP_ATTEND = "attend";
	const RSVP_MAYBE = "maybe";
	const RSVP_DECLINE = "decline";

	public function __construct(FbParser $fbParser, \Nette\Http\Session $webSession, MongoService $mongoService) {
		$this->fbParser = $fbParser;
		$this->webSession = $webSession;
		$this->mongoService = $mongoService;
		\Nette\Diagnostics\Debugger::$maxLen = 999999;
	}

	public function initSession() {
		if ($this->session !== NULL) {
			return;
		}

		$webDriver = new \WebDriver();
		$this->session = $webDriver->session();
		$webSession = $this->webSession->getSection("webdriver");
		$webSession->webdriverSession = $this->session;
	}

	public function reuseSession() {
		if ($this->session !== NULL) {
			throw new \Nette\InvalidStateException("Webdriver session was already initialized.");
		}

		$webSession = $this->webSession->getSection("webdriver");
		$this->session = $webSession->webdriverSession;
	}

	public function login($email, $password) {
		if ($this->session === NULL) {
			$this->initSession();
		}

		try {
			$this->session->open("https://www.facebook.com");
			// $emailInput = $this->session->findElement(By::id("email"));
			$emailInput = $this->session->element("id", "email");
			$emailInput->value($this->sendKeys($email));

			$passwordInput = $this->session->element("id", "pass");
			$passwordInput->value($this->sendKeys($password));

			try {
				$submitInput = $this->session->element("xpath", "//label[@id='loginbutton']//input");
			} catch (NoSuchElementWebDriverError $e) {
				$submitInput = $this->session->element("xpath", "//button[@id='loginbutton']");
			}
			$submitInput->click();
		} catch (WebDriverException $e) {
			$this->takeScreenshot();
			echo $e->getMessage();
		}
	}

	public function logout() {
		$topDropdown = $this->session->element("id", "userNavigationLabel");
		$topDropdown->click();

		$logout = $this->session->element("xpath", "//form[@id='logout_form']//input[@type='submit']");
		$logout->click();
	}

	protected function sendKeys($keys) {
		$payload = array("value" => preg_split("//u", $keys, -1, PREG_SPLIT_NO_EMPTY));
		return $payload;
	}

	public function takeScreenshot() {
		$imgData = base64_decode($this->session->screenshot());
		$screentshotFile = sprintf("%s/screenshot-%s.png", TEMP_DIR, date("Y-m-d-H-i-s"));
		file_put_contents($screentshotFile, $imgData);
	}

	/**
	 * Use attachement when posting photo or video link
	 */
	public function postStatus($message) {
		$this->session->open("http://www.facebook.com/");

		$textarea = $this->session->element("xpath", "//textarea[@name='xhpc_message']");
		sleep(2);
		$textarea->value($this->sendKeys($message));
		sleep(2);

		$textarea->submit();
	}

	public function postStatusWithAttachement($message, $attachement) {
		$this->session->open("http://www.facebook.com/");

		$textarea = $this->session->element("xpath", "//textarea[@name='xhpc_message']");

		$textarea->value($this->sendKeys($attachement));
		sleep(2);
		$textarea->clear();
		sleep(1);
		$textarea->value($this->sendKeys($message));
		sleep(2);

		$textarea->submit();
	}

	public function likeGroup($id) {
		$this->session->open("http://www.facebook.com/". $id);
		sleep(2);

		try {
			$like = $this->session->element("xpath", "//label[@id='timelineHeadlineLikeButton']//input[@type='submit']");
			$like->click();
		} catch (ElementNotDisplayedWebDriverError $e) {
			// already liked ignore
			return FALSE;
		}

		return TRUE;
	}

	public function addFriend($id) {
		$this->session->open("http://www.facebook.com/". $id);

		sleep(2);

		try {
			$addFriend = $this->session->element("xpath", "//div[@id='fbTimelineHeadline']//label//input[@type='button']");
			$addFriend->click();
		} catch (ElementNotDisplayedWebDriverError $e) {
			// friend request already sent
			return FALSE;
		}

		return TRUE;
	}

	public function RSVPEvent($id, $status = self::RSVP_ATTEND) {
		$this->session->open("http://www.facebook.com/events/". $id ."/");

		if ($status == self::RSVP_ATTEND) {
			try {
				$join = $this->session->element("xpath",
					"//div[@id='pagelet_event_header']//form[@class='stat_elem']//a[@class='fbEventJoinButton selected uiToolbarItem uiButton uiButtonConfirm']");
				$join->click();
			} catch (NoSuchElementWebDriverError $e) {
				// already joined - ignore
				return FALSE;
			}

		// Maybe
		} elseif ($status == self::RSVP_MAYBE) {
			try {
				$maybe = $this->session->element("xpath",
					"//div[@id='pagelet_event_header']//form[@class='stat_elem']//a[@class='uiToolbarItem uiButton uiButtonConfirm']");
					// /span[@class='uiButtonText' and text()='Maybe']");
				$maybe->click();
			} catch (NoSuchElementWebDriverError $e) {
				// already joined - ignore
				return FALSE;
			}
		} else {
			throw new \RuntimeException("RSVP Status: '" . $status . "' is not allowed, allowed statues: 'attend', 'maybe'.");
		}

		return TRUE;
	}

	// only for future events
	public function inviteEvent($eventId) {
		$this->session->open("http://www.facebook.com/events/". $eventId ."/");

		$popup = $this->session->element("xpath", "//a[@class='fbEventInviteButton uiToolbarItem uiButton']//span");
		$popup->click();

		$this->session->wait(5000);
		$people = $this->session->elements("xpath", "//li[@class='multiColumnCheckable checkableListItem']//input[@type='checkbox']");

		foreach ($people as $person) {
			$person->click();
		}
		$this->session->wait(5000);
		sleep(5);

		$invite = $this->session->element("xpath", "//td[@class='uiOverlayFooterButtons']//button[@type='submit']");
		$invite->click();
	}

	public function executeTasks($tasks, $userId = NULL) {
		foreach ($tasks as $task) {
			if ($task["action"] === "postStatus") {
				if ($task["param2"] !== "") {
					$this->postStatusWithAttachement($task["param1"], $task["param2"]);
					echo "Post status: " . $task["param1"] . ", " . $task["param2"];
				} else {
					$this->postStatus($task["param1"]);
					echo "Post status: " . $task["param1"];
				}

			// likeGroup
			} elseif ($task["action"] === "likeGroup") {
				$this->likeGroup($task["param1"]);
				echo "Like Group: " . $task["param1"];

			// addFriend
			} elseif ($task["action"] === "addFriend") {
				$this->addFriend($task["param1"]);
				echo "Add Friend: " . $task["param1"];

			// rsvpEvent
			} elseif ($task["action"] === "rsvpEvent") {
				if ($task["param2"] !== "") {
					$this->rsvpEvent($task["param1"], $task["param2"]);
					echo "RSVP event: " . $task["param1"] . ", " . $task["param2"];
				} else {
					$this->rsvpEvent($task["param1"], $task["param2"]);
					echo "RSVP event: " . $task["param1"];
				}

			// inviteEvent
			} elseif ($task["action"] === "inviteEvent") {
				$this->inviteEvent($task["param1"]);
			}

			$this->mongoService->queueSetDone($task["_id"], $userId);
		}
	}

	public function findFriends($count = 10) {
		$this->session->open("http://www.facebook.com/find-friends/browser/");

		$peopleNames = $people = $this->session->elements("xpath", "//li[@class='friendBrowserListUnit']" .
			"//div[@class='friendBrowserNameTitle fsl fwb fcb']");

		$names = array();
		foreach ($peopleNames as $peopleName) {
			if ($count < 1) {
				break;
			}

			$name = $peopleName->text();

			$names[] = $name;
			$count--;
		}

		return $names;

	}

	public function addFriends(array $add) {
		$peopleNames = $people = $this->session->elements("xpath", "//li[@class='friendBrowserListUnit']" .
			"//div[@class='friendBrowserNameTitle fsl fwb fcb']");

		$people = $this->session->elements("xpath", "//li[@class='friendBrowserListUnit']" .
			"//label[@class='FriendRequestAdd addButton uiButton']//input[@type='button']");


		$i = 0;
		foreach ($people as $person) {
			if (in_array($i, $add)) {
				$html = $peopleNames[$i]->attribute("innerHTML");
				$nameHref = $this->getAttribute($html, "href");

				if (Strings::endsWith($nameHref, "?hc_location=friend_browser") ||
					Strings::endsWith($nameHref, "&hc_location=friend_browser")) {
					$nameHref = Strings::substring($nameHref, 0, -27);
				}

				// dump($peopleNames[$i]->text());
				echo $nameHref . "<br/>";
				$person->click();
			}

			$i++;
		}
	}

	public function getProfile($personId, $friend = TRUE) {
		$this->session->open("http://www.facebook.com/". $personId ."/info");

		$profile = array();
		$profile["user"] = $personId;
		$profile["friend"] = $friend;

		$friendsCount = $this->getFriendsCount();
		$photosCount = $this->getPhotosCount();
		$likesCount = $this->getLikesCount();
		$profile["friendsCount"] = $friendsCount;
		$profile["photosCount"] = $photosCount;
		$profile["likesCount"] = $likesCount;

		$eduwork = $this->session->elements("xpath", "//div[@id='eduwork']//table//tbody");
		$eduwork = $this->fbParser->eduwork($eduwork);
		$profile = array_merge($profile, $eduwork);

		$hometown = $this->session->elements("xpath", "//div[@id='pagelet_hometown']//table//tr");
		$hometown = $this->fbParser->hometown($hometown);
		$profile = array_merge($profile, $hometown);

		$family = $this->session->elements("xpath", "//div[@id='family']//table//li");
		$family = $this->fbParser->family($family);
		$profile = array_merge($profile, $family);

		$relationship = $this->session->elements("xpath", "//div[@id='relationships']//table//tbody");
		$relationship = $this->fbParser->relationship($relationship);
		if (!empty($relationship)) {
			$profile["relationship"] = $relationship;
		}

		$yearly = $this->session->elements("xpath", "//div[@id='pagelet_yearly']//table//tbody");
		$yearly = $this->fbParser->yearly($yearly);
		if (!empty($yearly)) {
			$profile["yearly"] = $yearly;
		}

		$bios = $this->session->elements("xpath", "//div[@id='pagelet_bio']//div[@class='profileText']");
		foreach ($bios as $bio) {
			$bio = $bio->text();
			$profile["Bio"] = $bio;
		}

		$basic = $this->session->elements("xpath", "//div[@id='pagelet_basic']//table//tbody");
		$basic = $this->fbParser->basic($basic);
		$profile = array_merge($profile, $basic);

		$contact = $this->session->elements("xpath", "//div[@id='pagelet_contact']//table//tbody");
		$contact = $this->fbParser->contact($contact);
		$profile = array_merge($profile, $contact);

		$quotes = $this->session->elements("xpath", "//div[@id='pagelet_quotes']//div[@class='profileText']");
		foreach ($quotes as $quote) {
			$quote = $quote->text();
			$profile["Quote"] = $quote;
		}

		return $profile;
	}

	public function getFriends($personName) {
		$this->session->open("http://www.facebook.com/". $personName ."/friends");

		// scroll down
		$pageFooter = $this->session->element("xpath", "//div[@id='pageFooter']");
		for ($i = 0; $i < 200; $i++) {
			$this->session->moveto(array('element' => $pageFooter->getID()));
			$this->session->timeouts(array('type' => 'implicit', 'ms' => 5000));
			$this->session->timeouts(array('type' => 'script', 'ms' => 5000));
		}

		$friendElems = $this->session->elements("xpath", "//div[@id='pagelet_friends']//div[@class='lists']//td");

		// new timeline
		if ($friendElems == array()) {
			$friendElems = $this->session->elements("xpath", "//div[@id='pagelet_main_column_personal']//div/div//li");
			$friends = $this->fbParser->newWallFriends($friendElems);

			return $friends;
		}

		$friends = $this->fbParser->friends($friendElems);

		return $friends;
	}

	public function getWall($personId, $friend = TRUE) {
		$this->session->open("http://www.facebook.com/". $personId);

		try {
			return $this->getWallPosts($personId, $friend);
		} catch (WebDriverException $e) {
			$this->takeScreenshot();
			echo $e->getMessage();
		}
	}

	public function getFriendsCount() {
		try {
			$friendsCount = $this->session->element("xpath", "//li[@id='pagelet_timeline_friends_nav_top']//span[@class='count']");
		} catch (NoSuchElementWebDriverError $e) {
			return 0;
		}

		return $friendsCount->text();
	}

	public function getPhotosCount() {
		try {
			$photosCount = $this->session->element("xpath", "//li[@id='pagelet_timeline_photos_nav_top']//span[@class='count']");
		} catch (NoSuchElementWebDriverError $e) {
			return 0;
		}

		return $photosCount->text();
	}

	public function getLikesCount() {
		try {
			$likesCount = $this->session->element("xpath", "//li[@id='pagelet_timeline_favorites_nav_top']//span[@class='count']");
		} catch (NoSuchElementWebDriverError $e) {
			return 0;
		}

		return $likesCount->text();
	}

	protected function getWallPosts($personId, $friend = TRUE) {
		// scroll down
		$pageFooter = $this->session->element("xpath", "//div[@id='pageFooter']");
		for ($i = 0; $i < 200; $i++) {
			$this->session->moveto(array('element' => $pageFooter->getID()));
			$this->session->timeouts(array('type' => 'implicit', 'ms' => 5000));
			$this->session->timeouts(array('type' => 'script', 'ms' => 5000));
		}

		$wallPosts = $this->session->elements("xpath", "//div[@id='pagelet_timeline_recent']//li[@data-size='1']");
		$wall = $this->fbParser->wallPost($wallPosts, $personId, $friend);

		return $wall;
	}

	public function getMyWall($username) {
		$this->session->open("http://www.facebook.com/");

		$statuses = array();

		// scroll down
		$pageFooter = $this->session->element("xpath", "//div[@id='pageFooter']");
		for ($i = 0; $i < 200; $i++) {
			$this->session->moveto(array('element' => $pageFooter->getID()));
			$this->session->timeouts(array('type' => 'implicit', 'ms' => 5000));
			$this->session->timeouts(array('type' => 'script', 'ms' => 5000));
		}

		$wallPosts = $this->session->elements("xpath", "//div[@id='pagelet_home_stream']/div/ul/li");
		$wall = $this->fbParser->myWallPost($wallPosts, $username);

		return $wall;
	}

	protected function getAttribute($html, $attr) {
		preg_match('/' . $attr . '="?([^">]+)"?/', $html, $match);
		if (count($match) < 1) {
			return FALSE;
		}

		return $match[1];
	}

	protected function timeToRealtime($strTime) {
		if ($strTime === "Recently") {
			return strtotime("now");
		}

		$time = strtotime($strTime);
		if ($time !== FALSE) {
			return $time;
		}

		// March 5 at 3:01pm
		$time = date_create_from_format("F j * g:ia", $strTime);
		if ($time !== FALSE) {
			return $time->getTimestamp();
		}

		// March 9 via TakeThisLollipop
		$pos1 = strpos($strTime, " ");
		$pos2 = strpos($strTime, " ", $pos1 + 1);
		$strTime = Strings::substring($strTime, 0, $pos2);

		$time = date_create_from_format("F j", $strTime);
		if ($time !== FALSE) {
			return $time->getTimestamp();
		}
		dump($strTime, $time);

		return FALSE;
	}

	protected function getWallUpcomingEvent($statusArray) {
		$status = array();
		$status["type"] = "event";

		foreach ($statusArray as $line => $statusLine) {
			if ($statusLine === "Upcoming Events") {
				continue;

			} else if ($line === 1) {
				$status["name"] = $statusLine;

			} else if ($line === 2) {
				$status["time"] = $statusLine;
				$status["realtime"] = $this->timeToRealtime($statusLine);

			} elseif ($line === 3) {
				$status["where"] = $statusLine;

			} elseif (Strings::startsWith($statusLine, "Join · ") && Strings::endsWith($statusLine, " people are going")) {
				// Join · 26 people are going
				$statusLine = Strings::substring($statusLine, 7, -17);
			}
		}

		return $status;
	}

	protected function getWallPhoto($statusArray) {

	}

	public function getRequestsCount() {
		$requestsCount = $this->session->element("xpath", "//span[@id='requestsCountValue']");

		return $requestsCount->text();
	}

	public function getMessagesCount() {
		$messagesCount = $this->session->element("xpath", "//span[@id='mercurymessagesCountValue']");

		return $messagesCount->text();
	}

	public function getNotificationsCount() {
		$notificationsCount = $this->session->element("xpath", "//span[@id='notificationsCountValue']");
		return $notificationsCount->text();
	}
}