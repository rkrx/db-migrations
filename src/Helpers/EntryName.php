<?php
namespace Kir\DB\Migrations\Helpers;

class EntryName {
	/**
	 * @param string $entry
	 * @return string
	 */
	public static function shorten($entry) {
		if(preg_match('/^(\\d{4}\\-\\d{2}\\-\\d{2}\\-\\d{2}\\-\\d{2}\\-\\d{2})/', $entry, $matches)) {
			return $matches[1];
		}
		throw new \RuntimeException("Invalid filename: {$entry}");
	}
}
