<?php

namespace App\Domain\Spreadsheet\DTOs;

class RangeRef
{
    public function __construct(
        public readonly int $startRow,
        public readonly int $startCol,
        public readonly int $endRow,
        public readonly int $endCol,
    ) {}

    public static function fromA1(string $range): self
    {
        $parsed = A1Notation::parseRange($range);

        return new self(
            $parsed['start_row'],
            $parsed['start_col'],
            $parsed['end_row'],
            $parsed['end_col'],
        );
    }

    public function cellCount(): int
    {
        return ($this->endRow - $this->startRow + 1) * ($this->endCol - $this->startCol + 1);
    }
}
