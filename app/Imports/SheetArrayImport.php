<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithFormatData;

/**
 * Reads the first sheet as a plain 2D array.
 * WithFormatData returns cell values as displayed in Excel (dates, phone numbers, leading zeros).
 */
class SheetArrayImport implements ToArray, WithFormatData
{
    /** @var array<int, array<int, mixed>> */
    public array $rows = [];

    private bool $loaded = false;

    public function array(array $array): void
    {
        if ($this->loaded) {
            return; // first sheet only
        }

        $this->rows = $array;
        $this->loaded = true;
    }
}
