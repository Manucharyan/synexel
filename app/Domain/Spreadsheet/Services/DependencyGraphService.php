<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\DTOs\A1Notation;
use App\Domain\Spreadsheet\Models\Cell;
use App\Domain\Spreadsheet\Models\NamedRange;
use App\Domain\Spreadsheet\Models\Sheet;

class DependencyGraphService
{
    /** @return array<string, list<string>> */
    public function build(array $formulaCells, callable $cellKey): array
    {
        $graph = [];

        foreach ($formulaCells as $key => $formula) {
            $graph[$key] = [];
            $refs = $this->extractReferences($formula);

            foreach ($refs as $ref) {
                $graph[$key][] = $cellKey($ref['row'], $ref['col']);
            }
        }

        return $graph;
    }

    /** @return list<string> */
    public function sort(array $graph): array
    {
        $visited = [];
        $stack = [];
        $order = [];

        foreach (array_keys($graph) as $node) {
            if (! isset($visited[$node])) {
                $this->visit($node, $graph, $visited, $stack, $order);
            }
        }

        return $order;
    }

    /** @return list<array{row: int, col: int}> */
    public function extractReferences(string $formula): array
    {
        $refs = [];
        $normalized = ltrim($formula);

        if (! str_starts_with($normalized, '=')) {
            return $refs;
        }

        if (preg_match_all('/(?:([A-Za-z][A-Za-z0-9_]*)!)?(\$?[A-Z]+\$?\d+)(?::(\$?[A-Z]+\$?\d+))?/', $normalized, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (! empty($match[3])) {
                    $range = A1Notation::parseRange($match[2].':'.$match[3]);
                    for ($r = $range['start_row']; $r <= $range['end_row']; $r++) {
                        for ($c = $range['start_col']; $c <= $range['end_col']; $c++) {
                            $refs[] = ['row' => $r, 'col' => $c];
                        }
                    }
                } else {
                    $coords = A1Notation::toCoordinates($match[2]);
                    $refs[] = $coords;
                }
            }
        }

        return $refs;
    }

    private function visit(string $node, array $graph, array &$visited, array &$stack, array &$order): void
    {
        $visited[$node] = true;
        $stack[$node] = true;

        foreach ($graph[$node] ?? [] as $dep) {
            if (isset($stack[$dep])) {
                continue;
            }

            if (! isset($visited[$dep])) {
                $this->visit($dep, $graph, $visited, $stack, $order);
            }
        }

        unset($stack[$node]);
        $order[] = $node;
    }
}
