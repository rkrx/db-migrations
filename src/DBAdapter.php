<?php
namespace Kir\DB\Migrations;

use JetBrains\PhpStorm\Language;
use Kir\DB\Migrations\DBAdapters\TableDef;

interface DBAdapter {
	/**
	 * @param string $entry
	 * @return string
	 */
	public function hasEntry($entry);

	/**
	 * @param string $entry
	 * @return $this
	 */
	public function addEntry($entry);

	/**
	 * @param string $tableName
	 * @return TableDef
	 */
	public function table($tableName);

	/**
	 * @param string $query
	 * @param array $args
	 * @return QueryResult
	 */
	public function query(
		#[Language('MySQL')]
		$query,
		array $args = array()
	);

	/**
	 * @param string $query
	 * @param array $args
	 * @return ExecResult
	 */
	public function exec(
		#[Language('MySQL')]
		$query,
		array $args = array()
	);
}