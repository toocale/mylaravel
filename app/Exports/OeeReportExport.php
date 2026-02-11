<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OeeReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $reportData;

    public function __construct($reportData)
    {
        $this->reportData = $reportData;
    }

    public function collection()
    {
        return $this->reportData['metrics'];
    }

    public function headings(): array
    {
        return [
            'Date',
            'Machine',
            'Line',
            'Plant',
            'OEE %',
            'Availability %',
            'Performance %',
            'Quality %',
            'Total Units',
            'Good Units',
            'Reject Units',
            'Downtime (min)',
        ];
    }

    public function map($metric): array
    {
        return [
            $metric->date,
            $metric->machine->name ?? 'N/A',
            $metric->machine->line->name ?? 'N/A',
            $metric->machine->line->plant->name ?? 'N/A',
            round($metric->oee, 2),
            round($metric->availability, 2),
            round($metric->performance, 2),
            round($metric->quality, 2),
            $metric->total_count,
            $metric->good_count,
            $metric->reject_count,
            $metric->downtime_minutes,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'OEE Report';
    }
}
