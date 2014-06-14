<?php

App::uses('GeoIpLocation', 'GeoIp.Model');
App::uses('Location', 'GeoIp.Lib');

class SurveysComponent extends Component {

	const COOKIE_KEY = 'survey_';
	const COOKIE_DURATION = 30; // in days

	public $components = array ('Cookie', 'Dialog', 'Session');

	private $_controller;

	public function __construct(ComponentCollection $collection, $settings = array()) {
		parent::__construct($collection, Configure::read('Surveys'));
	}

	public function startup(Controller $controller) {
		$this->_controller = $controller;
	}

	public function beforeRender(Controller $controller)
	{
		/**
		 * Setting up a survey
		 */
		foreach ($this->settings as $survey => $options) {
			$pass = true;

			if (isset($options['checks']) && empty($options['bypassChecks'])) {

				$pass = true;

				foreach ($options['checks'] as $check => $checkData){
					$checkName = "_check" . ucfirst($check);

					if (!$this->{$checkName}($survey, $checkData)) {
						$pass = false;
						break;
					}
				}
			}

			if ($pass) {
				$this->Dialog->setDialogForCurrnetPage($options['dialogData']);

				// only show one survey for a request
				return;
			}
		}
	}

	private function _checkOnPage($survey, $pages) {
		foreach ($pages as $page) {
			if ($page['controller'] == $this->_controller->name && $page['action'] == $this->_controller->request->params['action']) {
				return true;
			}
		}

		return false;
	}

	private function _checkIpWithin($survey, $data) {
		$ipLocation = $this->getLocationData();

		if (!empty($ipLocation)) {
			$ipLocationObj = new Location();
			$ipLocationObj->set('latitude', $ipLocation['GeoIpLocation']['latitude']);
			$ipLocationObj->set('longitude', $ipLocation['GeoIpLocation']['longitude']);

			$desiredLocationObj= new Location();
			$desiredLocationObj->set('latitude', $data['lat']);
			$desiredLocationObj->set('longitude', $data['lng']);

			$distance = $ipLocationObj->distance($desiredLocationObj);

			if ($distance < $data['distance']) {
				return true;
			}
		}

		return false;
	}

	private function _checkIpFurtherThan($survey, $data) {
		return !$this->_checkIpWithin($survey, $data);
	}

	private function _checkOneTimeOnly($survey, $showSurveyOnlyOnce)
	{
		if ($showSurveyOnlyOnce && $this->isSurveySeen($survey)) {
			return false;
		}

		return true;
	}

	private function _checkCountryCodeIn($survey, $countryCodes) {
		$ipLocation = $this->getLocationData();

		return Set::check($ipLocation, 'GeoIpLocation.country_code') && in_array($ipLocation['GeoIpLocation']['country_code'], $countryCodes);
	}

	private function getLocationData() {
		$clientIp = $this->_controller->request->clientIp(false);

		if (!$this->Session->check("IP_LOCATION_$clientIp")) {
			$GeoIpLocation = ClassRegistry::init('GeoIp.GeoIpLocation');
			$ipLocation = $GeoIpLocation->find($clientIp);
			$this->Session->write("IP_LOCATION_$clientIp", $ipLocation);
		} else {
			$ipLocation = $this->Session->read("IP_LOCATION_$clientIp");
		}

		return $ipLocation;
	}

	public function isSurveySeen($survey) {
		// bypass checks for testing
		if (isset($this->_controller->request->data['enableSurvey'])) {
			return false;
		}

		$cookieKey = self::COOKIE_KEY . $survey;
		return $this->Cookie->check($cookieKey);
	}

	public function setSurveySeen($survey) {
		$this->Cookie->write(self::COOKIE_KEY . $survey, 1, true, "+ ".self::COOKIE_DURATION." days");
	}
} 