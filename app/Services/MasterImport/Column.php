<?php

namespace App\Services\MasterImport;

/** One column of a master-data template. */
final class Column
{
    /**
     * @param  string  $type  text | number | date | bool | list
     * @param  string|array|null  $options  allowed values (list), or the name of a live list (e.g. "agency_codes") for the dropdown
     */
    public function __construct(
        public readonly string $key,
        public readonly string $header,
        public readonly bool $required = false,
        public readonly string $type = 'text',
        public readonly string|array|null $options = null,
        public readonly string $help = '',
        public readonly string $example = '',
        public readonly int $width = 18,
        public readonly bool $secret = false,   // never exported with current data (bank account, Aadhaar)
        public readonly bool $asText = false,   // keep as text in Excel so leading zeros and long numbers survive
        public readonly bool $identifies = false, // identifies the record; required even when updating
    ) {}

    public function label(): string
    {
        return $this->header.($this->required ? ' *' : '');
    }
}
