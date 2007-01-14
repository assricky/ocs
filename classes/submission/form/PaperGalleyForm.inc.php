<?php

/**
 * PaperGalleyForm.inc.php
 *
 * Copyright (c) 2003-2007 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package submission.form
 *
 * Paper galley editing form.
 *
 * $Id$
 */

import('form.Form');

class PaperGalleyForm extends Form {

	/** @var int the ID of the paper */
	var $paperId;

	/** @var int the ID of the galley */
	var $galleyId;

	/** @var PaperGalley current galley */
	var $galley;

	/**
	 * Constructor.
	 * @param $paperId int
	 * @param $galleyId int (optional)
	 */
	function PaperGalleyForm($paperId, $galleyId = null) {
		parent::Form('submission/layout/galleyForm.tpl');
		$this->paperId = $paperId;

		if (isset($galleyId) && !empty($galleyId)) {
			$galleyDao = &DAORegistry::getDAO('PaperGalleyDAO');
			$this->galley = &$galleyDao->getGalley($galleyId, $paperId);
			if (isset($this->galley)) {
				$this->galleyId = $galleyId;
			}
		}

		// Validation checks for this form
		$this->addCheck(new FormValidator($this, 'label', 'required', 'submission.layout.galleyLabelRequired'));
	}

	/**
	 * Display the form.
	 */
	function display() {
		$templateMgr = &TemplateManager::getManager();
		$templateMgr->assign('paperId', $this->paperId);
		$templateMgr->assign('galleyId', $this->galleyId);

		if (isset($this->galley)) {
			$templateMgr->assign_by_ref('galley', $this->galley);
		}
		$templateMgr->assign('helpTopicId', 'editorial.layoutEditorsRole.layout');
		parent::display();
	}

	/**
	 * Initialize form data from current galley (if applicable).
	 */
	function initData() {
		if (isset($this->galley)) {
			$galley = &$this->galley;
			$this->_data = array(
				'label' => $galley->getLabel()
			);

		} else {
			$this->_data = array();
		}

	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(
			array(
				'label',
				'deleteStyleFile'
			)
		);
	}

	/**
	 * Save changes to the galley.
	 * @return int the galley ID
	 */
	function execute($fileName = null) {
		import('file.PaperFileManager');
		$paperFileManager = &new PaperFileManager($this->paperId);
		$galleyDao = &DAORegistry::getDAO('PaperGalleyDAO');

		$fileName = isset($fileName) ? $fileName : 'galleyFile';

		if (isset($this->galley)) {
			$galley = &$this->galley;

			// Upload galley file
			if ($paperFileManager->uploadedFileExists($fileName)) {
				if($galley->getFileId()) {
					$paperFileManager->uploadPublicFile($fileName, $galley->getFileId());
				} else {
					$fileId = $paperFileManager->uploadPublicFile($fileName);
					$galley->setFileId($fileId);
				}

				// Update file search index
				import('search.PaperSearchIndex');
				PaperSearchIndex::updateFileIndex($this->paperId, PAPER_SEARCH_GALLEY_FILE, $galley->getFileId());
			}

			if ($paperFileManager->uploadedFileExists('styleFile')) {
				// Upload stylesheet file
				$styleFileId = $paperFileManager->uploadPublicFile('styleFile', $galley->getStyleFileId());
				$galley->setStyleFileId($styleFileId);

			} else if($this->getData('deleteStyleFile')) {
				// Delete stylesheet file
				$styleFile = &$galley->getStyleFile();
				if (isset($styleFile)) {
					$paperFileManager->deleteFile($styleFile->getFileId());
				}
			}

			// Update existing galley
			$galley->setLabel($this->getData('label'));
			$galleyDao->updateGalley($galley);

		} else {
			// Upload galley file
			if ($paperFileManager->uploadedFileExists($fileName)) {
				$fileType = $paperFileManager->getUploadedFileType($fileName);
				$fileId = $paperFileManager->uploadPublicFile($fileName);

				// Update file search index
				import('search.PaperSearchIndex');
				PaperSearchIndex::updateFileIndex($this->paperId, PAPER_SEARCH_GALLEY_FILE, $fileId);
			} else {
				$fileId = 0;
			}

			if (isset($fileType) && strstr($fileType, 'html')) {
				// Assume HTML galley
				$galley = &new PaperHTMLGalley();
			} else {
				$galley = &new PaperGalley();
			}

			$galley->setPaperId($this->paperId);
			$galley->setFileId($fileId);

			if ($this->getData('label') == null) {
				// Generate initial label based on file type
				if ($galley->isHTMLGalley()) {
					$galley->setLabel('HTML');

				} else if (isset($fileType)) {
					if(strstr($fileType, 'pdf')) {
						$galley->setLabel('PDF');

					} else if (strstr($fileType, 'postscript')) {
						$galley->setLabel('PostScript');
					} else if (strstr($fileType, 'xml')) {
						$galley->setLabel('XML');
					}
				}

				if ($galley->getLabel() == null) {
					$galley->setLabel(Locale::translate('common.untitled'));
				}

			} else {
				$galley->setLabel($this->getData('label'));
			}

			// Insert new galley
			$galleyDao->insertGalley($galley);
			$this->galleyId = $galley->getGalleyId();
		}

		return $this->galleyId;
	}

	/**
	 * Upload an image to an HTML galley.
	 * @param $imageName string file input key
	 */
	function uploadImage() {
		import('file.PaperFileManager');
		$fileManager = &new PaperFileManager($this->paperId);
		$galleyDao = &DAORegistry::getDAO('PaperGalleyDAO');

		$fileName = 'imageFile';

		if (isset($this->galley) && $fileManager->uploadedFileExists($fileName)) {
			$type = $fileManager->getUploadedFileType($fileName);
			$extension = $fileManager->getImageExtension($type);
			if (!$extension) {
				$this->addError('imageFile', 'submission.layout.imageInvalid');
				return false;
			}

			if ($fileId = $fileManager->uploadPublicFile($fileName)) {
				$galleyDao->insertGalleyImage($this->galleyId, $fileId);

				// Update galley image files
				$this->galley->setImageFiles($galleyDao->getGalleyImages($this->galleyId));
			}

		}
	}

	/**
	 * Delete an image from an HTML galley.
	 * @param $imageId int the file ID of the image
	 */
	function deleteImage($imageId) {
		import('file.PaperFileManager');
		$fileManager = &new PaperFileManager($this->paperId);
		$galleyDao = &DAORegistry::getDAO('PaperGalleyDAO');

		if (isset($this->galley)) {
			$images = &$this->galley->getImageFiles();
			if (isset($images)) {
				for ($i=0, $count=count($images); $i < $count; $i++) {
					if ($images[$i]->getFileId() == $imageId) {
						$fileManager->deleteFile($images[$i]->getFileId());
						$galleyDao->deleteGalleyImage($this->galleyId, $imageId);
						unset($images[$i]);
						break;
					}
				}
			}
		}
	}

}

?>
