<?php
namespace Kir\DB\Migrations\DBAdapters;

use Kir\DB\Migrations\DBAdapter;

class Columns {
	/** @var DBAdapter */
	private $adapter;
	/** @var array */
	private $columns;

	/**
	 * @param DBAdapter $adapter
	 * @param array $columns
	 */
	public function __construct(DBAdapter $adapter, array $columns) {
		$this->adapter = $adapter;
		$this->columns = $columns;
	}

	/**
	 * @param string $columnName
	 * @return bool
	 */
	public function has($columnName) {
		return array_key_exists($columnName, $this->columns);
	}
}