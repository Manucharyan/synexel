<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\DTOs\A1Notation;
use App\Domain\Spreadsheet\Models\NamedRange;
use App\Domain\Spreadsheet\Models\Sheet;

class FormulaEvaluationService
{
    private array $values = [];

    /** @var \Illuminate\Support\Collection<string, \App\Domain\Spreadsheet\Models\Cell> */
    private $cells;

    public function __construct(
        private readonly DependencyGraphService $dependencyGraph,
    ) {}

    public function recalculateSheet(Sheet $sheet, bool $onlyAffected = false, array $affectedCells = []): void
    {
        $namedRanges = $sheet->workbook->namedRanges()->get()->keyBy(fn (NamedRange $nr) => strtoupper($nr->name));
        $cells = $sheet->cells()->get()->keyBy(fn ($cell) => $this->key($cell->row, $cell->col));

        $formulaCells = [];
        foreach ($cells as $key => $cell) {
            if ($cell->formula) {
                $formulaCells[$key] = $this->expandNamedRanges($cell->formula, $namedRanges, $sheet->name);
            }
        }

        $graph = $this->dependencyGraph->build($formulaCells, fn (int $row, int $col) => $this->key($row, $col));
        $order = $this->dependencyGraph->sort($graph);

        $values = [];
        foreach ($cells as $key => $cell) {
            $values[$key] = $cell->formula ? null : $this->toScalar($cell->raw_value, $cell->value_type?->value ?? 'string');
        }

        foreach ($order as $key) {
            if (! isset($formulaCells[$key])) {
                continue;
            }

            if ($onlyAffected && ! empty($affectedCells) && ! $this->isAffected($key, $affectedCells, $graph)) {
                continue;
            }

            [$row, $col] = $this->parseKey($key);
            $cell = $cells[$key] ?? null;

            if (! $cell) {
                continue;
            }

            try {
                $result = $this->evaluate($formulaCells[$key], $values, $cells, $sheet->name);
                $cell->computed_value = $this->formatResult($result);
                $values[$key] = $result;
            } catch (\Throwable) {
                $cell->computed_value = '#ERROR!';
                $values[$key] = '#ERROR!';
            }

            $cell->save();
        }
    }

    private function evaluate(string $formula, array $values, $cells, string $sheetName): mixed
    {
        $this->values = $values;
        $this->cells = $cells;

        $expr = ltrim($formula);
        if (! str_starts_with($expr, '=')) {
            return $expr;
        }

        $expr = substr($expr, 1);
        $expr = $this->expandNamedRanges($expr, collect(), $sheetName);
        $expr = $this->replaceCellReferences($expr, $values, $cells);

        return $this->evaluateExpression($expr);
    }

    private function replaceCellReferences(string $expr, array $values, $cells): string
    {
        return preg_replace_callback('/(?<![A-Z0-9:])(\$?[A-Z]+\$?\d+)(?!\s*:)/', function ($match) use ($values, $cells) {
            $coords = A1Notation::toCoordinates($match[1]);
            $key = $this->key($coords['row'], $coords['col']);
            $value = $values[$key] ?? null;

            if ($value === null && isset($cells[$key])) {
                $value = $this->toScalar($cells[$key]->raw_value, $cells[$key]->value_type?->value ?? 'string');
            }

            if ($value === null || $value === '') {
                return '0';
            }

            if (is_bool($value)) {
                return $value ? 'TRUE' : 'FALSE';
            }

            if (is_string($value) && ! is_numeric($value)) {
                return '"'.str_replace('"', '""', $value).'"';
            }

            return (string) $value;
        }, $expr) ?? $expr;
    }

