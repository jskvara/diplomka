<?php
// https://github.com/facebook/php-webdriver/wiki/Example-command-reference

namespace Model;

use \Nette\Utils\Strings;

class FbParser {

	protected $addLikes = array();

	public function eduwork($eduworkPost) {
		$return = array(
			"eduwork" => array()
		);

		foreach ($eduworkPost as $eduwork) {
			$eduwork = $eduwork->text();
			$edw = explode("\n", $eduwork, 2);
			if (count($edw) > 1) {
				$return["eduwork"][] = $edw[0] . " (". $edw[1] .")";
			} else {
				$return["eduwork"][] = $edw[0];
			}
		}

		return $return;
	}

	public function hometown($hometownPost) {
		$return = array();

		foreach ($hometownPost as $hometown) {
			$hometown = $hometown->text();
			if (empty($hometown)) {
				return array();
			}
			$htw = explode("\n", $hometown, 2);
			$return[$htw[1]] = $htw[0];
		}

		return $return;
	}

	public function family($family) {
		$return = array();

		foreach ($family as $person) {
			if (!array_key_exists("family", $return)) {
				$return["family"] = array();
			}

			$person = $person->text();
			$per = explode("\n", $person, 2);
			if (count($per) > 1) {
				if (!array_key_exists($per[1], $return["family"])) {
					$return["family"][$per[1]] = array();
				}
				$return["family"][$per[1]][] = $per[0];
			} else {
				if (!array_key_exists("other", $return["family"])) {
					$return["family"]["other"] = array();
				}
				$return["family"]["other"][] = $per[0];
			}
		}

		return $return;
	}

	public function relationship($relationships) {
		$return = array();

		foreach ($relationships as $relationship) {
			$relationship = $relationship->text();
			$rel = explode("\n", $relationship, 2);

			$return[] = $rel;
		}

		return $return;
	}

	public function yearly($yearly) {
		$return = array();

		foreach ($yearly as $yearly) {
			$yearly = $yearly->text();
			$yrl = explode("\n", $yearly, 2);
			if (!array_key_exists($yrl[0], $return)) {
				$return[$yrl[0]] = array();
			}
			$return[$yrl[0]][] = $yrl[1];
		}

		return $return;
	}

	public function basic($basic) {
		$return = array();

		$basicInfoKeys = array("Birthday", "Sex", "Interested In", "Relationship Status", "Languages", "Religious Views",
			"Political Views", "Anniversary");

		foreach ($basic as $basicInfo) {
			$basicInfo = $basicInfo->text();

			$found = false;
			foreach ($basicInfoKeys as $infoKey) {
				if (Strings::startsWith($basicInfo, $infoKey)) {
					$info = Strings::substring($basicInfo, Strings::length($infoKey) + 1);
					$return[$infoKey] = $info;

					$found = true;
					continue 2;
				}
			}

			if (!$found) {
				$info = explode(" ", $basicInfo, 2);
				$return[$info[0]] = $info[1];
			}
		}

		return $return;
	}

	public function contact($contacts) {
		$return = array();

		$contactKeys = array("Website", "Facebook", "Address", "Email", "Screen Name", "Networks", "Mobile Phones");
		$savedContacts = array();
		foreach ($contacts as $contact) {
			$contact = $contact->text();

			if (in_array($contact, $savedContacts)) {
				continue;
			}

			$found = false;
			foreach ($contactKeys as $contactKey) {
				if (Strings::startsWith($contact, $contactKey)) {
					$contact = Strings::substring($contact, Strings::length($contactKey) + 1);
					$return[$contactKey] = $contact;
					$savedContacts[] = $contact;

					$found = true;
					continue 2;
				}

			}

			if (!$found) {
				$contact = explode(" ", $contact, 2);
				$return[$contact[0]] = $contact[1];
			}
		}

		return $return;
	}

