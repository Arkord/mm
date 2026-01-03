<?php

use App\Models\BuyItem;
use App\Models\Sale;
use App\Models\Company;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
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
        $first = Carbon::create($year, $month, 1)->startOfDay();
        $last  = $first->copy()->endOfMonth();

        $start = $first->dayOfWeek === Carbon::MONDAY
            ? $first
            : $first->copy()->next(Carbon::MONDAY);

        while ($start->lte($last)) {
            $end = $start->copy()->endOfWeek(Carbon::SUNDAY);
            $weeks[] = ['start' => $start->copy(), 'end' => $end->copy()];
            $start = $end->addDay();
        }

        return $weeks;
    }

    private function sumBuys(int $company, $from, $to)
    {
        return BuyItem::whereHas('buy', fn($q) =>
            $q->where('company_id', $company)
              ->whereBetween('fecha', [$from, $to])
        )->get()->groupBy('material');
    }

    private function sumSales(int $company, $from, $to, string $type)
    {
        return Sale::where('company_id', $company)
            ->where('type', $type)
            ->whereBetween('fecha', [$from, $to])
            ->get()->groupBy('material');
    }

    public function exportToExcel()
    {
        $spreadsheet = new Spreadsheet();

        /** INVENTARIO ACUMULADO REAL */
        $carryKg  = array_fill_keys($this->materials, 0.0);
        $carryAmt = array_fill_keys($this->materials, 0.0);

        foreach (range(1, 12) as $month) {

            $sheet = $month === 1
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet();

            $sheet->setTitle(Carbon::create()->month($month)->locale('es')->isoFormat('MMMM'));

            $sheet->getStyle("A1:Z100")
    ->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()
    ->setARGB(Color::COLOR_BLACK);

$sheet->getStyle("A1:Z100")
    ->getFont()
    ->setBold(true)
    ->getColor()
    ->setARGB(Color::COLOR_WHITE);

            // NOMBRE DE LA EMPRESA
$companyName = $this->companies[$this->selectedCompany] ?? '';

$endCol = Coordinate::stringFromColumnIndex(count($this->materials) * 2 + 1);

$sheet->mergeCells("A1:{$endCol}1");
$sheet->setCellValue("A1", $companyName);

$sheet->getStyle("A1")->getFont()
    ->setSize(24)
    ->setBold(true)
    ->getColor()->setARGB(Color::COLOR_WHITE);

$sheet->getStyle("A1")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

// Dejamos espacio antes de las tablas
$row = 4;

$weeks = $this->weeksOfMonth($this->selectedYear, $month);
            $weeks = $this->weeksOfMonth($this->selectedYear, $month);
            $rowsMateriales = [];

            foreach ($weeks as $index => $range) {

                /** TÍTULO */
                $endCol = Coordinate::stringFromColumnIndex(count($this->materials) * 2 + 1);
                $sheet->mergeCells("B{$row}:C{$row}");
                $sheet->setCellValue("B{$row}", "Semana ".($index+1)." ({$range['start']->isoFormat('DD MMM')} - {$range['end']->isoFormat('DD MMM')})");
                $sheet->getStyle("B{$row}")->getFont()->setBold(true);
                $sheet->getStyle("B{$row}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFC00000');

                $row++;

                /** MATERIALES */
                $col = 2;
                $materialRow = $row; 

                foreach ($this->materials as $mat) {
                    $from = Coordinate::stringFromColumnIndex($col).$row;
                    $to   = Coordinate::stringFromColumnIndex($col+1).$row;
                    $sheet->mergeCells("$from:$to");
                    $sheet->setCellValue($from, $mat);

                    /* 
                    $sheet->getStyle("$from:$to")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC00000');

                    $sheet->getStyle("$from:$to")->getFont()->getColor()->setARGB(Color::COLOR_WHITE);

                    $sheet->getStyle("$from:$to")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                    $sheet->getStyle("$from:$to")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                    */

                    $col += 2;
                }

                $rowsMateriales[] = $materialRow;
                $row++;

                /** KG / $ */
                $col = 2;
                foreach ($this->materials as $_) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$row, 'Kg');
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col+1).$row, '$');

                     /** COLORES PARA LOS DATOS*/
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_WHITE);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getFont()->getColor()->setARGB(Color::COLOR_BLACK);

                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBFBFBF');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getFont()->getColor()->setARGB(Color::COLOR_BLACK);

                    $sheet->getStyle(
                        Coordinate::stringFromColumnIndex($col).$row.':'.
                        Coordinate::stringFromColumnIndex($col+1).$row
                    )->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $col += 2;
                }
                $row++;

                /** DATOS */
                $buys  = $this->sumBuys($this->selectedCompany, $range['start'], $range['end']);
                $patio = $this->sumSales($this->selectedCompany, $range['start'], $range['end'], Sale::TYPE_PATIO);
                $gen   = $this->sumSales($this->selectedCompany, $range['start'], $range['end'], Sale::TYPE_GENERAL);

                /** SEMANA ANTERIOR */
                $sheet->setCellValue("A{$row}", 'Semana anterior');
                $rowSemanaAnterior = $row;
                $col = 2;
                foreach ($this->materials as $mat) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$row, -1 * $carryKg[$mat]);
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col+1).$row, -1 * $carryAmt[$mat]);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getNumberFormat()->setFormatCode('#,##0.000');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getNumberFormat()->setFormatCode('$#,##0.00;-$#,##0.00');

                    /** COLORES PARA LOS DATOS*/
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_WHITE);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getFont()->getColor()->setARGB(Color::COLOR_BLACK);

                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBFBFBF');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getFont()->getColor()->setARGB(Color::COLOR_BLACK);
                    
                    $col += 2;
                }
                $row++;

                /** ACUMULADORES */
                $weekKg  = [];
                $weekAmt = [];

                /** COMPRAS */
                $sheet->setCellValue("A{$row}", 'Compras semana');
                $rowCompras = $row;
                $col = 2;
                foreach ($this->materials as $mat) {
                    $kg  = ($buys[$mat] ?? collect())->sum('kgs');
                    $amt = ($buys[$mat] ?? collect())->sum('total');
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$row, $kg);
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col+1).$row, $amt);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getNumberFormat()->setFormatCode('#,##0.000');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getNumberFormat()->setFormatCode('$#,##0.00;-$#,##0.00');

                     /** COLORES PARA LOS DATOS*/
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_WHITE);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getFont()->getColor()->setARGB(Color::COLOR_BLACK);

                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBFBFBF');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getFont()->getColor()->setARGB(Color::COLOR_BLACK);

                    $weekKg[$mat]  = ($weekKg[$mat]  ?? 0) + $kg;
                    $weekAmt[$mat] = ($weekAmt[$mat] ?? 0) + $amt;
                    $col += 2;
                }
                $row++;

                /** VENTAS PATIO */
                $sheet->setCellValue("A{$row}", 'Ventas patio');
                $rowVentasPatio = $row;
                $col = 2;
                foreach ($this->materials as $mat) {
                    $kg  = ($patio[$mat] ?? collect())->sum('kgs');
                    $amt = ($patio[$mat] ?? collect())->sum('total');
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$row, $kg);
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col+1).$row, $amt);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getNumberFormat()->setFormatCode('#,##0.000');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getNumberFormat()->setFormatCode('$#,##0.00;-$#,##0.00');

                     /** COLORES PARA LOS DATOS*/
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_WHITE);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getFont()->getColor()->setARGB(Color::COLOR_BLACK);

                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBFBFBF');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getFont()->getColor()->setARGB(Color::COLOR_BLACK);

                    $weekKg[$mat]  = ($weekKg[$mat]  ?? 0) - $kg;
                    $weekAmt[$mat] = ($weekAmt[$mat] ?? 0) - $amt;
                    $col += 2;
                }
                $row++;

                /** VENTAS GENERAL */
                $sheet->setCellValue("A{$row}", 'Ventas general');
                $rowVentasGeneral = $row;
                $col = 2;
                foreach ($this->materials as $mat) {
                    $kg  = ($gen[$mat] ?? collect())->sum('kgs');
                    $amt = ($gen[$mat] ?? collect())->sum('total');
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$row, $kg);
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col+1).$row, $amt);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getNumberFormat()->setFormatCode('#,##0.000');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getNumberFormat()->setFormatCode('$#,##0.00;-$#,##0.00');

                     /** COLORES PARA LOS DATOS*/
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_WHITE);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getFont()->getColor()->setARGB(Color::COLOR_BLACK);

                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBFBFBF');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getFont()->getColor()->setARGB(Color::COLOR_BLACK);

                    $weekKg[$mat]  = ($weekKg[$mat]  ?? 0) - $kg;
                    $weekAmt[$mat] = ($weekAmt[$mat] ?? 0) - $amt;
                    $col += 2;
                }
                $row++;

                /** TOTAL */
                $sheet->setCellValue("A{$row}", 'TOTAL');
                $col = 2;
                foreach ($this->materials as $mat) {
                    $colKg  = Coordinate::stringFromColumnIndex($col);
                    $colAmt = Coordinate::stringFromColumnIndex($col+1);

                    $sheet->setCellValue(
                        "{$colKg}{$row}",
                        "=({$colKg}{$rowVentasGeneral}+{$colKg}{$rowVentasPatio})-({$colKg}{$rowSemanaAnterior}+{$colKg}{$rowCompras})"
                    );
                    $sheet->setCellValue(
                        "{$colAmt}{$row}",
                        "=({$colAmt}{$rowVentasGeneral}+{$colAmt}{$rowVentasPatio})-({$colAmt}{$rowSemanaAnterior}+{$colAmt}{$rowCompras})"
                    );

                    $sheet->getStyle("{$colKg}{$row}")->getNumberFormat()->setFormatCode('#,##0.000');
                    $sheet->getStyle("{$colAmt}{$row}")->getNumberFormat()->setFormatCode('$#,##0.00;-$#,##0.00');

                     /** COLORES PARA LOS DATOS*/
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_WHITE);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col).$row)->getFont()->getColor()->setARGB(Color::COLOR_BLACK);

                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBFBFBF');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col+1).$row)->getFont()->getColor()->setARGB(Color::COLOR_BLACK);

                    /** CONDICIONAL NEGATIVO */
                    foreach (["{$colKg}{$row}", "{$colAmt}{$row}"] as $cell) {
                        $cond = new Conditional();
                        $cond->setConditionType(Conditional::CONDITION_CELLIS)
                             ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
                             ->addCondition('0');
                        $cond->getStyle()->getFont()->getColor()->setARGB(Color::COLOR_RED);
                        $sheet->getStyle($cell)->setConditionalStyles([$cond]);
                    }

                    /** ACTUALIZAR INVENTARIO REAL */
                    $carryKg[$mat]  = ($carryKg[$mat] ?? 0)
                    + (($buys[$mat] ?? collect())->sum('kgs'))
                    - (($patio[$mat] ?? collect())->sum('kgs'))
                    - (($gen[$mat] ?? collect())->sum('kgs'));

                $carryAmt[$mat] = ($carryAmt[$mat] ?? 0)
                    + (($buys[$mat] ?? collect())->sum('total'))
                    - (($patio[$mat] ?? collect())->sum('total'))
                    - (($gen[$mat] ?? collect())->sum('total'));

                    $col += 2;
                }

                $row += 3;
            }

            /** ANCHOS */
            $sheet->getColumnDimension('A')->setWidth(22);
            foreach (range('B', $sheet->getHighestColumn()) as $c) {
                $sheet->getColumnDimension($c)->setWidth(14);
            }

            /** FONDO NEGRO Y TEXTO BLANCO EN TODA LA HOJA */
