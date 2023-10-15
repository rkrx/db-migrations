<?php
namespace Kir\DB\Migrations\DBAdapters;

use ArrayIterator;
use IteratorAggregate;
use Kir\DB\Migrations\Common\ColumnDef;

/**
 * @implements IteratorAggregate<int, ColumnDef>
 */
class Columns implements IteratorAggregate {
	/**
	 * @param ColumnDef[] $columns
	 */
	public function __construct(
		private array $columns
	) {}

	/**
	 * @param string $columnName
	 * @return bool
	 */
	public function has($columnName) {
		return array_key_exists($columnName, $this->columns);
	}

	/**
	 * @return ArrayIterator<int, ColumnDef>
	 */
	public function getIterator(): ArrayIterator {
		return new ArrayIterator($this->columns);
	}
}