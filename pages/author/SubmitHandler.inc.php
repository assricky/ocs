<?php

/**
 * SubmitHandler.inc.php
 *
 * Copyright (c) 2003-2007 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package pages.author
 *
 * Handle requests for author paper submission. 
 *
 * $Id$
 */

class SubmitHandler extends AuthorHandler {
	
	/**
	 * Display conference author paper submission.
	 * Displays author index page if a valid step is not specified.
	 * @param $args array optional, if set the first parameter is the step to display
	 */
	function submit($args) {
		parent::validate();
		parent::setupTemplate(true);
		
		$step = isset($args[0]) ? (int) $args[0] : 0;
		$paperId = Request::getUserVar('paperId');
		
		list($conference, $event, $paper) = SubmitHandler::validate($paperId, $step);

		$formClass = "AuthorSubmitStep{$step}Form";
		import("author.form.submit.$formClass");

		$submitForm = &new $formClass($paper);
		$submitForm->initData();
		$submitForm->display();
	}
	
	/**
	 * Save a submission step.
	 * @param $args array first parameter is the step being saved
	 */
	function saveSubmit($args) {
		parent::validate();
		parent::setupTemplate(true);

		$step = isset($args[0]) ? (int) $args[0] : 0;
		$paperId = Request::getUserVar('paperId');
		
		list($conference, $event, $paper) = SubmitHandler::validate($paperId, $step);
			
		$formClass = "AuthorSubmitStep{$step}Form";
		import("author.form.submit.$formClass");
		
		$submitForm = &new $formClass($paper);
		$submitForm->readInputData();
			
		// Check for any special cases before trying to save
		switch ($step) {
			case 2:
				if (Request::getUserVar('addAuthor')) {
					// Add a sponsor
					$editData = true;
					$authors = $submitForm->getData('authors');
					array_push($authors, array());
					$submitForm->setData('authors', $authors);
					
				} else if (($delAuthor = Request::getUserVar('delAuthor')) && count($delAuthor) == 1) {
					// Delete an author
					$editData = true;
					list($delAuthor) = array_keys($delAuthor);
					$delAuthor = (int) $delAuthor;
					$authors = $submitForm->getData('authors');
					if (isset($authors[$delAuthor]['authorId']) && !empty($authors[$delAuthor]['authorId'])) {
						$deletedAuthors = explode(':', $submitForm->getData('deletedAuthors'));
						array_push($deletedAuthors, $authors[$delAuthor]['authorId']);
						$submitForm->setData('deletedAuthors', join(':', $deletedAuthors));
					}
					array_splice($authors, $delAuthor, 1);
					$submitForm->setData('authors', $authors);
					
					if ($submitForm->getData('primaryContact') == $delAuthor) {
						$submitForm->setData('primaryContact', 0);
					}
					
				} else if (Request::getUserVar('moveAuthor')) {
					// Move an author up/down
					$editData = true;
					$moveAuthorDir = Request::getUserVar('moveAuthorDir');
					$moveAuthorDir = $moveAuthorDir == 'u' ? 'u' : 'd';
					$moveAuthorIndex = (int) Request::getUserVar('moveAuthorIndex');
					$authors = $submitForm->getData('authors');
					
					if (!(($moveAuthorDir == 'u' && $moveAuthorIndex <= 0) || ($moveAuthorDir == 'd' && $moveAuthorIndex >= count($authors) - 1))) {
						$tmpAuthor = $authors[$moveAuthorIndex];
						$primaryContact = $submitForm->getData('primaryContact');
						if ($moveAuthorDir == 'u') {
							$authors[$moveAuthorIndex] = $authors[$moveAuthorIndex - 1];
							$authors[$moveAuthorIndex - 1] = $tmpAuthor;
							if ($primaryContact == $moveAuthorIndex) {
								$submitForm->setData('primaryContact', $moveAuthorIndex - 1);
							} else if ($primaryContact == ($moveAuthorIndex - 1)) {
								$submitForm->setData('primaryContact', $moveAuthorIndex);
							}
						} else {
							$authors[$moveAuthorIndex] = $authors[$moveAuthorIndex + 1];
							$authors[$moveAuthorIndex + 1] = $tmpAuthor;
							if ($primaryContact == $moveAuthorIndex) {
								$submitForm->setData('primaryContact', $moveAuthorIndex + 1);
							} else if ($primaryContact == ($moveAuthorIndex + 1)) {
								$submitForm->setData('primaryContact', $moveAuthorIndex);
							}
						}
					}
					$submitForm->setData('authors', $authors);
				}
				break;
				
			case 3:
				if (Request::getUserVar('uploadSubmissionFile')) {
					$submitForm->uploadSubmissionFile('submissionFile');
					$editData = true;
				}
				break;
				
			case 4:
				if (Request::getUserVar('submitUploadSuppFile')) {
					SubmitHandler::submitUploadSuppFile();
					return;
				}
				break;
		}
		
		if (!isset($editData) && $submitForm->validate()) {
			$paperId = $submitForm->execute();
			$conference = &Request::getConference();
			$event = &Request::getEvent();

			// For the "abstract only" or sequential review models, nothing else needs
			// to be collected beyond page 2.
			
			if (($step == 2 && !$event->getCollectPapersWithAbstracts()) ||
					($step == 5 )) {

				$templateMgr = &TemplateManager::getManager();
				$templateMgr->assign_by_ref('conference', $conference);
				// If this is an editor and there is a
				// submission file, paper can be expedited.
				if (Validation::isEditor($conference->getConferenceId()) && $paper->getSubmissionFileId()) {
					$templateMgr->assign('canExpedite', true);
				}
				$templateMgr->assign('paperId', $paperId);
				$templateMgr->assign('helpTopicId','submission.index');
				$templateMgr->display('author/submit/complete.tpl');
				
			} else {
				Request::redirect(null, null, null, 'submit', $step+1, array('paperId' => $paperId));
			}
		
		} else {
			$submitForm->display();
		}
	}
	
