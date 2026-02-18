<?php
namespace Kir\DB\Migrations\Commands;

use Kir\DB\Migrations\Contracts\ContextProvider;
use Kir\DB\Migrations\DBAdapter;
use Kir\DB\Migrations\Schema\EngineInfo;
use Kir\DB\Migrations\Schema\FeatureGate;
use Kir\DB\Migrations\Schema\FeatureMatrix;
use Kir\DB\Migrations\Schema\ForeignKeyDefinition;
use Kir\DB\Migrations\Schema\IndexDefinition;
use Kir\DB\Migrations\Schema\MigrationContext;
use Kir\DB\Migrations\Schema\SchemaInspectorFactory;
use Kir\DB\Migrations\Schema\SqlRenderer;
use Psr\Log\NullLogger;
use Closure;

class AddForeignKey {
	/**
	 * @param string $tableName
	 * @param string $fkName
	 * @param array<string> $localColumns
	 * @param string $foreignTableName
	 * @param array<string> $foreignColumns
	 * @param null|('NO ACTION'|'CASCADE'|'RESTRICT'|'SET NULL'|'SET DEFAULT') $updateMode
	 * @param null|('NO ACTION'|'CASCADE'|'RESTRICT'|'SET NULL'|'SET DEFAULT') $deleteMode
	 * @return array<array{up: Closure, down?: Closure}>
	 */
	public static function of(string $tableName, string $fkName, array $localColumns, string $foreignTableName, array $foreignColumns, ?string $updateMode, ?string $deleteMode) {
		$onUpdate = self::normalizeMode($updateMode, 'update');
		$onDelete = self::normalizeMode($deleteMode, 'delete');

		$fk = new ForeignKeyDefinition(
			name: $fkName,
			localColumns: $localColumns,
			foreignTable: $foreignTableName,
			foreignColumns: $foreignColumns,
			onUpdate: $onUpdate,
			onDelete: $onDelete
		);

		return [[
			'up' => function (DBAdapter $db) use ($tableName, $fkName): void {
				$context = self::createContext($db);
				if($context->inspector->getForeignKey($tableName, $fkName) === null) {
					return;
				}
				$context->execSql($db, $context->sql->renderDropForeignKey($tableName, $fkName));
			},
		], [
			'up' => function (DBAdapter $db) use ($tableName, $fk, $fkName): void {
				$context = self::createContext($db);
				if($context->inspector->getForeignKey($tableName, $fkName) !== null) {
					return;
				}
				if($context->engine->engine === 'mysql' || $context->engine->engine === 'mariadb') {
					$indexes = $context->inspector->getIndexes($tableName);
					if(!self::hasSupportingIndex($indexes, $fk->localColumns)) {
						$context->logger->warning(sprintf(
							'Skip UP add foreign key %s on %s: missing index covering (%s).',
							$fk->name,
							$tableName,
							implode(', ', $fk->localColumns)
						));
						return;
					}
				}
				$context->execSql($db, $context->sql->renderAddForeignKey($tableName, $fk));
			},
			'down' => function (DBAdapter $db) use ($tableName, $fkName): void {
				$context = self::createContext($db);
				if($context->inspector->getForeignKey($tableName, $fkName) === null) {
					return;
				}
				$context->execSql($db, $context->sql->renderDropForeignKey($tableName, $fkName));
			}
		]];
	}

	private static function normalizeMode(?string $mode, string $label): string {
		return match($mode) {
			null, 'NO ACTION' => 'NO ACTION',
			'CASCADE' => 'CASCADE',
			'RESTRICT' => 'RESTRICT',
			'SET NULL' => 'SET NULL',
			'SET DEFAULT' => 'SET DEFAULT',
			default => throw new \InvalidArgumentException("Invalid {$label} mode: {$mode}")
		};
	}

	/**
	 * @param IndexDefinition[] $indexes
	 * @param string[] $localColumns
	 */
	private static function hasSupportingIndex(array $indexes, array $localColumns): bool {
		$needed = count($localColumns);
		foreach($indexes as $index) {
			if(count($index->columns) < $needed) {
				continue;
			}
			if(array_slice($index->columns, 0, $needed) === $localColumns) {
				return true;
			}
		}
		return false;
	}

	private static function createContext(DBAdapter $db): MigrationContext {
		if($db instanceof ContextProvider) {
			return $db->buildContext(new NullLogger());
		}
		$engine = EngineInfo::detect($db);
		$features = new FeatureGate($engine, new FeatureMatrix());
		$inspector = SchemaInspectorFactory::create($db, $engine);
		$sql = new SqlRenderer($engine);
		return new MigrationContext(
			db: $db,
			logger: new NullLogger(),
			engine: $engine,
			features: $features,
			inspector: $inspector,
			sql: $sql
		);
	}
}
