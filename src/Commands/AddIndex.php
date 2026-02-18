<?php

namespace Kir\DB\Migrations\Commands;

use Kir\DB\Migrations\Contracts\ContextProvider;
use Kir\DB\Migrations\DBAdapter;
use Kir\DB\Migrations\Schema\EngineInfo;
use Kir\DB\Migrations\Schema\FeatureGate;
use Kir\DB\Migrations\Schema\FeatureMatrix;
use Kir\DB\Migrations\Schema\IndexDefinition;
use Kir\DB\Migrations\Schema\MigrationContext;
use Kir\DB\Migrations\Schema\SchemaInspectorFactory;
use Kir\DB\Migrations\Schema\SqlRenderer;
use Psr\Log\NullLogger;
use Closure;

class AddIndex {
	/**
	 * @param string $tableName
	 * @param string $indexName
	 * @param string[] $columns
	 * @return array<array{up: Closure, down?: Closure}>
	 */
	public static function of(string $tableName, string $indexName, array $columns) {
		$index = new IndexDefinition($indexName, $columns, false, false);

		return [[
			'up' => function (DBAdapter $db) use ($tableName, $indexName): void {
				$context = self::createContext($db);
				if($context->inspector->getIndex($tableName, $indexName) === null) {
					return;
				}
				$context->execSql($db, $context->sql->renderDropIndex($tableName, $indexName));
			},
		], [
			'up' => function (DBAdapter $db) use ($tableName, $index, $indexName): void {
				$context = self::createContext($db);
				if($context->inspector->getIndex($tableName, $indexName) !== null) {
					return;
				}
				$context->execSql($db, $context->sql->renderAddIndex($tableName, $index));
			},
			'down' => function (DBAdapter $db) use ($tableName, $indexName): void {
				$context = self::createContext($db);
				if($context->inspector->getIndex($tableName, $indexName) === null) {
					return;
				}
				$context->execSql($db, $context->sql->renderDropIndex($tableName, $indexName));
			}
		]];
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
