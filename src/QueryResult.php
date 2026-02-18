<?php
namespace Kir\DB\Migrations;

class QueryResult {
	/**
	 * @param array<array<string, null|scalar>> $rows
	 */
	public function __construct(private array $rows) {}

	/**
	 * @return array<array<string, null|scalar>>
	 */
	public function getRows(): array {
		return $this->rows;
	}

	/**
	 * @return null|array<string, null|scalar>
	 */
	public function getRow(): ?array {
		$rows = $this->getRows();
		return $rows[0] ?? null;
	}

	/**
	 * @template T
	 * @param null|string $fieldName If not null, the first column gets returned
	 * @param null|callable(null|scalar):T $mapFn
	 * @return ($mapFn is null ? array<null|scalar> : array<T>)
	 */
	public function getArray(?string $fieldName = null, $mapFn = null): array {
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
				$firstColumn = $mapFn($firstColumn);
			}
			$result[] = $firstColumn;
		}
		return $result;
	}

	/**
	 * @param null|string $fieldName
	 * @param null|callable $mapFn
	 * @return bool|int|float|string|null
	 */
	public function getValue(?string $fieldName = null, $mapFn = null) {
		$row = $this->getRow();
		if($row === null) {
			return null;
		}
		$value = null;
		if($fieldName !== null) {
			if(array_key_exists($fieldName, $row)) {
				$value = $row[$fieldName];
			}
		} elseif(count($row) > 0) {
			$value = array_shift($row);
		}
		if($mapFn !== null) {
			$value = $mapFn($value);
		}
		if($value === null) {
			return null;
		}
		if(is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
			return $value;
		}
		if(is_object($value) && method_exists($value, '__toString')) {
			return (string) $value;
		}
		return null;
	}
}