	public function friends($friendElems) {
		$return = array();

		foreach ($friendElems as $friendElem) {
			$friendHtml = $friendElem->attribute("innerHTML");
			if ($friendHtml != "") {
				$friendUrl = $this->getAttribute($friendHtml, "href");

				if (Strings::endsWith($friendUrl, "&amp;hc_location=friends_tab")) {
					$friendUrl = Strings::substring($friendUrl, 0, -28);
				}
				if (Strings::startsWith($friendUrl, "https://www.facebook.com/")) {
					$friendUrl = Strings::substring($friendUrl, 25);
				}

				$friendText = $friendElem->text();
				$mutualFriends = "";

				foreach (explode("\n", $friendText) as $line => $f) {
					if ($line == 0 && $f != "") {
						$friendName = $f;
					} else if (Strings::endsWith($f, " mutual friends")) {
						$mutualFriends = Strings::substring($f, 0, -15);
					}
				}

				$friend = array();
				$friend["name"] = $friendName;
				$friend["url"] = $friendUrl;
				if ($mutualFriends !== "") {
					$friend["mutual"] = $mutualFriends;
				}

				$return[] = $friend;
			}
		}

		return $return;
	}

	public function newWallFriends($friendElems) {
		$return = array();

		foreach ($friendElems as $friendElem) {
			$friendText = $friendElem->text();
			if ($friendText != "") {
				$friendHtml = $friendElem->attribute("innerHTML");
				$friendUrl = $this->getAttribute($friendHtml, "href");
				$friendUrl = str_replace("?fref=pb", "", $friendUrl);
				$friendUrl = str_replace("&amp;fref=pb", "", $friendUrl);
				$friendUrl = str_replace("&amp;hc_location=friends_tab", "", $friendUrl);
				$friendUrl = str_replace("https://www.facebook.com/", "", $friendUrl);

				$friendName = "";
				$friendsCount = "";

				foreach (explode("\n", $friendText) as $line => $f) {
					if ($line == 0 && $f !== "Friend") {
						continue 2;
					} else if ($line == 2) {
						$friendName = $f;
					} else if ($line == 3) {
						if (Strings::endsWith($f, " friends")) {
							$friendsCount = Strings::substring($f, 0, -8);
						}
					}
				}

				$friend = array();
				$friend["name"] = $friendName;
				$friend["url"] = $friendUrl;
				if ($friendsCount !== "") {
					$friend["friendsCount"] = $friendsCount;
				}

				$return[] = $friend;
			}
		}

		return $return;
	}

