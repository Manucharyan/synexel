<?php

namespace App\Domain\Spreadsheet\DTOs;

class CellUpdate
{
    public function __construct(
        public readonly int $row,
        public readonly int $col,
        public readonly ?string $value = null,
        public readonly ?string $formula = null,
        public readonly ?array $style = null,
        public readonly bool $clear = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            row: (int) $data['row'],
            col: (int) $data['col'],
            value: array_key_exists('value', $data) ? ($data['value'] === null ? null : (string) $data['value']) : null,
            formula: isset($data['formula']) ? (string) $data['formula'] : null,
            style: $data['style'] ?? null,
            clear: (bool) ($data['clear'] ?? false),
        );
    }
}