    private function evaluateExpression(string $expr): mixed
    {
        $expr = trim($expr);

        if (preg_match('/^"(.*)"$/s', $expr, $m)) {
            return str_replace('""', '"', $m[1]);
        }

        if (strcasecmp($expr, 'TRUE') === 0) {
            return true;
        }

        if (strcasecmp($expr, 'FALSE') === 0) {
            return false;
        }

        if (is_numeric($expr)) {
            return str_contains($expr, '.') ? (float) $expr : (int) $expr;
        }

        if (preg_match('/^(SUM|AVERAGE|MIN|MAX|COUNT|COUNTA|MEDIAN|ROUND|ABS|POWER|SQRT)\((.+)\)$/i', $expr, $m)) {
            $fn = strtoupper($m[1]);
            $args = $this->parseFunctionArgs($m[2]);

            if (in_array($fn, ['SUM', 'AVERAGE', 'MIN', 'MAX', 'COUNT', 'COUNTA', 'MEDIAN'], true)) {
                $nums = $this->collectNumbers($args);
                $all = $this->collectAll($args);

                return match ($fn) {
                    'SUM' => array_sum($nums),
                    'AVERAGE' => count($nums) ? array_sum($nums) / count($nums) : 0,
                    'MIN' => count($nums) ? min($nums) : 0,
                    'MAX' => count($nums) ? max($nums) : 0,
                    'COUNT' => count($nums),
                    'COUNTA' => count(array_filter($all, fn ($v) => $v !== null && $v !== '')),
                    'MEDIAN' => $this->median($nums),
                };
            }

            $first = trim($args[0] ?? '0');
            $second = trim($args[1] ?? '0');

            return match ($fn) {
                'ROUND' => round((float) $this->evaluateExpression($first), (int) $this->evaluateExpression($second)),
                'ABS' => abs((float) $this->evaluateExpression($first)),
                'POWER' => pow((float) $this->evaluateExpression($first), (float) $this->evaluateExpression($second)),
                'SQRT' => sqrt(max(0, (float) $this->evaluateExpression($first))),
            };
        }

        if (preg_match('/^IF\((.+)\)$/is', $expr, $m)) {
            $ifParts = $this->parseFunctionArgs($m[1]);
            $condition = $this->evaluateExpression(trim($ifParts[0]));

            return $this->evaluateExpression(trim($this->toBool($condition) ? $ifParts[1] : $ifParts[2]));
        }

        if (preg_match('/^CONCAT\((.+)\)$/i', $expr, $m)) {
            $parts = $this->parseFunctionArgs($m[1]);

            return implode('', array_map(fn ($p) => (string) $this->evaluateExpression(trim($p)), $parts));
        }

        if (preg_match('/^AND\((.+)\)$/i', $expr, $m)) {
            foreach ($this->parseFunctionArgs($m[1]) as $arg) {
                if (! $this->toBool($this->evaluateExpression(trim($arg)))) {
                    return false;
                }
            }

            return true;
        }

        if (preg_match('/^OR\((.+)\)$/i', $expr, $m)) {
            foreach ($this->parseFunctionArgs($m[1]) as $arg) {
                if ($this->toBool($this->evaluateExpression(trim($arg)))) {
                    return true;
                }
            }

            return false;
        }

        if (preg_match('/^IFERROR\((.+)\)$/is', $expr, $m)) {
            $parts = $this->parseFunctionArgs($m[1]);

            try {
                $result = $this->evaluateExpression(trim($parts[0] ?? '0'));
                if ($this->isError($result)) {
                    return $this->evaluateExpression(trim($parts[1] ?? '""'));
                }

                return $result;
            } catch (\Throwable) {
                return $this->evaluateExpression(trim($parts[1] ?? '""'));
            }
        }

        if (preg_match('/^TODAY\(\)$/i', $expr)) {
            return date('Y-m-d');
        }

        if (preg_match('/^NOW\(\)$/i', $expr)) {
            return date('Y-m-d H:i:s');
        }

        $comparison = $this->tryEvaluateComparison($expr);
        if ($comparison !== null) {
            return $comparison;
        }

        if (preg_match('/^[0-9.+\-*\/()\s]+$/', $expr)) {
            return $this->evaluateArithmetic($expr);
        }

        throw new \RuntimeException("Unsupported formula: {$expr}");
    }

