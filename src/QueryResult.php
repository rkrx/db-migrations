<?php
namespace Kir\DB\Migrations;

class QueryResult {
	/** @var array[] */
	private $rows = null;

	/**
	 * @param array[] $rows
	 */
	function __construct(array $rows) {
		$this->rows = $rows;
	}

	/**
	 * @return array[]
	 */
	public function getRows() {
		return $this->rows;
	}

	/**
	 * @return array
	 */
	public function getRow() {
		foreach($this->getRows() as $row) {
			return $row;
		}
		return null;
	}

	/**
	 * @param null|string $fieldName If not null, the first column gets returned
	 * @param null|callable $mapFn
	 * @return \string[]
	 */
	public function getArray($fieldName = null, $mapFn = null) {
		$result = [];
		foreach($this->rows as $row) {
			if(!count($row)) {
				continue;
			}
			if($fieldName !== null) {
				if(!array_key_exists($fieldName, $row)) {
					continue;
				}
				$firstColumn = $row[$fieldName];
			} else {
				$firstColumn = array_shift($row);
			}
			if($mapFn !== null) {
				$firstColumn = call_user_func($mapFn, $firstColumn);
			}
			$result[] = $firstColumn;
		}
		return $result;
	}

	/**
	 * @param null|string $fieldName
	 * @param null|callable $mapFn
	 * @return string
	 */
	public function getValue($fieldName = null, $mapFn = null) {
		$row = $this->getRow();
		$value = null;
		if($fieldName !== null) {
			if(array_key_exists($fieldName, $row)) {
				$value = $row[$fieldName];
			}
		} else {
			if(count($row) > 0) {
				$value = array_shift($row);
			}
		}
		if($mapFn !== null) {
			$value = call_user_func($mapFn, $value);
		}
		return $value;
	}
}