<?php

use App\Models\BuyItem;
use App\Models\Expense;
use App\Models\Cash;
use App\Models\Sale;
use App\Models\Company;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

new class extends Component {
    public $items = [];
    public $expenses = [];
    public $totalCaja = 0;
    public $totalCompras = 0;
    public $totalGastos = 0;
    public $totalVentas = 0;
    public $sobrante = 0;
    public $selectedYear;
    public $selectedMonth;
    public $selectedWeek;
    public $selectedCompany;
    public $years = [];
    public $months = [];
    public $weeks = [];
    public $companies = [];

    /**
     * Sanitize a value for JSON (Livewire) and Excel (UTF-8 encoding).
     */
    private function sanitizeForJson($value)
    {
        if (is_null($value) || $value === '-' || is_numeric($value)) {
            return $value;
        }

        $string = (string) $value;
        Log::debug('Sanitizing value', ['raw' => bin2hex($string), 'value' => $string]);

        if (function_exists('mb_convert_encoding')) {
            $sanitized = mb_convert_encoding($string, 'UTF-8', 'auto');
            Log::debug('Sanitized to UTF-8', ['sanitized' => $sanitized]);
            return $sanitized;
        } elseif (function_exists('iconv')) {
            $sanitized = iconv('UTF-8', 'UTF-8//IGNORE', $string);
            Log::debug('Sanitized with iconv', ['sanitized' => $sanitized]);
            return $sanitized;
        }

        $sanitized = utf8_encode($string);
        Log::debug('Sanitized with utf8_encode', ['sanitized' => $sanitized]);
        return $sanitized;
    }

    /**
     * Sanitize collection data for Livewire JSON serialization.
     */
    private function sanitizeCollection($collection)
    {
        return $collection->map(function ($item) {
            if (isset($item->material)) {
                $item->material = $this->sanitizeForJson($item->material);
            }
            if (isset($item->descripcion)) {
                $item->descripcion = $this->sanitizeForJson($item->descripcion);
            }
            return $item;
        });
    }

    public function mount()
    {
        $today = now();

        // Inicializar empresas
        $this->companies = Company::all()->pluck('name', 'id')->toArray();
        $this->selectedCompany = Auth::user()->company_id ?? array_key_first($this->companies);

        // Inicializar años y meses
        $this->years = range(2020, $today->year);
        $this->months = range(1, 12);

        // Establecer valores iniciales
        $this->selectedYear = $today->year;
        $this->selectedMonth = $today->month;

        // Actualizar semanas y seleccionar la semana actual
        $this->updateWeeks();
        $this->selectedWeek = $this->getCurrentWeekOfMonth($today);

        // Cargar ítems iniciales
        $this->loadItems();
    }

    // Reactividad al cambiar empresa
    public function updatedSelectedCompany($value)
    {
        $this->adjustWeeksAndReload();
    }

    // Reactividad al cambiar año
    public function updatedSelectedYear($value)
    {
        $this->adjustWeeksAndReload();
    }

    // Reactividad al cambiar mes
    public function updatedSelectedMonth($value)
    {
        $this->adjustWeeksAndReload();
    }

    // Reactividad al cambiar semana
    public function updatedSelectedWeek($value)
    {
        $this->loadItems();
    }

    private function adjustWeeksAndReload()
    {
        $this->updateWeeks();

        // Seleccionar la semana actual si coincide con el mes/año actual
        $today = now();
        if ($this->selectedYear == $today->year && $this->selectedMonth == $today->month) {
            $this->selectedWeek = $this->getCurrentWeekOfMonth($today);
        } else {
            $this->selectedWeek = array_key_first($this->weeks) ?? 1;
        }

        $this->loadItems();
    }

    public function updateWeeks()
    {
        $this->weeks = [];

        $firstDay = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfDay();
        $lastDay = $firstDay->copy()->endOfMonth()->endOfDay();

        // Find the first Monday on or after the first day of the month
        if ($firstDay->dayOfWeek === Carbon::MONDAY) {
            $currentStart = $firstDay->copy();
        } else {
            $currentStart = $firstDay->copy()->next(Carbon::MONDAY);
        }

        $weekNumber = 1;

        while ($currentStart->lte($lastDay)) {
            $weekEnd = $currentStart->copy()->endOfWeek(Carbon::SUNDAY);
            $start = $currentStart->copy();
            $end = $weekEnd->copy();

            $this->weeks[$weekNumber] = ['start' => $start, 'end' => $end];
            $weekNumber++;

            $currentStart = $weekEnd->copy()->addDay()->startOfDay();
        }
    }

    public function getCurrentWeekOfMonth(Carbon $date)
    {
        foreach ($this->weeks as $num => $range) {
            if ($date->between($range['start'], $range['end'])) {
                return $num;
            }
        }
        return array_key_first($this->weeks) ?? 1;
    }

    public function loadItems()
    {
        if (!$this->selectedCompany || !isset($this->weeks[$this->selectedWeek])) {
            $this->items = collect();
            $this->expenses = collect();
            $this->totalCaja = 0;
            $this->totalCompras = 0;
            $this->totalGastos = 0;
            $this->totalVentas = 0;
            $this->sobrante = 0;
            return;
        }

        $companyId = $this->selectedCompany;
        $start = $this->weeks[$this->selectedWeek]['start'];
        $end = $this->weeks[$this->selectedWeek]['end'];

        $this->items = BuyItem::whereHas('buy', function ($q) use ($companyId, $start, $end) {
            $q->where('company_id', $companyId)->whereDate('fecha', '>=', $start->toDateString())->whereDate('fecha', '<=', $end->toDateString());
        })->get();

        $this->items = $this->sanitizeCollection($this->items);

        $this->expenses = Expense::where('company_id', $companyId)->whereDate('fecha', '>=', $start->toDateString())->whereDate('fecha', '<=', $end->toDateString())->get();

        $this->expenses = $this->sanitizeCollection($this->expenses);

        $cash = Cash::where('company_id', $companyId)->whereDate('fecha', '>=', $start->toDateString())->whereDate('fecha', '<=', $end->toDateString())->sum('monto');

        $sales = Sale::where('company_id', $companyId)->whereDate('fecha', '>=', $start->toDateString())->whereDate('fecha', '<=', $end->toDateString())->sum('total');

        $this->totalCaja = $cash ?: 0;
        $this->totalCompras = $this->items->sum('total');
        $this->totalGastos = $this->expenses->sum('monto');
        $this->totalVentas = $sales ?: 0;
        $this->sobrante = $this->totalCaja + $this->totalVentas - $this->totalCompras - $this->totalGastos;

        Log::info('Loaded items', ['count' => $this->items->count(), 'materials' => $this->items->pluck('material')->toArray()]);
        Log::info('Loaded expenses', ['count' => $this->expenses->count(), 'descriptions' => $this->expenses->pluck('descripcion')->toArray()]);
        Log::info('Loaded cash', ['totalCaja' => $this->totalCaja]);
        Log::info('Loaded sales', ['totalVentas' => $this->totalVentas]);
        Log::info('Financial summary', [
            'totalCaja' => $this->totalCaja,
            'totalCompras' => $this->totalCompras,
            'totalGastos' => $this->totalGastos,
            'totalVentas' => $this->totalVentas,
            'sobrante' => $this->sobrante,
        ]);
    }

    private function getDataForWeek($week)
    {
        $companyId = $this->selectedCompany;

        if (!isset($this->weeks[$week]) || !$companyId) {
            return [
                'items' => collect(),
                'expenses' => collect(),
                'sales' => collect(),
                'totalCaja' => 0,
                'totalCompras' => 0,
                'totalGastos' => 0,
                'totalVentas' => 0,
                'sobrante' => 0,
            ];
        }

        $start = $this->weeks[$week]['start'];
        $end = $this->weeks[$week]['end'];

        $items = BuyItem::whereHas('buy', function ($q) use ($companyId, $start, $end) {
            $q->where('company_id', $companyId)->whereDate('fecha', '>=', $start->toDateString())->whereDate('fecha', '<=', $end->toDateString());
        })->get();

        $items = $this->sanitizeCollection($items);

        $expenses = Expense::where('company_id', $companyId)->whereDate('fecha', '>=', $start->toDateString())->whereDate('fecha', '<=', $end->toDateString())->get();

        $expenses = $this->sanitizeCollection($expenses);

        $cash = Cash::where('company_id', $companyId)->whereDate('fecha', '>=', $start->toDateString())->whereDate('fecha', '<=', $end->toDateString())->sum('monto');

        $salesQuery = Sale::where('company_id', $companyId)->whereDate('fecha', '>=', $start->toDateString())->whereDate('fecha', '<=', $end->toDateString());

        $totalVentas = $salesQuery->sum('total');

        $sales = $salesQuery->get();

        $sales = $this->sanitizeCollection($sales);

        $totalCaja = $cash ?: 0;
        $totalCompras = $items->sum('total');
        $totalGastos = $expenses->sum('monto');
        $sobrante = $totalCaja + $totalVentas - $totalCompras - $totalGastos;

        return [
            'items' => $items,
            'expenses' => $expenses,
            'sales' => $sales,
            'totalCaja' => $totalCaja,
            'totalCompras' => $totalCompras,
            'totalGastos' => $totalGastos,
            'totalVentas' => $totalVentas,
            'sobrante' => $sobrante,
        ];
    }

    private function populateSheet($sheet, $data, $materials, $materialsMergeEndCol, $companyName)
    {
        $groupedBuys = $data['items']->groupBy('material');
        $maxRowsBuys = $groupedBuys->max(fn($group) => $group->count()) ?? 0;

        $groupedSales = $data['sales']->groupBy('material');
        $maxRowsSales = $groupedSales->max(fn($group) => $group->count()) ?? 0;

        // Define border style
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];

        // Define header style for totals
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4500'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
            ],
        ];

        // Define style for sold items (yellow background)
        $soldStyle = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFF00'],
            ],
        ];

        // Company Name (spans all columns used by Compras and Ventas tables)
        $sheet->setCellValue('A1', $companyName);
        $sheet->mergeCells("A1:{$materialsMergeEndCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Section 1: Gastos (left side, A-C, shifted down)
        $sheet->setCellValue('A2', $this->sanitizeForJson('Gastos'));
        $sheet->mergeCells('A2:C2');
        $sheet->getStyle('A2:C2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:C2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $expenseHeaderRow = 3;
        $sheet->setCellValue("A{$expenseHeaderRow}", $this->sanitizeForJson('Fecha'));
        $sheet->setCellValue("B{$expenseHeaderRow}", $this->sanitizeForJson('Descripción'));
        $sheet->setCellValue("C{$expenseHeaderRow}", $this->sanitizeForJson('Monto'));
        $sheet->getStyle("A{$expenseHeaderRow}:C{$expenseHeaderRow}")->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
        $sheet->getStyle("A{$expenseHeaderRow}:C{$expenseHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4500');
        $sheet->getStyle("A{$expenseHeaderRow}:C{$expenseHeaderRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $expenseDataRow = $expenseHeaderRow + 1;
        if ($data['expenses']->isNotEmpty()) {
            foreach ($data['expenses'] as $expense) {
                $sheet->setCellValue("A{$expenseDataRow}", $this->sanitizeForJson($expense->fecha));
                $sheet->setCellValue("B{$expenseDataRow}", $this->sanitizeForJson($expense->descripcion));
                $sheet->setCellValue("C{$expenseDataRow}", (float) $expense->monto);
                $sheet->getStyle("C{$expenseDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("C{$expenseDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $expenseDataRow++;
            }
        } else {
            $sheet->setCellValue("A{$expenseDataRow}", $this->sanitizeForJson('No hay gastos registrados'));
            $sheet->mergeCells("A{$expenseDataRow}:C{$expenseDataRow}");
            $sheet->getStyle("A{$expenseDataRow}:C{$expenseDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $expenseDataRow++;
        }
        $expensesEndRow = $expenseDataRow - 1;

        // Apply borders to Gastos table
        $sheet->getStyle("A{$expenseHeaderRow}:C{$expensesEndRow}")->applyFromArray($borderStyle);

        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(12);

        // Section 2: Resumen Financiero (right side, E-F, shifted down)
        $sheet->setCellValue('E2', $this->sanitizeForJson('Resumen Financiero'));
        $sheet->mergeCells('E2:F2');
        $sheet->getStyle('E2:F2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('E2:F2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $summaryHeaderRow = 3;
        $sheet->setCellValue("E{$summaryHeaderRow}", $this->sanitizeForJson('Concepto'));
        $sheet->setCellValue("F{$summaryHeaderRow}", $this->sanitizeForJson('Monto'));
        $sheet->getStyle("E{$summaryHeaderRow}:F{$summaryHeaderRow}")->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
        $sheet->getStyle("E{$summaryHeaderRow}:F{$summaryHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4500');
        $sheet->getStyle("E{$summaryHeaderRow}:F{$summaryHeaderRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $summaryDataRow = $summaryHeaderRow + 1;
        $financialData = [
            ['Total Caja', $data['totalCaja']],
            ['Total Compras', $data['totalCompras']],
            ['Total Gastos', $data['totalGastos']],
            ['Total Ventas', $data['totalVentas']],
            ['Sobrante', $data['sobrante']],
        ];
        foreach ($financialData as $finData) {
            $sheet->setCellValue("E{$summaryDataRow}", $this->sanitizeForJson($finData[0]));
            $sheet->setCellValue("F{$summaryDataRow}", $finData[1]);
            $sheet->getStyle("F{$summaryDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("F{$summaryDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $summaryDataRow++;
        }
        $summaryEndRow = $summaryDataRow - 1;

        // Apply borders to Resumen Financiero table
        $sheet->getStyle("E{$summaryHeaderRow}:F{$summaryEndRow}")->applyFromArray($borderStyle);

        $sheet->getStyle("E4:F{$summaryEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(12);

        // Start Materials below the max of expenses and summary + 2 blank rows
        $maxUpperRow = max($expensesEndRow, $summaryEndRow);
        $materialsStartRow = $maxUpperRow + 3;

        // Section 3: Compras (Materials, full width)
        $sheet->setCellValue("A{$materialsStartRow}", $this->sanitizeForJson('Compras'));
        $materialsTitleRow = $materialsStartRow;
        $sheet->mergeCells("A{$materialsTitleRow}:{$materialsMergeEndCol}{$materialsTitleRow}");
        $sheet->getStyle("A{$materialsTitleRow}:{$materialsMergeEndCol}{$materialsTitleRow}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$materialsTitleRow}:{$materialsMergeEndCol}{$materialsTitleRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $materialsHeaderRow = $materialsTitleRow + 1;
        $col = 1;
        foreach ($materials as $material) {
            $startCol = Coordinate::stringFromColumnIndex($col);
            $endCol = Coordinate::stringFromColumnIndex($col + 2);
            $sheet->setCellValue("{$startCol}{$materialsHeaderRow}", $this->sanitizeForJson($material));
            $sheet->mergeCells("{$startCol}{$materialsHeaderRow}:{$endCol}{$materialsHeaderRow}");
            $col += 3;
        }
        $sheet->getStyle("A{$materialsHeaderRow}:{$materialsMergeEndCol}{$materialsHeaderRow}")->applyFromArray($headerStyle);

        $materialsSubHeaderRow = $materialsHeaderRow + 1;
        $col = 1;
        foreach ($materials as $material) {
            $kgsCol = Coordinate::stringFromColumnIndex($col);
            $precioKgCol = Coordinate::stringFromColumnIndex($col + 1);
            $totalCol = Coordinate::stringFromColumnIndex($col + 2);

            $sheet->setCellValue("{$kgsCol}{$materialsSubHeaderRow}", $this->sanitizeForJson('Kgs'));
            $sheet->setCellValue("{$precioKgCol}{$materialsSubHeaderRow}", $this->sanitizeForJson('Precio/Kg'));
            $sheet->setCellValue("{$totalCol}{$materialsSubHeaderRow}", $this->sanitizeForJson('Total'));

            // Apply colors to sub-header cells
            $sheet->getStyle("{$kgsCol}{$materialsSubHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF');
            $sheet->getStyle("{$precioKgCol}{$materialsSubHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9D9D9');
            $sheet->getStyle("{$totalCol}{$materialsSubHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('BFBFBF');

            $sheet->getStyle("{$kgsCol}{$materialsSubHeaderRow}:{$totalCol}{$materialsSubHeaderRow}")->getFont()->setBold(true);
            $sheet->getStyle("{$kgsCol}{$materialsSubHeaderRow}:{$totalCol}{$materialsSubHeaderRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $col += 3;
        }

        $materialsDataRow = $materialsSubHeaderRow + 1;
        $materialsDataStartRow = $materialsDataRow;
        for ($i = 0; $i < $maxRowsBuys; $i++) {
            $col = 1;
            foreach ($materials as $material) {
                $kgsCol = Coordinate::stringFromColumnIndex($col);
                $precioKgCol = Coordinate::stringFromColumnIndex($col + 1);
                $totalCol = Coordinate::stringFromColumnIndex($col + 2);

                $item = $groupedBuys[$material][$i] ?? null;
                $sheet->setCellValue("{$kgsCol}{$materialsDataRow}", $this->sanitizeForJson($item?->kgs ? $item->kgs : '-'));
                $sheet->getStyle("{$kgsCol}{$materialsDataRow}")->getNumberFormat()->setFormatCode('#,##0.000');
                $sheet->getStyle("{$kgsCol}{$materialsDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("{$kgsCol}{$materialsDataRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF');

                $sheet->setCellValue("{$precioKgCol}{$materialsDataRow}", $this->sanitizeForJson($item?->precio_kg ? $item->precio_kg : '-'));
                $sheet->getStyle("{$precioKgCol}{$materialsDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("{$precioKgCol}{$materialsDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("{$precioKgCol}{$materialsDataRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9D9D9');

                $sheet->setCellValue("{$totalCol}{$materialsDataRow}", $this->sanitizeForJson($item?->total ? $item->total : '-'));
                $sheet->getStyle("{$totalCol}{$materialsDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("{$totalCol}{$materialsDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("{$totalCol}{$materialsDataRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('BFBFBF');

                // Highlight cells in yellow if the BuyItem is fully sold
                if ($item && $item->availableKgs() == 0) {
                    $sheet->getStyle("{$kgsCol}{$materialsDataRow}")->applyFromArray($soldStyle);
                    $sheet->getStyle("{$precioKgCol}{$materialsDataRow}")->applyFromArray($soldStyle);
                    $sheet->getStyle("{$totalCol}{$materialsDataRow}")->applyFromArray($soldStyle);
                }

                $col += 3;
            }
            $materialsDataRow++;
        }
        $materialsDataEndRow = $materialsDataRow - 1;

        // Grand totals row for Compras
        $materialsGrandTotalRow = $materialsDataRow;
        $col = 1;
        foreach ($materials as $index => $material) {
            $kgsCol = Coordinate::stringFromColumnIndex($col);
            $precioKgCol = Coordinate::stringFromColumnIndex($col + 1);
            $totalCol = Coordinate::stringFromColumnIndex($col + 2);

            $materialItems = $groupedBuys[$material] ?? collect();
            $totalKgs = $this->sanitizeForJson(number_format($materialItems->sum('kgs'), 2));
            $totalAmount = $this->sanitizeForJson(number_format($materialItems->sum('total'), 2));

            // Use Excel formula for simple average precio_kg
            $precioKgRange = "{$precioKgCol}{$materialsDataStartRow}:{$precioKgCol}{$materialsDataEndRow}";
            $avgFormula = "=IF(COUNT({$precioKgRange})=0,\"-\",AVERAGE({$precioKgRange}))";

            $sheet->setCellValue("{$kgsCol}{$materialsGrandTotalRow}", $totalKgs);
            $sheet->setCellValue("{$precioKgCol}{$materialsGrandTotalRow}", $avgFormula);
            $sheet->setCellValue("{$totalCol}{$materialsGrandTotalRow}", $totalAmount);

            $sheet->getStyle("{$kgsCol}{$materialsGrandTotalRow}:{$totalCol}{$materialsGrandTotalRow}")->applyFromArray($headerStyle);
            $sheet->getStyle("{$kgsCol}{$materialsGrandTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$precioKgCol}{$materialsGrandTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$totalCol}{$materialsGrandTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');

            $col += 3;

            // If this is FIERRO (first material), place its data in columns A to C
            if ($index === 0) {
                $sheet->setCellValue("A{$materialsGrandTotalRow}", $totalKgs);
                $sheet->setCellValue("B{$materialsGrandTotalRow}", $avgFormula);
                $sheet->setCellValue("C{$materialsGrandTotalRow}", $totalAmount);

                $sheet->getStyle("A{$materialsGrandTotalRow}:C{$materialsGrandTotalRow}")->applyFromArray($headerStyle);
                $sheet->getStyle("A{$materialsGrandTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("B{$materialsGrandTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("C{$materialsGrandTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        // Apply right alignment to the entire grand totals row for Compras
        $sheet->getStyle("A{$materialsGrandTotalRow}:{$materialsMergeEndCol}{$materialsGrandTotalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Apply borders to Compras table (including grand totals)
        $sheet->getStyle("A{$materialsHeaderRow}:{$materialsMergeEndCol}{$materialsGrandTotalRow}")->applyFromArray($borderStyle);

        // Section 4: Ventas (Sales, full width, below Compras)
        $salesStartRow = $materialsGrandTotalRow + 2;
        $sheet->setCellValue("A{$salesStartRow}", $this->sanitizeForJson('Ventas'));
        $salesTitleRow = $salesStartRow;
        $sheet->mergeCells("A{$salesTitleRow}:{$materialsMergeEndCol}{$salesTitleRow}");
        $sheet->getStyle("A{$salesTitleRow}:{$materialsMergeEndCol}{$salesTitleRow}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$salesTitleRow}:{$materialsMergeEndCol}{$salesTitleRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $salesHeaderRow = $salesTitleRow + 1;
        $col = 1;
        foreach ($materials as $material) {
            $startCol = Coordinate::stringFromColumnIndex($col);
            $endCol = Coordinate::stringFromColumnIndex($col + 2);
            $sheet->setCellValue("{$startCol}{$salesHeaderRow}", $this->sanitizeForJson($material));
            $sheet->mergeCells("{$startCol}{$salesHeaderRow}:{$endCol}{$salesHeaderRow}");
            $col += 3;
        }
        $sheet->getStyle("A{$salesHeaderRow}:{$materialsMergeEndCol}{$salesHeaderRow}")->applyFromArray($headerStyle);

        $salesSubHeaderRow = $salesHeaderRow + 1;
        $col = 1;
        foreach ($materials as $material) {
            $kgsCol = Coordinate::stringFromColumnIndex($col);
            $precioKgCol = Coordinate::stringFromColumnIndex($col + 1);
            $totalCol = Coordinate::stringFromColumnIndex($col + 2);

            $sheet->setCellValue("{$kgsCol}{$salesSubHeaderRow}", $this->sanitizeForJson('Kgs'));
            $sheet->setCellValue("{$precioKgCol}{$salesSubHeaderRow}", $this->sanitizeForJson('Precio/Kg'));
            $sheet->setCellValue("{$totalCol}{$salesSubHeaderRow}", $this->sanitizeForJson('Total'));

            // Apply colors to sub-header cells
            $sheet->getStyle("{$kgsCol}{$salesSubHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF');
            $sheet->getStyle("{$precioKgCol}{$salesSubHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9D9D9');
            $sheet->getStyle("{$totalCol}{$salesSubHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('BFBFBF');

            $sheet->getStyle("{$kgsCol}{$salesSubHeaderRow}:{$totalCol}{$salesSubHeaderRow}")->getFont()->setBold(true);
            $sheet->getStyle("{$kgsCol}{$salesSubHeaderRow}:{$totalCol}{$salesSubHeaderRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $col += 3;
        }

        $salesDataRow = $salesSubHeaderRow + 1;
        $salesDataStartRow = $salesDataRow;
        for ($i = 0; $i < $maxRowsSales; $i++) {
            $col = 1;
            foreach ($materials as $material) {
                $kgsCol = Coordinate::stringFromColumnIndex($col);
                $precioKgCol = Coordinate::stringFromColumnIndex($col + 1);
                $totalCol = Coordinate::stringFromColumnIndex($col + 2);

                $sale = $groupedSales[$material][$i] ?? null;
                $sheet->setCellValue("{$kgsCol}{$salesDataRow}", $this->sanitizeForJson($sale?->kgs ? $sale->kgs : '-'));
                $sheet->getStyle("{$kgsCol}{$salesDataRow}")->getNumberFormat()->setFormatCode('#,##0.000');
                $sheet->getStyle("{$kgsCol}{$salesDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("{$kgsCol}{$salesDataRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF');

                $sheet->setCellValue("{$precioKgCol}{$salesDataRow}", $this->sanitizeForJson($sale?->precio_kg ? $sale->precio_kg : '-'));
                $sheet->getStyle("{$precioKgCol}{$salesDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("{$precioKgCol}{$salesDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("{$precioKgCol}{$salesDataRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9D9D9');

                $sheet->setCellValue("{$totalCol}{$salesDataRow}", $this->sanitizeForJson($sale?->total ? $sale->total : '-'));
                $sheet->getStyle("{$totalCol}{$salesDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("{$totalCol}{$salesDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("{$totalCol}{$salesDataRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('BFBFBF');

                $col += 3;
            }
            $salesDataRow++;
        }
        $salesDataEndRow = $salesDataRow - 1;

        // Grand totals row for Ventas
        $salesGrandTotalRow = $salesDataRow;
        $col = 1;
        foreach ($materials as $index => $material) {
            $kgsCol = Coordinate::stringFromColumnIndex($col);
            $precioKgCol = Coordinate::stringFromColumnIndex($col + 1);
            $totalCol = Coordinate::stringFromColumnIndex($col + 2);

            $saleItems = $groupedSales[$material] ?? collect();
            $totalKgs = $this->sanitizeForJson(number_format($saleItems->sum('kgs'), 2));
            $totalAmount = $this->sanitizeForJson(number_format($saleItems->sum('total'), 2));

            // Use Excel formula for simple average precio_kg
            $precioKgRange = "{$precioKgCol}{$salesDataStartRow}:{$precioKgCol}{$salesDataEndRow}";
            $avgFormula = "=IF(COUNT({$precioKgRange})=0,\"-\",AVERAGE({$precioKgRange}))";

            $sheet->setCellValue("{$kgsCol}{$salesGrandTotalRow}", $totalKgs);
            $sheet->setCellValue("{$precioKgCol}{$salesGrandTotalRow}", $avgFormula);
            $sheet->setCellValue("{$totalCol}{$salesGrandTotalRow}", $totalAmount);

            $sheet->getStyle("{$kgsCol}{$salesGrandTotalRow}:{$totalCol}{$salesGrandTotalRow}")->applyFromArray($headerStyle);
            $sheet->getStyle("{$kgsCol}{$salesGrandTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$precioKgCol}{$salesGrandTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$totalCol}{$salesGrandTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');

            $col += 3;

            // If this is FIERRO (first material), place its data in columns A to C
            if ($index === 0) {
                $sheet->setCellValue("A{$salesGrandTotalRow}", $totalKgs);
                $sheet->setCellValue("B{$salesGrandTotalRow}", $avgFormula);
                $sheet->setCellValue("C{$salesGrandTotalRow}", $totalAmount);

                $sheet->getStyle("A{$salesGrandTotalRow}:C{$salesGrandTotalRow}")->applyFromArray($headerStyle);
                $sheet->getStyle("A{$salesGrandTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("B{$salesGrandTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("C{$salesGrandTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        // Apply right alignment to the entire grand totals row for Ventas
        $sheet->getStyle("A{$salesGrandTotalRow}:{$materialsMergeEndCol}{$salesGrandTotalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Apply borders to Ventas table (including grand totals)
        $sheet->getStyle("A{$salesHeaderRow}:{$materialsMergeEndCol}{$salesGrandTotalRow}")->applyFromArray($borderStyle);

        // Adjust column widths for materials
        $col = 1;
        foreach ($materials as $material) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(10);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col + 1))->setWidth(12);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col + 2))->setWidth(12);
            $col += 3;
        }
    }

    public function exportToExcel()
    {
        try {
            $materials = ['FIERRO', 'LAMINA', 'COBRE', 'BRONCE', 'ALUMINIO', 'BOTE', 'ARCHIVO', 'CARTON', 'PLASTICO', 'PET', 'BATERIAS', 'VIDRIO'];
            $materials = array_map([$this, 'sanitizeForJson'], $materials);
            $materialsMergeEndCol = Coordinate::stringFromColumnIndex(count($materials) * 3);

            $companyName = $this->sanitizeForJson(Company::find($this->selectedCompany)->name ?? 'Reporte Multimetal');

            $spreadsheet = new Spreadsheet();

            $weekNumbers = array_keys($this->weeks);
            $hasData = false;

            foreach ($weekNumbers as $index => $week) {
                $data = $this->getDataForWeek($week);

                $maxRowsBuys = $data['items']->groupBy('material')->max(fn($group) => $group->count()) ?? 0;
                $maxRowsSales = $data['sales']->groupBy('material')->max(fn($group) => $group->count()) ?? 0;

                if ($maxRowsBuys > 0 || !$data['items']->isEmpty() || !$data['expenses']->isEmpty() || $maxRowsSales > 0 || !$data['sales']->isEmpty()) {
                    $hasData = true;
                }

                if ($index > 0) {
                    $sheet = $spreadsheet->createSheet();
                } else {
                    $sheet = $spreadsheet->getSheet(0);
                }

                // Set worksheet name
                $startDate = $this->weeks[$week]['start']->locale('es')->isoFormat('DD MMM YYYY');
                $endDate = $this->weeks[$week]['end']->locale('es')->isoFormat('DD MMM YYYY');
                $sheetName = $this->sanitizeForJson("Semana {$week} ({$startDate} - {$endDate})");
                $sheet->setTitle(substr($sheetName, 0, 31));

                $this->populateSheet($sheet, $data, $materials, $materialsMergeEndCol, $companyName);
            }

            if (!$hasData) {
                $this->addError('export', 'No hay datos para exportar en ninguna semana del mes.');
                return null;
            }

            $writer = new Xlsx($spreadsheet);
            $filename = $this->sanitizeForJson('reporte_' . $this->selectedYear . '_' . $this->selectedMonth . '.xlsx');

            return response()->streamDownload(
                function () use ($writer) {
                    $writer->save('php://output');
                },
                $filename,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=UTF-8',
                    'Content-Disposition' => "attachment; filename=\"$filename\"",
                    'Cache-Control' => 'max-age=0',
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error exporting to Excel: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            $this->addError('export', 'Error al generar el archivo XLSX. Verifique la codificación de datos o la base de datos.');
            return null;
        }
    }
}; ?>

<div class="p-6">
    <h1 class="text-xl font-bold mb-6">Reporte financiero</h1>

    @if ($errors->has('export'))
        <div class="text-red-500 mb-4">
            {{ $errors->first('export') }}
        </div>
    @endif

    <div class="mb-4 flex gap-4 items-end">
        <div>
            <label for="company-select" class="block text-sm font-medium">Empresa:</label>
            <select wire:model.live="selectedCompany" id="company-select" class="border px-2 py-1 rounded">
                @foreach ($companies as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="year-select" class="block text-sm font-medium">Año:</label>
            <select wire:model.live="selectedYear" id="year-select" class="border px-2 py-1 rounded">
                @foreach ($years as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="month-select" class="block text-sm font-medium">Mes:</label>
            <select wire:model.live="selectedMonth" id="month-select" class="border px-2 py-1 rounded">
                @foreach ($months as $month)
                    <option value="{{ $month }}">
                        {{ \Carbon\Carbon::create()->month($month)->locale('es')->isoFormat('MMMM') }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="week-select" class="block text-sm font-medium">Semana:</label>
            <select wire:model.live="selectedWeek" id="week-select" class="border px-2 py-1 rounded">
                @foreach ($weeks as $num => $range)
                    <option value="{{ $num }}">
                        Semana {{ $num }} ({{ $range['start']->locale('es')->isoFormat('DD MMM YYYY') }} -
                        {{ $range['end']->locale('es')->isoFormat('DD MMM YYYY') }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="relative">
            <button wire:click="exportToExcel" class="bg-green-500 text-white p-2 rounded-sm cursor-pointer relative" wire:loading.class="opacity-50 cursor-not-allowed">
                <span wire:loading.remove>Descargar XLSX</span>
                <span wire:loading wire:target="exportToExcel" class="flex items-center">
                    <svg class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Generando...
                </span>
            </button>
        </div>
    </div>

    <h1 class="text-xl font-bold mb-6">Compras registradas</h1>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-700 text-sm text-gray-300">
            <thead class="bg-gray-800 text-gray-200">
                <tr>
                    @php
                        $materials = [
                            'FIERRO',
                            'LAMINA',
                            'COBRE',
                            'BRONCE',
                            'ALUMINIO',
                            'BOTE',
                            'ARCHIVO',
                            'CARTON',
                            'PLASTICO',
                            'PET',
                            'BATERIAS',
                            'VIDRIO',
                        ];
                        $materials = array_map(fn($m) => $this->sanitizeForJson($m), $materials);
                        $grouped = $items->groupBy('material');
                        $maxRows = $grouped->max(fn($group) => $group->count()) ?? 0;
                    @endphp
                    @foreach ($materials as $m)
                        <th colspan="3" class="px-2 py-2 border text-center bg-gray-700">{{ $m }}</th>
                    @endforeach
                </tr>
                <tr class="bg-gray-900 text-gray-400">
                    @foreach ($materials as $m)
                        <th class="px-2 py-1 border">Kgs</th>
                        <th class="px-2 py-1 border">Precio/Kg</th>
                        <th class="px-2 py-1 border">Total</th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @for ($i = 0; $i < $maxRows; $i++)
                    <tr class="hover:bg-gray-800">
                        @foreach ($materials as $m)
                            @php $item = $grouped[$m][$i] ?? null; @endphp
                            <td class="px-2 py-1 border text-right">{{ $this->sanitizeForJson($item?->kgs ? number_format($item->kgs, 3) : '-') }}</td>
                            <td class="px-2 py-1 border text-right">{{ $this->sanitizeForJson($item?->precio_kg ? number_format($item->precio_kg, 2) : '-') }}</td>
                            <td class="px-2 py-1 border text-right">{{ $this->sanitizeForJson($item?->total ? number_format($item->total, 2) : '-') }}</td>
                        @endforeach
                    </tr>
                @endfor
            </tbody>
            <tfoot class="bg-gray-800 text-gray-200 font-bold">
                <tr>
                    @foreach ($materials as $m)
                        @php
                            $materialItems = $grouped[$m] ?? collect();
                            $totalKgs = $materialItems->sum('kgs');
                            $totalAmount = $materialItems->sum('total');
                        @endphp
                        <td class="px-2 py-1 border text-right">{{ $this->sanitizeForJson(number_format($totalKgs, 3)) }}</td>
                        <td class="px-2 py-1 border text-center">{{ $this->sanitizeForJson('-') }}</td>
                        <td class="px-2 py-1 border text-right">{{ $this->sanitizeForJson(number_format($totalAmount, 2)) }}</td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    </div>

    <h2 class="text-lg font-semibold mb-4 mt-6">Resumen Financiero y Gastos</h2>
    <div class="flex gap-4 mb-6">
        <div class="overflow-x-auto flex-1 min-w-0">
            <table class="w-full border border-gray-700 text-sm text-gray-300">
                <thead class="bg-gray-800 text-gray-200">
                    <tr>
                        <th class="px-2 py-2 border text-center">Fecha</th>
                        <th class="px-2 py-2 border text-center">Descripción</th>
                        <th class="px-2 py-2 border text-center">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr class="hover:bg-gray-800">
                            <td class="px-2 py-1 border text-right">{{ $expense->fecha }}</td>
                            <td class="px-2 py-1 border text-left">{{ $expense->descripcion }}</td>
                            <td class="px-2 py-1 border text-right">{{ number_format($expense->monto, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-2 py-1 border text-center">No hay gastos registrados para este período.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-800 text-gray-200 font-bold">
                    <tr>
                        <td class="px-2 py-1 border text-center" colspan="2">Total</td>
                        <td class="px-2 py-1 border text-right">{{ $this->sanitizeForJson(number_format($expenses->sum('monto'), 2)) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="overflow-x-auto flex-1 min-w-0">
            <table class="w-full border border-gray-700 text-sm text-gray-300">
                <thead class="bg-gray-800 text-gray-200">
                    <tr>
                        <th class="px-2 py-2 border text-center">Concepto</th>
                        <th class="px-2 py-2 border text-center">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="hover:bg-gray-800">
                        <td class="px-2 py-1 border text-left">Total Caja</td>
                        <td class="px-2 py-1 border text-right">{{ number_format($totalCaja, 2) }}</td>
                    </tr>
                    <tr class="hover:bg-gray-800">
                        <td class="px-2 py-1 border text-left">Total Compras</td>
                        <td class="px-2 py-1 border text-right">{{ number_format($totalCompras, 2) }}</td>
                    </tr>
                    <tr class="hover:bg-gray-800">
                        <td class="px-2 py-1 border text-left">Total Gastos</td>
                        <td class="px-2 py-1 border text-right">{{ number_format($totalGastos, 2) }}</td>
                    </tr>
                    <tr class="hover:bg-gray-800">
                        <td class="px-2 py-1 border text-left">Total Ventas</td>
                        <td class="px-2 py-1 border text-right">{{ number_format($totalVentas, 2) }}</td>
                    </tr>
                    <tr class="hover:bg-gray-800">
                        <td class="px-2 py-1 border text-left">Sobrante</td>
                        <td class="px-2 py-1 border text-right">{{ number_format($sobrante, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>