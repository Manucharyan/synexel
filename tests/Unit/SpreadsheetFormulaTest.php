<?php

namespace Tests\Unit;

use App\Domain\Spreadsheet\DTOs\A1Notation;
use App\Domain\Spreadsheet\Services\DependencyGraphService;
use PHPUnit\Framework\TestCase;

class SpreadsheetFormulaTest extends TestCase
{
    public function test_a1_notation_parsing(): void
    {
        $coords = A1Notation::toCoordinates('B2');
        $this->assertEquals(2, $coords['row']);
        $this->assertEquals(2, $coords['col']);

        $this->assertEquals('C10', A1Notation::fromCoordinates(10, 3));
    }

    public function test_range_parsing(): void
    {
        $range = A1Notation::parseRange('A1:C3');
        $this->assertEquals(1, $range['start_row']);
        $this->assertEquals(3, $range['end_col']);
        $this->assertEquals(9, A1Notation::cellCount('A1:C3'));
    }

    public function test_dependency_extraction(): void
    {
        $service = new DependencyGraphService;
        $refs = $service->extractReferences('=A1+B2*SUM(C1:C10)');

        $this->assertNotEmpty($refs);
        $this->assertContains(['row' => 1, 'col' => 1], $refs);
    }
}
