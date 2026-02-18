<?php
namespace Kir\DB\Migrations\Commands;

use Kir\DB\Migrations\Contracts\MigrationStep;
use Kir\DB\Migrations\DBAdapter;
use Kir\DB\Migrations\Schema\Feature;
use Kir\DB\Migrations\Schema\MigrationContext;

final class DropTableBuilder implements MigrationStep {
	public function __construct(private string $tableName) {}

	public function statements(MigrationContext $context): array {
		$required = [Feature::DROP_TABLE, Feature::CREATE_TABLE];
		$unsupported = $context->features->unsupported($required);
		if($unsupported !== []) {
			return [$context->skipStatement($this->skipMessage($context, 'drop table', $unsupported))];
		}
		$tableName = $this->tableName;
		$inspector = $context->inspector;
		$dropSql = $context->sql->renderDropTable($tableName);
		$createSql = null;
		$dropped = false;

		$up = function (DBAdapter $db) use ($inspector, $tableName, $dropSql, &$createSql, &$dropped, $context): void {
			$createSql = $inspector->getCreateTableSql($tableName);
			if($createSql === null) {
				$dropped = false;
				return;
			}
			$context->execSql($db, $dropSql);
			$dropped = true;
		};
		$down = function (DBAdapter $db) use (&$createSql, &$dropped, $context): void {
			if(!$dropped || $createSql === null) {
				return;
			}
			$context->execSql($db, $createSql);
		};

		return [['up' => $up, 'down' => $down]];
	}

	/**
	 * @param string[] $unsupported
	 */
	private function skipMessage(MigrationContext $context, string $action, array $unsupported): string {
		return sprintf(
			'Skip UP %s on %s: unsupported features for %s (%s).',
			$action,
			$this->tableName,
			$context->features->describeEngine(),
			implode(', ', $unsupported)
		);
	}
}
