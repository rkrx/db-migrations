<?php
namespace Kir\DB\Migrations\Helpers;

use Kir\DB\Migrations\Common\ColumnDef;
use Kir\DB\Migrations\Common\Expr;
use Kir\DB\Migrations\DBAdapter;
use Kir\DB\Migrations\DBAdapters\Columns;

class TableObj {
	public function __construct(
		private DBAdapter $adapter,
		private string $database,
		private string $tableName
	) {}

	public function exists(): bool {
		return (bool) $this->adapter->query('
				  SELECT COUNT(*)
					FROM information_schema.columns
				   WHERE TABLE_SCHEMA=:d
				     AND TABLE_NAME=:t
			', ['d' => $this->database, 't' => $this->tableName]
		)->getValue();
	}

	/**
	 * @return Columns
	 */
	public function getColumns(): Columns {
		/** @var array<array{COLUMN_NAME: string, COLUMN_TYPE: string, IS_NULLABLE: 'YES'|'NO', COLUMN_DEFAULT: string|null, EXTRA: string, COLUMN_COMMENT: string|null, CHARACTER_SET_NAME: string|null, COLLATION_NAME: string|null}> $data */
		$data = $this->adapter->query('
				  SELECT *
				    FROM information_schema.columns c
				   WHERE c.TABLE_SCHEMA=DATABASE()
				     AND c.TABLE_NAME=:t
				ORDER BY c.ORDINAL_POSITION
			', ['d' => $this->database, 't' => $this->tableName]
		)->getRows();
		$columns = [];
		foreach($data as $row) {
			$columns[] = new ColumnDef(
				name: $row['COLUMN_NAME'],
				dataType: $row['COLUMN_TYPE'],
				nullable: $row['IS_NULLABLE'] === 'YES',
				defaultValue: match(true) {
					$row['COLUMN_DEFAULT'] === null => null,
					(bool) preg_match('{^\'.*?\'$}', $row['COLUMN_DEFAULT']) =>
						substr($row['COLUMN_DEFAULT'], 1, -1),
					(bool) preg_match('{^\\d+$}', $row['COLUMN_DEFAULT']) =>
						(int) $row['COLUMN_DEFAULT'],
					(bool) preg_match('{^[+-]?(\\d*[.])?\\d+$}', $row['COLUMN_DEFAULT']) =>
						(float) $row['COLUMN_DEFAULT'],
					$row['COLUMN_DEFAULT'] === 'NULL' => new Expr('NULL'),
					default => new Expr($row['COLUMN_DEFAULT'])
				},
				onUpdateValue: call_user_func(static function () use ($row) {
					if(preg_match('{^ON UPDATE (.+)$}i', $row['EXTRA'], $matches)) {
						return new Expr($matches[1]);
					}
					return null;
				}),
				comment: $row['COLUMN_COMMENT'],
				charset: $row['CHARACTER_SET_NAME'],
				collation: $row['COLLATION_NAME']
			);
		}
		return new Columns($columns);
	}

	public function hasColumn(string $name): bool {
		$result = $this->adapter->query('
			SELECT COUNT(*)
			  FROM information_schema.columns c
			 WHERE c.TABLE_SCHEMA = :d
			   AND c.TABLE_NAME = :t
			   AND c.COLUMN_NAME = :c
		', ['d' => $this->database, 't' => $this->tableName, 'c' => $name]);
		return (bool) $result->getValue();
	}

	public function hasIndex(string $name): bool {
		$result = $this->adapter->query('
			SELECT COUNT(*)
			  FROM information_schema.statistics
			 WHERE TABLE_SCHEMA = :d
			   AND TABLE_NAME = :t
			   AND INDEX_NAME = :i
		', ['d' => $this->database, 't' => $this->tableName, 'i' => $name]);
		return (bool) $result->getValue();
	}
}