	/**
	 * Create new supplementary file with a uploaded file.
	 */
	function submitUploadSuppFile() {
		parent::validate();
		parent::setupTemplate(true);
		
		$paperId = Request::getUserVar('paperId');
		
		list($conference, $event, $paper) = SubmitHandler::validate($paperId, 4);
		
		import("author.form.submit.AuthorSubmitSuppFileForm");
		$submitForm = &new AuthorSubmitSuppFileForm($paper);
		$submitForm->setData('title', Locale::translate('common.untitled'));
		$suppFileId = $submitForm->execute();
		
		Request::redirect(null, null, null, 'submitSuppFile', $suppFileId, array('paperId' => $paperId));
	}
	
	/**
	 * Display supplementary file submission form.
	 * @param $args array optional, if set the first parameter is the supplementary file to edit
	 */
	function submitSuppFile($args) {
		parent::validate();
		parent::setupTemplate(true);
		
		$paperId = Request::getUserVar('paperId');
		$suppFileId = isset($args[0]) ? (int) $args[0] : 0;
		
		list($conference, $event, $paper) = SubmitHandler::validate($paperId, 4);
		
		import("author.form.submit.AuthorSubmitSuppFileForm");
		$submitForm = &new AuthorSubmitSuppFileForm($paper, $suppFileId);
		
		$submitForm->initData();
		$submitForm->display();
	}
	
	/**
	 * Save a supplementary file.
	 * @param $args array optional, if set the first parameter is the supplementary file to update
	 */
	function saveSubmitSuppFile($args) {
		parent::validate();
		parent::setupTemplate(true);
		
		$paperId = Request::getUserVar('paperId');
		$suppFileId = isset($args[0]) ? (int) $args[0] : 0;
		
		list($conference, $event, $paper) = SubmitHandler::validate($paperId, 4);
		import("author.form.submit.AuthorSubmitSuppFileForm");
		$submitForm = &new AuthorSubmitSuppFileForm($paper, $suppFileId);
		$submitForm->readInputData();
		
		if ($submitForm->validate()) {
			$submitForm->execute();
			Request::redirect(null, null, null, 'submit', '4', array('paperId' => $paperId));
		} else {
			$submitForm->display();
		}
	}
	
	/**
	 * Delete a supplementary file.
	 * @param $args array, the first parameter is the supplementary file to delete
	 */
	function deleteSubmitSuppFile($args) {
		import("file.PaperFileManager");

		parent::validate();
		parent::setupTemplate(true);
		
		$paperId = Request::getUserVar('paperId');
		$suppFileId = isset($args[0]) ? (int) $args[0] : 0;

		list($conference, $event, $paper) = SubmitHandler::validate($paperId, 4);
		
		$suppFileDao = &DAORegistry::getDAO('SuppFileDAO');
		$suppFile = $suppFileDao->getSuppFile($suppFileId, $paperId);
		$suppFileDao->deleteSuppFileById($suppFileId, $paperId);
		
		if ($suppFile->getFileId()) {
			$paperFileManager = &new PaperFileManager($paperId);
			$paperFileManager->deleteFile($suppFile->getFileId());
		}
		
		Request::redirect(null, null, null, 'submit', '4', array('paperId' => $paperId));
	}

	function expediteSubmission() {
		$paperId = (int) Request::getUserVar('paperId');
		list($conference, $event, $paper) = SubmitHandler::validate($paperId);

		// The author must also be an editor to perform this task.
		if (Validation::isEditor($conference->getConferenceId()) && $paper->getSubmissionFileId()) {
			import('submission.editor.EditorAction');
			EditorAction::expediteSubmission($paper);
			Request::redirect(null, null, 'editor', 'schedulingQueue');
		}

		Request::redirect(null, null, null, 'track');
	}

	/**
	 * Validation check for submission.
	 * Checks that paper ID is valid, if specified.
	 * @param $paperId int
	 * @param $step int
	 */
	function validate($paperId = null, $step = false) {
		list($conference, $event) = parent::validate(true, true);
		
		$paperDao = &DAORegistry::getDAO('PaperDAO');
		$user = &Request::getUser();

		if ($step !== false && ($step < 1 || $step > 5 || (!isset($paperId) && $step != 1))) {
			Request::redirect(null, null, null, 'submit', array(1));
		}

		$paper = null;

		if (isset($paperId)) {
			// Check that paper exists for this conference and user and that submission is incomplete
			$paper =& $paperDao->getPaper((int) $paperId);
			if (!$paper || $paper->getUserId() !== $user->getUserId() || $paper->getEventId() !== $event->getEventId()) {
				Request::redirect(null, null, null, 'submit');
			}
			
			if($step !== false && $step > $paper->getSubmissionProgress()) {
				Request::redirect(null, null, null, 'submit');
			}

		} else {

			// If the paper does not exist, require that the submission window be open.
			$submissionsOpenDate = $event->getSetting('proposalsOpenDate', false);
			$submissionsCloseDate = $event->getSetting('proposalsCloseDate', false);
			
			if(!$submissionsOpenDate || !$submissionsCloseDate ||
					time() < $submissionsOpenDate || time() > $submissionsCloseDate) {
				
				Request::redirect(null, null, 'author', 'index');
			}
		}
		return array(&$conference, &$event, &$paper);
	}
	
}
?>