$highestColumn = $sheet->getHighestColumn();
$highestRow    = $sheet->getHighestRow();

// $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
//     ->getFill()
//     ->setFillType(Fill::FILL_SOLID)
//     ->getStartColor()
//     ->setARGB(Color::COLOR_BLACK);

// $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
//     ->getFont()
//     ->setBold(true)
//     ->getColor()
//     ->setARGB(Color::COLOR_WHITE);

// REAPLICAR ROJO A TODAS LAS FILAS DE MATERIALES (TODAS LAS SEMANAS)
foreach ($rowsMateriales as $rowMateriales) {
    foreach ($this->materials as $i => $mat) {

        $colStart = Coordinate::stringFromColumnIndex(2 + ($i * 2));
        $colEnd   = Coordinate::stringFromColumnIndex(3 + ($i * 2));

        $sheet->getStyle("{$colStart}{$rowMateriales}:{$colEnd}{$rowMateriales}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFC00000');
            
        $sheet->getStyle("{$colStart}{$rowMateriales}:{$colEnd}{$rowMateriales}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("{$colStart}{$rowMateriales}:{$colEnd}{$rowMateriales}")
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setARGB(Color::COLOR_WHITE);
    }
}

        }

        $filename = "inventario_{$this->selectedYear}.xlsx";
        return response()->streamDownload(
            fn() => (new Xlsx($spreadsheet))->save('php://output'),
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
