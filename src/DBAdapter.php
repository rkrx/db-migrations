<?php
namespace Kir\DB\Migrations;

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
	 * @param string $query
	 * @param array $args
	 * @return ExecResult
	 */
	public function exec($query, array $args = array());
}