	public function wallPost($wallPosts, $personId, $isFriend = TRUE) {
		$statuses = array();
		$activities = array();
		$friends = array();
		$likes = array();
		$places = array();
		$events = array();

		foreach ($wallPosts as $post) {
			$postText = $post->text();
			$postHtml = $post->attribute("innerHTML");

			// post times
			//$utimes = $this->getAllAttributes($postHtml, "data-utime");
			$texttimes = $this->getAllElementAttributes($postHtml, "abbr" ,"title");

			$dataJson = $this->getAttribute($postHtml, 'data-gt');
			if ($dataJson !== FALSE) {
				$dataJson = json_decode(htmlspecialchars_decode($dataJson), FALSE, 512, JSON_BIGINT_AS_STRING);
			}

			if ($postText === "") {
				continue;

			// Post a status or Photos
			} elseif (Strings::startsWith($postText, "Post") || Strings::startsWith($postText, "Photo")) {
				continue;

			// Activity
			} elseif (Strings::startsWith($postText, "Activity")) {
				$activityPost = explode("\n", $postText);
				foreach ($activityPost as $activity) {
					if ($activity == "Activity" || $activity == "Recent" || $activity == "Like · Comment" ||
						$activity == "More Recent Activity") {
						continue;
					}

					$activities[] = $activity;
				}

			// Friends
			} elseif (Strings::startsWith($postText, "Friends")) {
				$friendsPost = explode("\n", $postText);
				foreach ($friendsPost as $friend) {
					if ($friend == "Friends" || $friend == "See All") {
						continue;
					}

					$friends[] = $friend;
				}

			// Likes
			} elseif (Strings::startsWith($postText, "Likes")) {
				$likesPost = explode("\n", $postText);
				foreach ($likesPost as $like) {
					if ($like == "Likes" || $like == "See All" || $like == "Recently") {
						continue;
					}

					$likes[] = $like;
				}

			// Places
			} elseif (Strings::startsWith($postText, "Places")) {
				$placesPost = explode("\n", $postText);
				foreach ($placesPost as $place) {
					if ($place == "Places" || $place == "See All" || $place == "See All Stories" ||
						$place == "1 Recent Place") {
						continue;
					}

					$places[] = $place;
				}

			// Events
			} elseif (Strings::startsWith($postText, "Events")) {
				$event = array();

				$eventsPost = explode("\n", $postText);
				foreach ($eventsPost as $e) {
					if ($e == "Events" || $e == "March" ||
						(Strings::startsWith($e, "Joined ") && Strings::endsWith($e, " Events"))) {
						continue;

					} else if (Strings::startsWith($e, "With ") && Strings::endsWith($e, " other guests")) {
						$guests = Strings::substring($e, 5, -13);
						$event["guests"] = $guests;

						$events[] = $event;

					} else {
						$event["name"] = $e;

					}
				}

			// Status
			} else {
				$statusPost = explode("\n", $postText);
				$status = array();
				$status["user"] = $personId;
				$status["friend"] = $isFriend;
				$status["type"] = $dataJson->timeline_unit_type;
				$status["timestamp"] = $this->titleToDate(array_shift($texttimes));

				$areComments = FALSE;
				$comments = array();
				$comment = array();
				$status["commentsCount"] = 0;
				$status["likesCount"] = 0;

				foreach ($statusPost as $line => $st) {
					if ($st == "Like ·" ||
						$st == "Write a comment..." ||
						$st == "Press Enter to post." ||
						$st == "See Translation"
						) {
						continue;

					// date
					} else if ($line == 1) {
						continue;

					} else if ($st == "Like · · Share") {
						$areComments = TRUE;

					} else if (Strings::endsWith($st, " likes this.") || Strings::endsWith($st, " like this.")) {
						$status["likesCount"] = $this->parseLikesText($st);

					} else if (!$areComments) {
						if (!empty($status["status"])) {
							$status["status"] .= "\n" . $st;
						} else {
							$status["status"] = $st;
						}

					// View X more comments
					} else if ($areComments &&
						Strings::startsWith($st, "View ") &&
						(($singleMoreComment = Strings::endsWith($st, " more comment")) ||
							Strings::endsWith($st, " more comments"))) {

						if ($singleMoreComment) {
							$status["commentsCount"] = 1;
						} else {
							$count = Strings::substring($st, 5, -14);
							$status["commentsCount"] = (int) $count;
						}

					} else if ($areComments && Strings::contains($st, " · Like")) {
						if (Strings::endsWith($st, " · Like")) {
							$st = Strings::substring($st, 0, -7);
						}
						// $comment["time"] = $st;
						// $comment["realtime"] = $this->timeToRealtime($st);

						// $comment["timestamp"] = array_shift($utimes);
						// $comment["timestamp-text"] = date("Y-m-d H:i:s", $comment["timestamp"]);
						$comment["timestamp"] = $this->titleToDate(array_shift($texttimes));

						$status["commentsCount"]++;
						$comments[] = $comment;
						$comment = array();

					} else if ($areComments) {
						if (!empty($comment["text"])) {
							$comment["text"] .= "\n" . $st;
						} else {
							$comment["text"] = $st;
						}

					} else {
						$status[] = $st;
					}
				}

				if ($comments != array()) {
					$status["comments"] = $comments;
				}

				$statuses[] = $status;
			}
		}

		return $statuses;
	}

