<?php
namespace Kir\DB\Migrations\Schema;

use Kir\DB\Migrations\DBAdapter;
use Psr\Log\LoggerInterface;

final class MigrationContext {
	public function __construct(
		public DBAdapter $db,
		public LoggerInterface $logger,
		public EngineInfo $engine,
		public FeatureGate $features,
		public SchemaInspector $inspector,
		public SqlRenderer $sql
	) {}

	/**
	 * @return array{up: \Closure, down: \Closure}
	 */
	public function skipStatement(string $message): array {
		$logger = $this->logger;
		$up = static function (DBAdapter $db) use ($logger, $message): void {
			$logger->warning($message);
		};
		$down = static function (DBAdapter $db): void {
		};
		return ['up' => $up, 'down' => $down];
	}
}
