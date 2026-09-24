<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/** One right-to-left sheet of InvoiceFullReportExport; $headerRow (1-based, 0 = none) is bold, $totalRow bolds the last row. */
class InvoiceFullReportSheet implements FromArray, WithTitle, WithEvents, ShouldAutoSize
{
    public function __construct(protected string $title, protected array $rows, protected int $headerRow = 1, protected bool $totalRow = false)
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $sheet->setRightToLeft(true);
            $last = $sheet->getHighestColumn();
            if ($this->headerRow > 0) {
                $sheet->getStyle("A{$this->headerRow}:{$last}{$this->headerRow}")->getFont()->setBold(true);
                $sheet->freezePane('A' . ($this->headerRow + 1));
            } else {
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1:A' . $sheet->getHighestRow())->getFont()->setBold(true);
            }
            if ($this->totalRow) {
                $row = $sheet->getHighestRow();
                $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true);
            }
        }];
    }
}
