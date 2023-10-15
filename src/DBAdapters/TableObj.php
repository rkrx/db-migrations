<?php
namespace Kir\DB\Migrations\DBAdapters;

use Kir\DB\Migrations\DBAdapter;

class TableObj {
	public function __construct(
		private DBAdapter $adapter,
		private string $tableName
	) {}

	/**
	 * @return Columns
	 */
	public function getColumns(): Columns {
		$database = $this->adapter->getDatabase();
		$data = $this->adapter->query(
			'
				SELECT * 
				FROM information_schema.COLUMNS 
				WHERE TABLE_SCHEMA=:s AND TABLE_NAME=:t
				ORDER BY ORDINAL_POSITION
			', ['s' => $database, 't' => $this->tableName]
		)->getRows();
		$columns = array();
		foreach($data as $row) {
			$columns[$row['COLUMN_NAME']] = $row;
		}
		return new Columns($this->adapter, $columns);
	}

	public function hasColumn(string $name): bool {
		$result = $this->adapter->query("
			SELECT COUNT(*)
			FROM information_schema.COLUMNS c
			WHERE c.TABLE_SCHEMA = DATABASE()
			  AND c.TABLE_NAME = :table 
			  AND c.COLUMN_NAME = :column
		", ['table' => $this->tableName, 'column' => $name]);
		return (bool) $result->getValue();
	}

	public function hasIndex(string $name): bool {
		$result = $this->adapter->query("
			SELECT COUNT(*)
			FROM INFORMATION_SCHEMA.STATISTICS
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = :table
			  AND INDEX_NAME = :index
		", ['table' => $this->tableName, 'index' => $name]);
		return (bool) $result->getValue();
	}
}