    private function isError(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, '#');
    }

    private function tryEvaluateComparison(string $expr): ?bool
    {
        $split = $this->splitAtComparisonOp($expr);
        if ($split === null) {
            return null;
        }

        [$left, $op, $right] = $split;
        $l = $this->evaluateExpression(trim($left));
        $r = $this->evaluateExpression(trim($right));

        if ($this->isError($l) || $this->isError($r)) {
            throw new \RuntimeException('Comparison error');
        }

        if (is_string($l) && is_string($r) && ! is_numeric($l) && ! is_numeric($r)) {
            $cmp = strcmp($l, $r);

            return match ($op) {
                '=' => $cmp === 0,
                '<>' => $cmp !== 0,
                '>' => $cmp > 0,
                '<' => $cmp < 0,
                '>=' => $cmp >= 0,
                '<=' => $cmp <= 0,
            };
        }

        $ln = is_numeric($l) ? (float) $l : 0.0;
        $rn = is_numeric($r) ? (float) $r : 0.0;

        return match ($op) {
            '=' => $ln == $rn,
            '<>' => $ln != $rn,
            '>' => $ln > $rn,
            '<' => $ln < $rn,
            '>=' => $ln >= $rn,
            '<=' => $ln <= $rn,
        };
    }

    /** @return array{0:string,1:string,2:string}|null */
    private function splitAtComparisonOp(string $expr): ?array
    {
        $ops = ['>=', '<=', '<>', '=', '>', '<'];
        $depth = 0;
        $inString = false;

        for ($i = 0; $i < strlen($expr); $i++) {
            $ch = $expr[$i];

            if ($ch === '"') {
                $inString = ! $inString;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ($ch === '(') {
                $depth++;
                continue;
            }

            if ($ch === ')') {
                $depth--;
                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            foreach ($ops as $op) {
                if (substr($expr, $i, strlen($op)) !== $op) {
                    continue;
                }

                if ($op === '=' && $i > 0 && $expr[$i - 1] === '!') {
                    continue;
                }

                if ($op === '<' && $i + 1 < strlen($expr) && $expr[$i + 1] === '>') {
                    continue;
                }

                $left = substr($expr, 0, $i);
                $right = substr($expr, $i + strlen($op));

                if (trim($left) === '' || trim($right) === '') {
                    continue;
                }

                return [trim($left), $op, trim($right)];
            }
        }

        return null;
    }

    private function evaluateArithmetic(string $expr): mixed
    {
        $expr = trim($expr);

        while (($start = strrpos($expr, '(')) !== false) {
            $depth = 0;
            $end = null;
            for ($i = $start; $i < strlen($expr); $i++) {
                if ($expr[$i] === '(') {
                    $depth++;
                } elseif ($expr[$i] === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $end = $i;
                        break;
                    }
                }
            }
            if ($end === null) {
                break;
            }
            $inner = substr($expr, $start + 1, $end - $start - 1);
            $result = $this->evaluateArithmetic($inner);
            if ($result === '#DIV/0!') {
                return '#DIV/0!';
            }
            $expr = substr($expr, 0, $start).(string) $result.substr($expr, $end + 1);
        }

        if (preg_match('/^(-?\d+(?:\.\d+)?)$/', $expr, $m)) {
            return str_contains($m[1], '.') ? (float) $m[1] : (int) $m[1];
        }

        if ($this->isError($expr)) {
            return $expr;
        }

        if (preg_match('/(-?\d+(?:\.\d+)?)\s*([+\-])\s*(.+)$/', $expr, $m)) {
            $left = $this->evaluateArithmetic($m[1]);
            $right = $this->evaluateArithmetic($m[3]);
            if ($left === '#DIV/0!' || $right === '#DIV/0!') {
                return '#DIV/0!';
            }

            return $m[2] === '+' ? $left + $right : $left - $right;
        }

        if (preg_match('/(-?\d+(?:\.\d+)?)\s*([*\/])\s*(-?\d+(?:\.\d+)?)/', $expr, $m, PREG_OFFSET_CAPTURE)) {
            $left = (float) $m[1][0];
            $op = $m[2][0];
            $right = (float) $m[3][0];
            $result = $op === '*' ? $left * $right : ($right == 0 ? '#DIV/0!' : $left / $right);
            $expr = substr($expr, 0, $m[0][1]).(string) $result.substr($expr, $m[0][1] + strlen($m[0][0]));

            return $this->evaluateArithmetic($expr);
        }

        if (is_numeric($expr)) {
            return str_contains($expr, '.') ? (float) $expr : (int) $expr;
        }

        throw new \RuntimeException("Unsupported arithmetic: {$expr}");
    }

    private function parseFunctionArgs(string $args): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inString = false;

        for ($i = 0; $i < strlen($args); $i++) {
            $ch = $args[$i];

            if ($ch === '"') {
                $inString = ! $inString;
                $current .= $ch;
                continue;
            }

            if (! $inString && $ch === '(') {
                $depth++;
            }

            if (! $inString && $ch === ')') {
                $depth--;
            }

            if (! $inString && $ch === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $ch;
        }

        if ($current !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    private function collectNumbers(array $args): array
    {
        $nums = [];

        foreach ($args as $arg) {
            foreach ($this->expandArgValues(trim($arg)) as $val) {
                if (is_numeric($val)) {
                    $nums[] = (float) $val;
                }
            }
        }

        return $nums;
    }

    /** @return list<mixed> */
    private function collectAll(array $args): array
    {
        $all = [];

        foreach ($args as $arg) {
            foreach ($this->expandArgValues(trim($arg)) as $val) {
                $all[] = $val;
            }
        }

        return $all;
    }

    /** @return list<mixed> */
    private function expandArgValues(string $arg): array
    {
        if (preg_match('/^(\$?[A-Z]+\$?\d+):(\$?[A-Z]+\$?\d+)$/i', $arg)) {
            $range = A1Notation::parseRange($arg);
            $values = [];
            for ($r = $range['start_row']; $r <= $range['end_row']; $r++) {
                for ($c = $range['start_col']; $c <= $range['end_col']; $c++) {
                    $values[] = $this->getCellValue($r, $c);
                }
            }

            return $values;
        }

        return [$this->evaluateExpression($arg)];
    }

    private function getCellValue(int $row, int $col): mixed
    {
        $key = $this->key($row, $col);

        if (array_key_exists($key, $this->values) && $this->values[$key] !== null) {
            return $this->values[$key];
        }

        if (isset($this->cells[$key])) {
            return $this->toScalar($this->cells[$key]->raw_value, $this->cells[$key]->value_type?->value ?? 'string');
        }

        return null;
    }

    private function median(array $nums): float
    {
        if (count($nums) === 0) {
            return 0;
        }

        sort($nums);
        $mid = intdiv(count($nums), 2);

        return count($nums) % 2
            ? $nums[$mid]
            : ($nums[$mid - 1] + $nums[$mid]) / 2;
    }

    private function expandNamedRanges(string $formula, $namedRanges, string $defaultSheet): string
    {
        return preg_replace_callback('/\b([A-Za-z_][A-Za-z0-9_]*)\b/', function ($match) use ($namedRanges) {
            $name = strtoupper($match[1]);
            if (! $namedRanges->has($name)) {
                return $match[0];
            }

            /** @var NamedRange $range */
            $range = $namedRanges[$name];

            return $range->sheet_name.'!'.$range->range_a1;
        }, $formula) ?? $formula;
    }

    private function toScalar(?string $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'number' => is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        if (is_string($value)) {
            return strtoupper($value) === 'TRUE' || $value !== '';
        }

        return (bool) $value;
    }

    private function formatResult(mixed $result): string
    {
        if ($result === '#DIV/0!') {
            return '#DIV/0!';
        }

        if (is_bool($result)) {
            return $result ? 'TRUE' : 'FALSE';
        }

        return (string) $result;
    }

    private function key(int $row, int $col): string
    {
        return "{$row}:{$col}";
    }

    private function parseKey(string $key): array
    {
        [$row, $col] = explode(':', $key);

        return [(int) $row, (int) $col];
    }

    private function isAffected(string $key, array $affectedCells, array $graph): bool
    {
        if (in_array($key, $affectedCells, true)) {
            return true;
        }

        foreach ($graph as $node => $deps) {
            if ($node === $key) {
                foreach ($deps as $dep) {
                    if (in_array($dep, $affectedCells, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
