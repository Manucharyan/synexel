<?php

namespace App\Domain\Spreadsheet\DTOs;

class A1Notation
{
    public static function toCoordinates(string $cell): array
    {
        if (! preg_match('/^(\$?)([A-Za-z]+)(\$?)(\d+)$/', $cell, $matches)) {
            throw new \InvalidArgumentException("Invalid A1 notation: {$cell}");
        }

        $colLetters = strtoupper($matches[2]);
        $row = (int) $matches[4];
        $col = 0;

        for ($i = 0; $i < strlen($colLetters); $i++) {
            $col = $col * 26 + (ord($colLetters[$i]) - ord('A') + 1);
        }

        return ['row' => $row, 'col' => $col];
    }

    public static function fromCoordinates(int $row, int $col): string
    {
        $letters = '';
        $c = $col;

        while ($c > 0) {
            $remainder = ($c - 1) % 26;
            $letters = chr(ord('A') + $remainder).$letters;
            $c = intdiv($c - 1, 26);
        }

        return $letters.$row;
    }

    public static function parseRange(string $range): array
    {
        $parts = explode(':', strtoupper(trim($range)));

        if (count($parts) === 1) {
            $coords = self::toCoordinates($parts[0]);

            return [
                'start_row' => $coords['row'],
                'start_col' => $coords['col'],
                'end_row' => $coords['row'],
                'end_col' => $coords['col'],
            ];
        }

        $start = self::toCoordinates($parts[0]);
        $end = self::toCoordinates($parts[1]);

        return [
            'start_row' => min($start['row'], $end['row']),
            'start_col' => min($start['col'], $end['col']),
            'end_row' => max($start['row'], $end['row']),
            'end_col' => max($start['col'], $end['col']),
        ];
    }

    public static function cellCount(string $range): int
    {
        $parsed = self::parseRange($range);

        return ($parsed['end_row'] - $parsed['start_row'] + 1)
            * ($parsed['end_col'] - $parsed['start_col'] + 1);
    }

    public static function fromRange(int $startRow, int $startCol, int $endRow, int $endCol): string
    {
        $start = self::fromCoordinates($startRow, $startCol);
        $end = self::fromCoordinates($endRow, $endCol);

        return $start === $end ? $start : $start.':'.$end;
    }
}
