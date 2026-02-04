<?php
namespace Kir\DB\Migrations\Schema;

final class Feature {
	public const CREATE_TABLE = 'create_table';
	public const DROP_TABLE = 'drop_table';
	public const ALTER_ADD_COLUMN = 'alter_add_column';
	public const ALTER_DROP_COLUMN = 'alter_drop_column';
	public const ALTER_MODIFY_COLUMN = 'alter_modify_column';
	public const ALTER_ADD_PRIMARY_KEY = 'alter_add_primary_key';
	public const ALTER_DROP_PRIMARY_KEY = 'alter_drop_primary_key';
	public const ALTER_ADD_INDEX = 'alter_add_index';
	public const ALTER_DROP_INDEX = 'alter_drop_index';
	public const ALTER_ADD_FOREIGN_KEY = 'alter_add_foreign_key';
	public const ALTER_DROP_FOREIGN_KEY = 'alter_drop_foreign_key';
	public const COLUMN_COMMENT = 'column_comment';
	public const COLUMN_CHARSET = 'column_charset';
	public const COLUMN_COLLATION = 'column_collation';
	public const COLUMN_UNSIGNED = 'column_unsigned';
	public const COLUMN_ON_UPDATE = 'column_on_update';
	public const AUTO_INCREMENT = 'auto_increment';
	public const TABLE_ENGINE = 'table_engine';
}
