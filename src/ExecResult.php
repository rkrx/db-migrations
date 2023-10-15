<?php
namespace Kir\DB\Migrations;

class ExecResult {
	function __construct(
		private ?string $lastInsertId,
		private int $rowsAffected
	) {}

	/**
	 * @return string|null
	 */
	public function getLastInsertId(): ?string {
		return $this->lastInsertId;
	}

	/**
	 * @return int
	 */
	public function getRowsAffected(): int {
		return $this->rowsAffected;
	}
}