	public function myWallPost($wallPosts, $username, $makeLikesCount = 3) {
		$statuses = array();

		$i = 0;

		$this->addLikes = array();
		foreach ($wallPosts as $post) {
			$postText = $post->text();
			$postHtml = $post->attribute("innerHTML");

			// post times
			$texttimes = $this->getAllElementAttributes($postHtml, "abbr" ,"title");

			$user = $this->getFirstElementAttribute($postHtml, "a", "href");

			$dataJson = $this->getAttribute($postHtml, 'data-gt');
			if ($dataJson !== FALSE) {
				$dataJson = json_decode(htmlspecialchars_decode($dataJson), FALSE, 512, JSON_BIGINT_AS_STRING);
			}

			if ($postText === "") {
				continue;

			// Options
			} elseif (Strings::startsWith($postText, "Options") ||
				Strings::startsWith($postText, "Upcoming Events")) {
				continue;

			// Status
			} else {
				$statusPost = explode("\n", $postText);
				$status = array(
					"myWall" => TRUE,
				);
				$status["user"] = $username;

				$areComments = FALSE;
				$comments = array();
				$comment = array();
				$status["commentsCount"] = 0;
				$status["likesCount"] = 0;

				foreach ($statusPost as $line => $st) {

					if ($line === 0) {
						$status["timestamp"] = $this->titleToDate(array_shift($texttimes));

						if (Strings::contains($st, "like") || // A and B like/likes X's album/video Y/a photo/page
							Strings::contains($st, "shared") || // shared an event/X's photo/link
							Strings::contains($st, "now friend") || // is now friends with
							Strings::contains($st, "profile picture") || // changed his profile picture
							Strings::contains($st, "added a new photo") ||
							Strings::contains($st, "tagged in") || // was/were tagged in X's photo. — with Y
							Strings::contains($st, "commented on") || // commented on a photo/a video
							Strings::contains($st, "is going to an event") || // is going to an event
							Strings::contains($st, "updated") || // updated the event photo/his cover photo
							Strings::contains($st, "created") // created an event
							// small letters are not allowed in names
						) {
							$status["status"] = $st;
							continue;
						} else {
							$status["user"] = $st;
						}

					} elseif ($st == "Like ·" ||
						$st == "Write a comment..." ||
						$st == "Press Enter to post." ||
						$st == "See translation" ||
						$st == "Options") {
						continue;

					} else if (Strings::startsWith($st, "Like · ·") ||
						Strings::startsWith($st, "Like · Share")) { // Like · · Share
						$areComments = TRUE;

					} else if (Strings::endsWith($st, " likes this.") || Strings::endsWith($st, " like this.")) {
						$status["likesCount"] = $this->parseLikesText($st);

					} else if (!$areComments) {
						if (!empty($status["status"])) {
							$status["status"] .= "\n" . $st;
						} else {
							$status["status"] = $st;
						}
					} else if ($areComments &&
						Strings::startsWith($st, "View ") &&
						(($singleMoreComment = Strings::endsWith($st, " more comment")) ||
							Strings::endsWith($st, " more comments"))) {

						if ($singleMoreComment) {
							$status["commentsCount"] = 1;
						} else {
							$count = Strings::substring($st, 5, -14);
							$status["commentsCount"] = (int) $count;
						}

					} else if ($areComments && Strings::contains($st, " · Like")) {
						if (Strings::endsWith($st, " · Like")) {
							$st = Strings::substring($st, 0, -7);
						}
						// $comment["time"] = $st;
						// $comment["realtime"] = $this->timeToRealtime($st);

						// $comment["timestamp"] = array_shift($utimes);
						// $comment["timestamp-text"] = date("Y-m-d H:i:s", $comment["timestamp"]);
						$comment["timestamp"] = $this->titleToDate(array_shift($texttimes));

						$comments[] = $comment;
						$comment = array();
						$status["commentsCount"]++;

					} else if ($areComments) {
						if (!empty($comment["text"])) {
							$comment["text"] .= "\n" . $st;
						} else {
							$comment["text"] = $st;
						}

					} else {
						$status[] = $st;
					}
				}

				if ($comments != array()) {
					$status["comments"] = $comments;
				}

				// statuses with likes
				if ($i <= 20 && isset($status["likesCount"])) {
					$this->checkAddLikes($status["likesCount"], $post, $makeLikesCount);
				}
				// dump($status);

				$statuses[] = $status;
				$i++;
			}
		}

		// like statuses
		foreach ($this->addLikes as $like) {
			dump($like);
			$like["likeElement"]->click();
		}

		return $statuses;
	}

