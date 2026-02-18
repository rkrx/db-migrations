<?php
namespace Kir\DB\Migrations\Contracts;

use Kir\DB\Migrations\Schema\MigrationContext;
use Psr\Log\LoggerInterface;

interface ContextProvider {
	public function buildContext(LoggerInterface $logger): MigrationContext;
}
