<?php
namespace Kir\DB\Migrations;

use Kir\DB\Migrations\DBAdapters\TableDef;

interface DBAdapter {
	/**
	 * @return $this
	 */
	public function createMigrationsStore();

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
	public function query($query, array $args = array());

	/**
	 * @param string $query
	 * @param array $args
	 * @return ExecResult
	 */
	public function exec($query, array $args = array());
}