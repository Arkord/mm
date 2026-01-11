<?php

use App\Models\BuyItem;
use App\Models\Sale;
use App\Models\Company;
use App\Models\Balance;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

new class extends Component {

    public $selectedCompany;
    public $selectedYear;
    public $companies = [];
    public $years = [];

    private array $materials = [
        'FIERRO','LAMINA','COBRE','BRONCE','ALUMINIO',
        'BOTE','ARCHIVO','CARTON','PLASTICO','PET','BATERIAS','VIDRIO'
    ];

    public function mount()
    {
        $today = now();
        $this->companies = Company::pluck('name', 'id')->toArray();
        $this->selectedCompany = Auth::user()->company_id ?? array_key_first($this->companies);
        $this->years = range(2020, $today->year);
        $this->selectedYear = $today->year;
    }

    private function weeksOfMonth(int $year, int $month): array
    {
        $weeks = [];

        $firstOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $lastOfMonth  = $firstOfMonth->copy()->endOfMonth()->endOfDay();

        $start = $firstOfMonth->copy()->startOfWeek(Carbon::MONDAY);

        while (true) {
            $end = $start->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

            if ($start->gt($lastOfMonth)) {
                break;
            }

            if ($end->gte($firstOfMonth)) {
                $weeks[] = [
                    'start' => $start->copy(),
                    'end'   => $end->copy(),
                ];
            }

            $start->addWeek();
        }

        return $weeks;
    }

    private function sumBuys(int $company, $from, $to)
    {
        return BuyItem::selectRaw('material, SUM(kgs) as kgs, SUM(total) as total')
            ->whereHas('buy', fn ($q) =>
                $q->where('company_id', $company)
                    ->whereBetween('fecha', [$from, $to])
            )
            ->groupBy('material')
            ->get()
            ->keyBy('material');
    }

    private function sumSales(int $company, $from, $to, string $type)
    {
        return Sale::selectRaw('material, SUM(kgs) as kgs, SUM(total) as total')
            ->where('company_id', $company)
            ->where('type', $type)
            ->whereBetween('fecha', [$from, $to])
            ->groupBy('material')
            ->get()
            ->keyBy('material');
    }

    public function exportToExcel()
    {
        $spreadsheet = new Spreadsheet();

        $styleKg = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => Color::COLOR_WHITE]],
            'font' => ['color' => ['argb' => Color::COLOR_BLACK]],
            'numberFormat' => ['formatCode' => '#,##0.000'],
        ];

        $styleAmt = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFBFBFBF']],
            'font' => ['color' => ['argb' => Color::COLOR_BLACK]],
            'numberFormat' => ['formatCode' => '$#,##0.00;-$#,##0.00'],
        ];

        $styleHeaderRed = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC00000']],
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];

        // Inicializamos carry y lastWeek
        $lastWeekTotalKg  = array_fill_keys($this->materials, 0.0);
        $lastWeekTotalAmt = array_fill_keys($this->materials, 0.0);

        // === CARGA LA SUMA DE TODOS LOS BALANCES DEL AÑO ANTERIOR POR MATERIAL ===
        $previousYear = $this->selectedYear - 1;

        $previousBalances = Balance::where('company_id', $this->selectedCompany)
            ->where('anio', $previousYear)
            ->selectRaw('material, SUM(kgs) as total_kgs, SUM(monto) as total_monto')
            ->groupBy('material')
            ->get()
            ->keyBy('material');

        $applyInitialBalance = true;

        foreach (range(1, 12) as $month) {

            $sheet = $month === 1 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle(Carbon::create()->month($month)->locale('es')->isoFormat('MMMM'));

            $sheet->getStyle("A1:Z58")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_BLACK);
            $sheet->getStyle("A1:Z58")->getFont()->setBold(true)->getColor()->setARGB(Color::COLOR_WHITE);

            $companyName = $this->companies[$this->selectedCompany] ?? '';
            $endCol = Coordinate::stringFromColumnIndex(count($this->materials) * 2 + 1);
            $sheet->mergeCells("A1:{$endCol}1");
            $sheet->setCellValue("A1", $companyName);
            $sheet->getStyle("A1")->getFont()->setSize(24)->setBold(true)->getColor()->setARGB(Color::COLOR_WHITE);
            $sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells("A2:{$endCol}2");
            $sheet->setCellValue("A2", "Inventario semanal – Año {$this->selectedYear}");
            $sheet->getStyle("A2")->getFont()->setSize(14)->setBold(true)->getColor()->setARGB(Color::COLOR_WHITE);
            $sheet->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row = 5;

            $weeks = [];
            $firstOfMonth = Carbon::create($this->selectedYear, $month, 1)->startOfDay();
            $lastOfMonth  = $firstOfMonth->copy()->endOfMonth()->endOfDay();
            $start = $firstOfMonth->copy()->startOfWeek(Carbon::MONDAY);

            while ($start->lte($lastOfMonth)) {
                $end = $start->copy()->addDays(6)->endOfDay();
                $weeks[] = ['start' => $start->copy(), 'end' => $end->copy()];
                $start->addWeek();
            }

            foreach ($weeks as $index => $range) {

                // === APLICAR LA SUMA DE TODOS LOS BALANCES DEL AÑO ANTERIOR ===
                if ($month === 1 && $index === 0 && $applyInitialBalance) {
                    foreach ($this->materials as $mat) {
                        $balance = $previousBalances->get($mat);
                        $lastWeekTotalKg[$mat]  = $balance?->total_kgs ?? 0.0;
                        $lastWeekTotalAmt[$mat] = $balance?->total_monto ?? 0.0;
                    }
                    $applyInitialBalance = false;
                }

                $sheet->mergeCells("B{$row}:C{$row}");
                $sheet->setCellValue("B{$row}", "Semana ".($index+1)." ({$range['start']->isoFormat('DD MMM')} - {$range['end']->isoFormat('DD MMM')})");
                $sheet->getStyle("B{$row}:C{$row}")->applyFromArray($styleHeaderRed);
                $row++;

                $col = 2;
                foreach ($this->materials as $mat) {
                    $from = Coordinate::stringFromColumnIndex($col).$row;
                    $to   = Coordinate::stringFromColumnIndex($col+1).$row;
                    $sheet->mergeCells("$from:$to");
                    $sheet->setCellValue($from, $mat);
                    $sheet->getStyle("$from:$to")->applyFromArray($styleHeaderRed);
                    $col += 2;
                }
                $row++;

                $col = 2;
                foreach ($this->materials as $_) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$row, 'Kg');
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col+1).$row, '$');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->applyFromArray($styleKg);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->applyFromArray($styleAmt);
                    $col += 2;
                }
                $row++;

                $buys  = $this->sumBuys($this->selectedCompany, $range['start'], $range['end']);
                $patio = $this->sumSales($this->selectedCompany, $range['start'], $range['end'], Sale::TYPE_PATIO);
                $gen   = $this->sumSales($this->selectedCompany, $range['start'], $range['end'], Sale::TYPE_GENERAL);

                $rows = [
                    'Semana anterior' => fn($m) => [-$lastWeekTotalKg[$m], -$lastWeekTotalAmt[$m]],
                    'Compras semana'  => fn($m) => [$buys->get($m)->kgs ?? 0, $buys->get($m)->total ?? 0],
                    'Ventas patio'    => fn($m) => [$patio->get($m)->kgs ?? 0, $patio->get($m)->total ?? 0],
                    'Ventas general'  => fn($m) => [$gen->get($m)->kgs ?? 0, $gen->get($m)->total ?? 0],
                ];

                foreach ($rows as $label => $calc) {
                    $sheet->setCellValue("A{$row}", $label);
                    $col = 2;
                    foreach ($this->materials as $mat) {
                        [$kg, $amt] = $calc($mat);
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$row, $kg);
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col+1).$row, $amt);
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->applyFromArray($styleKg);
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->applyFromArray($styleAmt);
                        $col += 2;
                    }
                    $row++;
                }

                // Cálculo del total de la semana
                $weekTotalKg  = [];
                $weekTotalAmt = [];

                foreach ($this->materials as $mat) {
                    $buyKg   = $buys->get($mat)->kgs ?? 0;
                    $buyAmt  = $buys->get($mat)->total ?? 0;
                    $saleKg  = ($patio->get($mat)->kgs ?? 0) + ($gen->get($mat)->kgs ?? 0);
                    $saleAmt = ($patio->get($mat)->total ?? 0) + ($gen->get($mat)->total ?? 0);

                    $weekTotalKg[$mat]  = $saleKg - (-$lastWeekTotalKg[$mat] + $buyKg);
                    $weekTotalAmt[$mat] = $saleAmt - (-$lastWeekTotalAmt[$mat] + $buyAmt);
                }

                // Escribir TOTAL
                $sheet->setCellValue("A{$row}", 'TOTAL');
                $col = 2;
                foreach ($this->materials as $mat) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$row, $weekTotalKg[$mat]);
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col+1).$row, $weekTotalAmt[$mat]);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->applyFromArray($styleKg);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->applyFromArray($styleAmt);
                    $col += 2;
                }
                $row++;

                // Actualizar carry para la próxima semana
                $lastWeekTotalKg  = $weekTotalKg;
                $lastWeekTotalAmt = $weekTotalAmt;

                $row += 2; // Espacio
            }

            $sheet->getColumnDimension('A')->setWidth(22);
            foreach (range('B', $sheet->getHighestColumn()) as $c) {
                $sheet->getColumnDimension($c)->setWidth(14);
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $filename = "inventario_{$this->selectedYear}.xlsx";
        return response()->streamDownload(
            fn() => $writer->save('php://output'),
            $filename
        );
    }
};
?>

<div class="p-6">
    <h1 class="text-xl font-bold mb-6">Reporte de inventario</h1>

    <div class="flex items-end gap-4">
        <div>
            <label class="block text-sm font-medium">Empresa</label>
            <select wire:model="selectedCompany" class="border rounded p-2">
                @foreach($companies as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Año</label>
            <select wire:model="selectedYear" class="border rounded p-2">
                @foreach($years as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>

        <button wire:click="exportToExcel"
                wire:loading.attr="disabled"
                class="relative bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-60 cursor-pointer">
            <span wire:loading.remove>
                Generar XLSX
            </span>
            <span wire:loading class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                Generando...
            </span>
        </button>
    </div>
</div>