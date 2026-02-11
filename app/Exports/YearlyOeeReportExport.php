<?php

namespace App\Exports;

use App\Models\DailyOeeMetric;
use App\Models\Machine;
use App\Models\ProductionTarget;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class YearlyOeeReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected array $reportData;
    protected int $year;
    protected array $monthLabels = [];

    public function __construct(array $reportData, int $year)
    {
        $this->reportData = $reportData;
        $this->year = $year;
        
        // Generate month labels like "Jan-25", "Feb-25", etc.
        for ($month = 1; $month <= 12; $month++) {
            $date = Carbon::create($year, $month, 1);
            $this->monthLabels[$month] = $date->format('M-y') . ' Oec';
        }
    }

    public function collection()
    {
        return collect($this->reportData['machines'] ?? []);
    }

    public function headings(): array
    {
        $headings = [
            'Machine',
            'Line',
            'Plant',
            'Oec Target',
        ];
        
        // Add monthly OEC columns
        for ($month = 1; $month <= 12; $month++) {
            $headings[] = $this->monthLabels[$month];
        }
        
        // Add summary columns
        $headings = array_merge($headings, [
            'Availability',
            'Utilization',
            'Quality',
            'Total Time Lost(Hrs)',
            'Total Production Waste',
            'Uom',
        ]);
        
        return $headings;
    }

    public function map($machine): array
    {
        $row = [
            $machine['name'] ?? 'N/A',
            $machine['line_name'] ?? 'N/A',
            $machine['plant_name'] ?? 'N/A',
            isset($machine['target_oee']) ? round($machine['target_oee'], 2) . '%' : 'N/A',
        ];
        
        // Add monthly OEC values
        for ($month = 1; $month <= 12; $month++) {
            $monthKey = 'month_' . $month;
            $value = $machine['monthly_oee'][$monthKey] ?? null;
            $row[] = $value !== null ? round($value, 2) . '%' : '-';
        }
        
        // Add summary values
        $row[] = isset($machine['availability']) ? round($machine['availability'], 2) . '%' : '-';
        $row[] = isset($machine['utilization']) ? round($machine['utilization'], 2) . '%' : '-';
        $row[] = isset($machine['quality']) ? round($machine['quality'], 2) . '%' : '-';
        $row[] = isset($machine['time_lost_hours']) ? round($machine['time_lost_hours'], 2) : '-';
        $row[] = isset($machine['total_waste']) ? number_format($machine['total_waste']) : '-';
        $row[] = $machine['uom'] ?? '-';
        
        return $row;
    }

    public function styles(Worksheet $sheet): array
    {
        // Calculate last column letter (A=1, so column 22 = V)
        $lastCol = 'V';
        
        return [
            // Header row styling
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F81BD'],
                ],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }

    public function title(): string
    {
        return 'Yearly OEE Report ' . $this->year;
    }
}
