<?php

App::uses('AppModel', 'Model');

class Survey extends AppModel {
	public function beforeSave($options = array()) {
		parent::beforeSave($options);

		return true;
	}
}

