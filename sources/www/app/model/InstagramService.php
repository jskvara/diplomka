<?php

namespace Model;

use \Instagram\Instagram;
use \Instagram\Auth;
use \Instagram\Core\ApiException;

class InstagramService {

	protected $params;

	public function __construct($params) {
		$this->params = $params;
	}

	public function getPhoto($tag) {
		$authConfig = array(
			'client_id' => $this->params["clientId"],
			'client_secret' => $this->params["clientSecret"],
			'redirect_uri' => $this->params["redirectUri"],
			'scope' => array('basic', 'likes', 'comments', 'relationships'),
		);

		$auth = new Auth($authConfig);

		if (isset($_GET['code']) && empty($_SESSION['instagram_access_token'])) {
		    try {
		        $_SESSION['instagram_access_token'] = $auth->getAccessToken($_GET['code']);
		    } catch (ApiException $e) {
		        echo $e->getMessage();
		        return FALSE;
		    }
		} else if (empty($_SESSION['instagram_access_token'])) {
		    $auth->authorize();
		}

		$instagram = new Instagram();
		$accessToken = $_SESSION['instagram_access_token'];
		// $accessToken = "296289177.7608784.9c49bc41e2e049149224572d4b8071b9";
		$instagram->setAccessToken($accessToken);

		try {
			$tag = $instagram->getTag($tag);
			$media = $tag->getMedia();
		} catch (ApiException $e) {
			echo $e->getMessage();
			return FALSE;
		}

		if ($media->count() < 1) {
			return FALSE;
		}

		return $media[0]->getData()->images->standard_resolution->url;
	}
}