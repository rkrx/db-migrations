<?php
namespace Kir\DB\Migrations;

use JetBrains\PhpStorm\Language;
use Kir\DB\Migrations\Helpers\TableObj;

interface DBAdapter {
	/**
	 * @param string $entry
	 * @return bool
	 */
	public function hasEntry(string $entry): bool;

	/**
	 * @param string $entry
	 * @return $this
	 */
	public function addEntry(string $entry): self;

	/**
	 * @return string|null
	 */
	public function getDatabase(): ?string;

	/**
	 * @param string $tableName
	 * @return TableObj
	 */
	public function table(string $tableName): TableObj;

	/**
	 * @param string $query
	 * @param array<string, null|scalar> $args
	 * @return QueryResult
	 */
	public function query(
		#[Language('MySQL')]
		string $query,
		array $args = []
	): QueryResult;

	/**
	 * @param string $query
	 * @param array<string, null|scalar> $args
	 * @return ExecResult
	 */
	public function exec(
		#[Language('MySQL')]
		string $query,
		array $args = []
	): ExecResult;
}
