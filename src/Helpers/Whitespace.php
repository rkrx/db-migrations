<?php
namespace Kir\DB\Migrations\Helpers;

use InvalidArgumentException;

class Whitespace {
	public function __construct(private int $tabWidth = 4, private string $lineBreak = "\n") {}

	/**
	 * @param string $data
	 * @return string
	 */
	public function stripMargin(string $data): string {
		$lines = preg_split("{\\r?\\n}", $data);
		if(!is_array($lines)) {
			throw new InvalidArgumentException("Invalid data");
		}
		$tabs = str_repeat(' ', $this->tabWidth);
		$lines = $this->stripHead($lines);
		$lines = array_reverse($lines);
		$lines = $this->stripHead($lines);
		$lines = array_reverse($lines);
		$lines = $this->stripLeft($lines, $tabs);
		$lines = $this->stripRight($lines);
		return implode($this->lineBreak, $lines);
	}

	/**
	 * @param string[] $lines
	 * @return string[]
	 */
	private function stripHead(array $lines): array {
		while(count($lines) > 0 && trim($lines[0]) === '') {
			array_shift($lines);
			$lines = array_values($lines);
		}
		return $lines;
	}

	/**
	 * @param string[] $lines
	 * @param string $tabs
	 * @return string[]
	 */
	private function stripLeft(array $lines, string $tabs): array {
		$indentation = null;

		$result = [];
		foreach($lines as $line) {
			preg_match('/^([\\t ]*)/', $line, $matches);
			$whitespace = strtr($matches[1], ["\t" => $tabs]);
			$result[] = $whitespace . ltrim($line);
			if($indentation === null) {
				$indentation = strlen($whitespace);
			}
			$indentation = min(strlen($whitespace), $indentation);
		}

		if($indentation === null) {
			return $lines;
		}

		return array_map(static fn($line) => substr($line, $indentation), $result);
	}

	/**
	 * @param string[] $lines
	 * @return string[]
	 */
	private function stripRight(array $lines): array {
		return array_map('rtrim', $lines);
	}
}