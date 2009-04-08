<?php

/**
 * @file ProfileHandler.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ProfileHandler
 * @ingroup pages_user
 *
 * @brief Handle requests for modifying user profiles. 
 */

//$Id$

class ProfileHandler extends UserHandler {

	/**
	 * Display form to edit user's profile.
	 */
	function profile() {
		parent::validate();
		parent::setupTemplate(true);

		import('user.form.ProfileForm');

		$profileForm = &new ProfileForm();
		if ($profileForm->isLocaleResubmit()) {
			$profileForm->readInputData();
		} else {
			$profileForm->initData();
		}
		$profileForm->display();
	}

	/**
	 * Validate and save changes to user's profile.
	 */
	function saveProfile() {
		parent::validate();

		import('user.form.ProfileForm');

		$profileForm = &new ProfileForm();
		$profileForm->readInputData();

		if ($profileForm->validate()) {
			$profileForm->execute();
			Request::redirect(null, null, Request::getRequestedPage());

		} else {
			parent::setupTemplate(true);
			$profileForm->display();
		}
	}

	/**
	 * Display form to change user's password.
	 */
	function changePassword() {
		parent::validate();
		parent::setupTemplate(true);

		import('user.form.ChangePasswordForm');

		$passwordForm = &new ChangePasswordForm();
		$passwordForm->initData();
		$passwordForm->display();
	}

	/**
	 * Save user's new password.
	 */
	function savePassword() {
		parent::validate();

		import('user.form.ChangePasswordForm');

		$passwordForm = &new ChangePasswordForm();
		$passwordForm->readInputData();

		if ($passwordForm->validate()) {
			$passwordForm->execute();
			Request::redirect(null, null, Request::getRequestedPage());

		} else {
			parent::setupTemplate(true);
			$passwordForm->display();
		}
	}

}

?>