	protected function checkAddLikes($statusLikesCount, $post, $makeLikesCount) {
		if ($statusLikesCount < 1) {
			return;
		}

		uasort($this->addLikes, array($this, "cmpLikes"));

		if (count($this->addLikes) < $makeLikesCount) {
			try {
				$likeElement = $post->element("link text", "Like");
			// already liked
			} catch (NoSuchElementWebDriverError $e) {
				return FALSE;
			}

			$this->addLikes[] = array(
				"likesCount" => $statusLikesCount,
				"likeElement" => $likeElement,
				"text" => $post->text(),
			);
			return TRUE;
		}

		if ($this->addLikes[($makeLikesCount - 1)]["likesCount"] < $statusLikesCount) {
			try {
				$likeElement = $post->element("link text", "Like");
			// already liked
			} catch (NoSuchElementWebDriverError $e) {
				return FALSE;
			}

			$this->addLikes[($makeLikesCount - 1)] = array(
				"likesCount" => $statusLikesCount,
				"likeElement" => $likeElement,
				"text" => $post->text(),
			);
		}

		return TRUE;
	}

	protected function cmpLikes($a, $b) {
		if ($a["likesCount"] == $b["likesCount"]) {
			return 0;
		}
		return ($a["likesCount"] > $b["likesCount"]) ? -1 : 1;
	}

	protected function getAddLikes() {
		return $this->addLikes;
	}

	protected function parseLikesText($likesText) {
		// 2 people like this.
		if (Strings::endsWith($likesText, " people like this.")) {
			$likesString = Strings::substring($likesText, 0, -18);

			// 3,446
			if (Strings::contains($likesString, ",")) {
				$likesString = str_replace(",", "", $likesString);
			}

			return (int) $likesString;

		// XXX and 43 others like this.
		} elseif (Strings::endsWith($likesText, " others like this.")) {
			$likes = substr_count($likesText, ",");
			$likes++;

			$likesString = Strings::substring($likesText, strpos($likesText, " and ") + 4, -18);
			// 3,446
			if (Strings::contains($likesString, ",")) {
				$likesString = str_replace(",", "", $likesString);
				$likes--;
			}

			$likes += (int) $likesString;

			return $likes;

		// XXX and YYY like this.
		} elseif (Strings::endsWith($likesText, " like this.")) {
			$likes = substr_count($likesText, ",");
			$likes++;

			if (Strings::contains($likesText, " and ")) {
				$likes++;
			}

			return $likes;

		// XXX likes this.
		} elseif (Strings::endsWith($likesText, " likes this.")) {
			return 1;
		}

		echo "Bad like text: ";
		dump($likesText);

		return $likesText;
	}

	protected function getAttribute($html, $attr) {
		preg_match('/' . $attr . '="?([^">]+)"?/', $html, $match);
		if (count($match) < 1) {
			return FALSE;
		}

		return $match[1];
	}

	protected function getAllAttributes($html, $attr) {
		preg_match_all('/' . $attr . '="?([^">]+)"?/', $html, $match);
		if (count($match) < 1) {
			return array();
		}

		return $match[1];
	}

	protected function getAllElementAttributes($html, $element, $attribute) {
		// preg_match_all('/<'. $element .'\s+'. $attribute .'\s*=\s*("[^"]*"|\'[^\']*\'|[^\s >]*)/', $html, $match);
		preg_match_all('/<'. $element .'\s+'. $attribute .'="?([^">]+)"[^\s >]*/', $html, $match);
		if (count($match) < 1) {
			return array();
		}

		return $match[1];
	}

	protected function getFirstElementAttribute($html, $element, $attribute) {
		preg_match('/<'. $element .'\s+'. $attribute .'="?([^">]+)"[^\s >]*/', $html, $match);
		if (count($match) < 1) {
			return array();
		}

		return $match[1];
	}

	protected function titleToDate($title) {
		if ($title === NULL) {
			return "";
		}

		// Friday, March 29, 2013 at 10:21pm
		$datetime = \DateTime::createFromFormat("l, F j, Y \at g:ia", $title);

		if ($datetime === FALSE) {
			// March 30, 2013
			$datetime = \DateTime::createFromFormat("!F j, Y", $title); // ! - defaults time to zeros
		}

		if ($datetime === FALSE) {
			echo "Bad title date: ";
			dump($title);
			return "";
		}

		return $datetime->format("Y-m-d H:i:s");;
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

		// March 9 via XXXApp
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

}
