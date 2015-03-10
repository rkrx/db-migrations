<?php
namespace Kir\DB\Migrations;

class ExecResult {
	/** @var int */
	private $lastInsertId;
	/** @var int */
	private $rowsAffected;

	/**
	 * @param int $lastInsertId
	 * @param int $rowsAffected
	 */
	function __construct($lastInsertId, $rowsAffected) {
		$this->lastInsertId = $lastInsertId;
		$this->rowsAffected = $rowsAffected;
	}

	/**
	 * @return int
	 */
	public function getLastInsertId() {
		return $this->lastInsertId;
	}

	/**
	 * @return int
	 */
	public function getRowsAffected() {
		return $this->rowsAffected;
	}
}