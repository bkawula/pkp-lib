<?php

/**
 * @file classes/submission/reviewAssignment/ReviewAssignmentDAO.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentDAO
 * @ingroup submission
 * @see ReviewAssignment
 *
 * @brief Class for DAO relating reviewers to submissions.
 */


import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignment');

class ReviewAssignmentDAO extends DAO {
	var $userDao;

	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct();
		$this->userDao = DAORegistry::getDAO('UserDAO');
	}


	/**
	 * Retrieve review assignments for the passed review round id.
	 * @param $reviewRoundId int
	 * @return array
	 */
	function getByReviewRoundId($reviewRoundId) {
		$params = array((int)$reviewRoundId);
		$query = $this->_getSelectQuery() .
			' WHERE r.review_round_id = ? ORDER BY review_id';
		return $this->_getReviewAssignmentsArray($query, $params);
	}

	/**
	 * Retrieve open review assignments for the passed review round id.
	 * @param $reviewRoundId int
	 * @return array
	 */
	function getOpenReviewsByReviewRoundId($reviewRoundId) {
		$params = array((int)$reviewRoundId, SUBMISSION_REVIEW_METHOD_OPEN);
		$query = $this->_getSelectQuery() .
			' WHERE r.review_round_id = ? AND r.review_method = ? AND r.date_confirmed IS NOT NULL ORDER BY review_id';
		return $this->_getReviewAssignmentsArray($query, $params);
	}

	/**
	 * Retrieve review assignments from table usign the passed
	 * sql query and parameters.
	 * @param $query string
	 * @param $queryParams array
	 * @return array
	 */
	function _getReviewAssignmentsArray($query, $queryParams) {
		$reviewAssignments = array();

		$result = $this->retrieve($query, $queryParams);

		while (!$result->EOF) {
			$reviewAssignments[$result->fields['review_id']] = $this->_fromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		return $reviewAssignments;
	}

	/**
	 * Get the review_rounds join string. Must be implemented
	 * by subclasses.
	 * @return string
	 */
	function getReviewRoundJoin() {
		return 'r.review_round_id = r2.review_round_id';
	}


	//
	// Public methods.
	//
	/**
	 * Retrieve a review assignment by review round and reviewer.
	 * @param $reviewRoundId int
	 * @param $reviewerId int
	 * @return ReviewAssignment
	 */
	function getReviewAssignment($reviewRoundId, $reviewerId) {
		$result = $this->retrieve(
			$this->_getSelectQuery() .
			' WHERE	r.review_round_id = ? AND
				r.reviewer_id = ?',
			array(
				(int) $reviewRoundId,
				(int) $reviewerId
			)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		return $returner;
	}


	/**
	 * Retrieve a review assignment by review assignment id.
	 * @param $reviewId int
	 * @return ReviewAssignment
	 */
	function getById($reviewId) {
		$reviewRoundJoinString = $this->getReviewRoundJoin();
		if ($reviewRoundJoinString) {
			$result = $this->retrieve(
				'SELECT	r.*, r2.review_revision
				FROM	review_assignments r
					LEFT JOIN review_rounds r2 ON (' . $reviewRoundJoinString . ')
				WHERE	r.review_id = ?',
				(int) $reviewId
			);

			$returner = null;
			if ($result->RecordCount() != 0) {
				$returner = $this->_fromRow($result->GetRowAssoc(false));
			}

			$result->Close();
			return $returner;
		} else {
			assert(false);
		}
	}

	/**
	 * Get all incomplete review assignments for all journals/conferences/presses
	 * @param $articleId int
	 * @return array ReviewAssignments
	 */
	function getIncompleteReviewAssignments() {
		$reviewAssignments = array();
		$reviewRoundJoinString = $this->getReviewRoundJoin();
		if ($reviewRoundJoinString) {
			$result = $this->retrieve(
				'SELECT	r.*, r2.review_revision
				FROM	review_assignments r
					LEFT JOIN review_rounds r2 ON (' . $reviewRoundJoinString . ')
				WHERE' . $this->getIncompleteReviewAssignmentsWhereString() .
				' ORDER BY r.submission_id'
			);

			while (!$result->EOF) {
				$reviewAssignments[] = $this->_fromRow($result->GetRowAssoc(false));
				$result->MoveNext();
			}

			$result->Close();
		} else {
			assert(false);
		}

		return $reviewAssignments;
	}

	/**
	 * Get the WHERE sql string to filter incomplete review
	 * assignments.
	 * @return string
	 */
	function getIncompleteReviewAssignmentsWhereString() {
		return ' r.date_notified IS NOT NULL AND
		r.date_completed IS NULL AND
		r.declined <> 1';
	}

	/**
	 * Get all review assignments for a submission.
	 * @param $submissionId int Submission ID
	 * @param $reviewRoundId int Review round ID
	 * @param $stageId int Optional stage ID
	 * @return array ReviewAssignments
	 */
	function getBySubmissionId($submissionId, $reviewRoundId = null, $stageId = null) {
		$query = $this->_getSelectQuery() .
			' WHERE	r.submission_id = ?';

		$orderBy = ' ORDER BY review_id';

		$queryParams[] = (int) $submissionId;

		if ($reviewRoundId != null) {
			$query .= ' AND r2.review_round_id = ?';
			$queryParams[] = (int) $reviewRoundId;
		} else {
			$orderBy .= ', r2.review_round_id';
		}

		if ($stageId != null) {
			$query .= ' AND r2.stage_id = ?';
			$queryParams[] = (int) $stageId;
		} else {
			$orderBy .= ', r2.stage_id';
		}

		$query .= $orderBy;

		return $this->_getReviewAssignmentsArray($query, $queryParams);
	}

	/**
	 * Get all review assignments for a reviewer.
	 * @param $userId int
	 * @return array ReviewAssignments
	 */
	function getByUserId($userId) {
		$reviewAssignments = array();
		$reviewRoundJoinString = $this->getReviewRoundJoin();

		if ($reviewRoundJoinString) {
			$result = $this->retrieve(
				'SELECT	r.*, r2.review_revision
				FROM	review_assignments r
					LEFT JOIN review_rounds r2 ON (' . $reviewRoundJoinString . ')
				WHERE	r.reviewer_id = ?
				ORDER BY round, review_id',
			(int) $userId
			);

			while (!$result->EOF) {
				$reviewAssignments[] = $this->_fromRow($result->GetRowAssoc(false));
				$result->MoveNext();
			}

			$result->Close();
		} else {
			assert(false);
		}

		return $reviewAssignments;
	}

	/**
	 * Check if a reviewer is assigned to a specified submisssion.
	 * @param $reviewRoundId int
	 * @param $reviewerId int
	 * @return boolean
	 */
	function reviewerExists($reviewRoundId, $reviewerId) {
		$result = $this->retrieve(
				'SELECT COUNT(*)
				FROM	review_assignments
				WHERE	review_round_id = ? AND
				reviewer_id = ?',
				array((int) $reviewRoundId, (int) $reviewerId)
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Get all review assignments for a review form.
	 * @param $reviewFormId int
	 * @return array ReviewAssignments
	 */
	function getByReviewFormId($reviewFormId) {
		$reviewAssignments = array();
		$reviewRoundJoinString = $this->getReviewRoundJoin();

		if ($reviewRoundJoinString) {
			$result = $this->retrieve(
				'SELECT	r.*, r2.review_revision
				FROM	review_assignments r
					LEFT JOIN review_rounds r2 ON (' . $reviewRoundJoinString . ')
				WHERE	r.review_form_id = ?
				ORDER BY round, review_id',
				(int) $reviewFormId
			);

			while (!$result->EOF) {
				$reviewAssignments[] = $this->_fromRow($result->GetRowAssoc(false));
				$result->MoveNext();
			}

			$result->Close();
		} else {
			assert(false);
		}

		return $reviewAssignments;
	}

	/**
	 * Determine the order of active reviews for the given round of the given submission
	 * @param $submissionId int Submission ID
	 * @param $reviewRoundId int Review round ID
	 * @return array Associating review ID with number, i.e. if review ID 26 is first returned['26']=0.
	 */
	function getReviewIndexesForRound($submissionId, $reviewRoundId) {
		$result = $this->retrieve(
			'SELECT	review_id
			FROM	review_assignments
			WHERE	submission_id = ? AND
				review_round_id = ?
			ORDER BY review_id',
			array((int) $submissionId, (int) $reviewRoundId)
		);

		$index = 0;
		$returner = array();
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$returner[$row['review_id']] = $index++;
			$result->MoveNext();
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Insert a new Review Assignment.
	 * @param $reviewAssignment ReviewAssignment
	 */
	function insertObject($reviewAssignment) {
		$result = $this->update(
			sprintf('INSERT INTO review_assignments (
				submission_id,
				reviewer_id,
				stage_id,
				review_method,
				round,
				competing_interests,
				recommendation,
				declined,
				date_assigned, date_notified, date_confirmed,
				date_completed, date_acknowledged, date_due, date_response_due,
				quality, date_rated,
				last_modified,
				date_reminded, reminder_was_automatic,
				review_form_id,
				review_round_id,
				unconsidered
				) VALUES (
				?, ?, ?, ?, ?, ?, ?, ?, %s, %s, %s, %s, %s, %s, %s, ?, %s, %s, %s, ?, ?, ?, ?
				)',
				$this->datetimeToDB($reviewAssignment->getDateAssigned()),
				$this->datetimeToDB($reviewAssignment->getDateNotified()),
				$this->datetimeToDB($reviewAssignment->getDateConfirmed()),
				$this->datetimeToDB($reviewAssignment->getDateCompleted()),
				$this->datetimeToDB($reviewAssignment->getDateAcknowledged()),
				$this->datetimeToDB($reviewAssignment->getDateDue()),
				$this->datetimeToDB($reviewAssignment->getDateResponseDue()),
				$this->datetimeToDB($reviewAssignment->getDateRated()),
				$this->datetimeToDB($reviewAssignment->getLastModified()),
				$this->datetimeToDB($reviewAssignment->getDateReminded())
			), array(
				(int) $reviewAssignment->getSubmissionId(),
				(int) $reviewAssignment->getReviewerId(),
				(int) $reviewAssignment->getStageId(),
				(int) $reviewAssignment->getReviewMethod(),
				max((int) $reviewAssignment->getRound(), 1),
				$reviewAssignment->getCompetingInterests(),
				$reviewAssignment->getRecommendation(),
				(int) $reviewAssignment->getDeclined(),
				$reviewAssignment->getQuality(),
				(int) $reviewAssignment->getReminderWasAutomatic(),
				$reviewAssignment->getReviewFormId(),
				(int) $reviewAssignment->getReviewRoundId(),
				(int) $reviewAssignment->getUnconsidered(),
			)
		);

		$reviewAssignment->setId($this->getInsertId());

		// Update review stage status whenever a review assignment is changed
		$this->updateReviewRoundStatus($reviewAssignment);
	}

	/**
	 * Update an existing review assignment.
	 * @param $reviewAssignment object
	 */
	function updateObject($reviewAssignment) {
		$result = $this->update(
			sprintf('UPDATE review_assignments
				SET	submission_id = ?,
					reviewer_id = ?,
					stage_id = ?,
					review_method = ?,
					round = ?,
					competing_interests = ?,
					recommendation = ?,
					declined = ?,
					date_assigned = %s,
					date_notified = %s,
					date_confirmed = %s,
					date_completed = %s,
					date_acknowledged = %s,
					date_due = %s,
					date_response_due = %s,
					quality = ?,
					date_rated = %s,
					last_modified = %s,
					date_reminded = %s,
					reminder_was_automatic = ?,
					review_form_id = ?,
					review_round_id = ?,
					unconsidered = ?
				WHERE review_id = ?',
				$this->datetimeToDB($reviewAssignment->getDateAssigned()), $this->datetimeToDB($reviewAssignment->getDateNotified()), $this->datetimeToDB($reviewAssignment->getDateConfirmed()), $this->datetimeToDB($reviewAssignment->getDateCompleted()), $this->datetimeToDB($reviewAssignment->getDateAcknowledged()), $this->datetimeToDB($reviewAssignment->getDateDue()), $this->datetimeToDB($reviewAssignment->getDateResponseDue()), $this->datetimeToDB($reviewAssignment->getDateRated()), $this->datetimeToDB($reviewAssignment->getLastModified()), $this->datetimeToDB($reviewAssignment->getDateReminded())),
			array(
				(int) $reviewAssignment->getSubmissionId(),
				(int) $reviewAssignment->getReviewerId(),
				(int) $reviewAssignment->getStageId(),
				(int) $reviewAssignment->getReviewMethod(),
				(int) $reviewAssignment->getRound(),
				$reviewAssignment->getCompetingInterests(),
				$reviewAssignment->getRecommendation(),
				(int) $reviewAssignment->getDeclined(),
				$reviewAssignment->getQuality(),
				$reviewAssignment->getReminderWasAutomatic(),
				$reviewAssignment->getReviewFormId(),
				(int) $reviewAssignment->getReviewRoundId(),
				(int) $reviewAssignment->getUnconsidered(),
				(int) $reviewAssignment->getId()
			)
		);

		// Update review stage status whenever a review assignment is changed
		$this->updateReviewRoundStatus($reviewAssignment);
	}

	/**
	 * Update the status of the review round an assignment is attached to. This
	 * should be fired whenever a reviewer assignment is modified.
	 *
	 * @param $reviewAssignment ReviewAssignment
	 */
	public function updateReviewRoundStatus($reviewAssignment) {
		import('lib.pkp.classes.submission.reviewRound/ReviewRoundDAO');
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$reviewRound = $reviewRoundDao->getReviewRound(
			$reviewAssignment->getSubmissionId(),
			$reviewAssignment->getStageId(),
			$reviewAssignment->getRound()
		);

		// Review round may not exist if submission is being deleted
		if ($reviewRound) {
			return $reviewRoundDao->updateStatus($reviewRound);
		}

		return false;
	}

	/**
	 * Internal function to return a review assignment object from a row.
	 * @param $row array
	 * @return ReviewAssignment
	 */
	function _fromRow($row) {
		$reviewAssignment = $this->newDataObject();
		$user = $this->userDao->getById($row['reviewer_id']);

		$reviewAssignment->setId($row['review_id']);
		$reviewAssignment->setSubmissionId($row['submission_id']);
		$reviewAssignment->setReviewerId($row['reviewer_id']);
		$reviewAssignment->setReviewerFullName($user->getFullName());
		$reviewAssignment->setCompetingInterests($row['competing_interests']);
		$reviewAssignment->setRecommendation($row['recommendation']);
		$reviewAssignment->setDateAssigned($this->datetimeFromDB($row['date_assigned']));
		$reviewAssignment->setDateNotified($this->datetimeFromDB($row['date_notified']));
		$reviewAssignment->setDateConfirmed($this->datetimeFromDB($row['date_confirmed']));
		$reviewAssignment->setDateCompleted($this->datetimeFromDB($row['date_completed']));
		$reviewAssignment->setDateAcknowledged($this->datetimeFromDB($row['date_acknowledged']));
		$reviewAssignment->setDateDue($this->datetimeFromDB($row['date_due']));
		$reviewAssignment->setDateResponseDue($this->datetimeFromDB($row['date_response_due']));
		$reviewAssignment->setLastModified($this->datetimeFromDB($row['last_modified']));
		$reviewAssignment->setDeclined($row['declined']);
		$reviewAssignment->setQuality($row['quality']);
		$reviewAssignment->setDateRated($this->datetimeFromDB($row['date_rated']));
		$reviewAssignment->setDateReminded($this->datetimeFromDB($row['date_reminded']));
		$reviewAssignment->setReminderWasAutomatic($row['reminder_was_automatic']);
		$reviewAssignment->setRound($row['round']);
		$reviewAssignment->setReviewFormId($row['review_form_id']);
		$reviewAssignment->setReviewRoundId($row['review_round_id']);
		$reviewAssignment->setReviewMethod($row['review_method']);
		$reviewAssignment->setStageId($row['stage_id']);
		$reviewAssignment->setUnconsidered($row['unconsidered']);

		return $reviewAssignment;
	}

	/**
	 * Return a new review assignment data object.
	 * @return DataObject
	 */
	function newDataObject() {
		return new ReviewAssignment();
	}

	/**
	 * Delete review assignment.
	 * @param $reviewId int
	 */
	function deleteById($reviewId) {
		$reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');
		$reviewFormResponseDao->deleteByReviewId($reviewId);

		$reviewFilesDao = DAORegistry::getDAO('ReviewFilesDAO');
		$reviewFilesDao->revokeByReviewId($reviewId);

		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$notificationDao->deleteByAssoc(ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewId);

		// Retrieve the review assignment before it's deleted, so it can be
		// be used to fire an update on the review round status.
		import('lib.pkp.classes.submission.reviewRound/ReviewRoundDAO');
		$reviewAssignment = $this->getById($reviewId);

		$result = $this->update(
			'DELETE FROM review_assignments WHERE review_id = ?',
			(int) $reviewId
		);

		$this->updateReviewRoundStatus($reviewAssignment);

		return $result;
	}

	/**
	 * Delete review assignments by submission ID.
	 * @param $submissionId int
	 * @return boolean
	 */
	function deleteBySubmissionId($submissionId) {
		$returner = false;
		$result = $this->retrieve(
			'SELECT review_id FROM review_assignments WHERE submission_id = ?',
			array((int) $submissionId)
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$this->deleteById($row['review_id']);
			$result->MoveNext();
			$returner = true;
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Get the ID of the last inserted review assignment.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('review_assignments', 'review_id');
	}

	/**
	 * Get the last review round review assignment for a given user.
	 * @param $submissionId int
	 * @param $reviewerId int
	 * @return ReviewAssignment
	 */
	function getLastReviewRoundReviewAssignmentByReviewer($submissionId, $reviewerId) {
		$params = array(
				(int) $submissionId,
				(int) $reviewerId
		);

		$result = $this->retrieveLimit(
				$this->_getSelectQuery() .
				' WHERE	r.submission_id = ? AND
				r.reviewer_id = ?
				ORDER BY r2.stage_id DESC, r2.round DESC',
				$params,
				1
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Return the review methods translation keys.
	 * @return array
	 */
	function getReviewMethodsTranslationKeys() {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_EDITOR);
		return array(
			SUBMISSION_REVIEW_METHOD_DOUBLEBLIND => 'editor.submissionReview.doubleBlind',
			SUBMISSION_REVIEW_METHOD_BLIND => 'editor.submissionReview.blind',
			SUBMISSION_REVIEW_METHOD_OPEN => 'editor.submissionReview.open',
		);
	}

	/**
	 * Get sql query to select review assignments.
	 * @return string
	 */
	function _getSelectQuery() {
		return 'SELECT r.*, r2.review_revision FROM review_assignments r
			LEFT JOIN review_rounds r2 ON (r.review_round_id = r2.review_round_id)';
	}
}


