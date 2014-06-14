<?php

App::uses('CakeEventManager', 'Event');
App::uses('CakeEventListener', 'Event');

class SurveysController extends AppController {

	/**
	 * @var SurveysComponent
	 */
	public $Surveys;

	public $components = array('Auth', 'Survey.Surveys');

	const SURVEY_CLOSED_EVENT = "Survey.surveyClosed";
	const SURVEY_VIEWED_EVENT = "Survey.surveyViewed";
	const SURVEY_COMPLETED_EVENT = "Survey.surveyCompleted";

	public function beforeFilter() {
		parent::beforeFilter();

		$this->autoRender = false;
		$this->layout = false;
	}

	public function add() {
		// Check if there is already a cookie set for this user
		if ($this->Surveys->isSurveySeen($this->data['Survey']['survey'])) {
			return;
		}

		$survey = $this->request->data;
		$survey['Survey']['data'] = json_encode($survey['Survey']['data']);
		$survey['Survey']['ip'] = $this->request->clientIp(false);
		$survey['Survey']['user_id'] = $this->Auth->user('id');

		$this->Survey->create();
		$saved = $this->Survey->save($survey);

		if ($saved) {
			$this->Surveys->setSurveySeen($this->data['Survey']['survey']);

			$event = new CakeEvent(self::SURVEY_COMPLETED_EVENT, $this, array ('survey' => $this->data['Survey']['survey'], 'user' => $this->Auth->user()));
			$this->getEventManager()->dispatch($event);
		}
	}

	public function display($survey) {
		// check cookie already set
		if ($this->Surveys->isSurveySeen($survey)) {
			throw new InvalidArgumentException();
		}

		$this->set('survey', $survey);

		$this->render("survey_$survey");

		$event = new CakeEvent(self::SURVEY_VIEWED_EVENT, $this, array ('survey' => $survey, 'user' => $this->Auth->user()));
		$this->getEventManager()->dispatch($event);
	}

	public function close_survey($survey) {
		$this->Surveys->setSurveySeen($survey);

		$event = new CakeEvent(self::SURVEY_CLOSED_EVENT, $this, array ('survey' => $survey, 'user' => $this->Auth->user()));
		$this->getEventManager()->dispatch($event);
	}
}
