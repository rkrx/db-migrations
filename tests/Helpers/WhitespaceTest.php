<?php

namespace Kir\DB\Migrations\Helpers;

use PHPUnit\Framework\TestCase;

class WhitespaceTest extends TestCase {
	public function testAll(): void {
		$testData = "
			
			
			This is a test
			
				An indented line
			This is a test
			
			
		";

		$strippedData = (new Whitespace(2, "\n"))->stripMargin($testData);
		$this->assertEquals("This is a test\n\n  An indented line\nThis is a test", $strippedData);
	}
}
