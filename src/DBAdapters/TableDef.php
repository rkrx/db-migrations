<?php
namespace Kir\DB\Migrations\DBAdapters;

use Kir\DB\Migrations\DBAdapter;

class TableDef {
	/** @var DBAdapter */
	private $adapter;
	/** @var string */
	private $tableName;

	/**
	 * @param DBAdapter $adapter
	 * @param string $tableName
	 */
	public function __construct(DBAdapter $adapter, $tableName) {
		$this->adapter = $adapter;
		$this->tableName = $tableName;
	}

	/**
	 * @return Columns
	 */
	public function getColumns() {
		$database = $this->getDatabase();
		$data = $this->adapter->query('SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:s AND TABLE_NAME=:t ORDER BY ORDINAL_POSITION', ['s' => $database, 't' => $this->tableName])->getRows();
		$columns = array();
		foreach($data as $row) {
			$columns[$row['COLUMN_NAME']] = $row;
		}
		return new Columns($this->adapter, $columns);
	}

	/**
	 * @return string
	 */
	private function getDatabase() {
		return $this->adapter->query('SELECT DATABASE()')->getValue();
	}
}