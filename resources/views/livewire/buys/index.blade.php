<?php

use App\Models\BuyItem;
use App\Models\Expense;
use App\Models\Cash;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
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
    public $years = [];
    public $months = [];
    public $weeks = [];

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

        $currentStart = $firstDay->copy()->startOfWeek(Carbon::MONDAY);
        $weekNumber = 1;

        while ($currentStart <= $lastDay) {
            $weekEnd = $currentStart->copy()->endOfWeek(Carbon::SUNDAY);
            $start = $currentStart->max($firstDay);
            $end = $weekEnd->min($lastDay);

            if ($start <= $end) {
                $this->weeks[$weekNumber] = ['start' => $start, 'end' => $end];
                $weekNumber++;
            }

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
        $companyId = Auth::user()->company_id;

        if (!isset($this->weeks[$this->selectedWeek])) {
            $this->items = collect();
            $this->expenses = collect();
            $this->totalCaja = 0;
            $this->totalCompras = 0;
            $this->totalGastos = 0;
            $this->totalVentas = 0;
            $this->sobrante = 0;
            return;
        }

        $start = $this->weeks[$this->selectedWeek]['start'];
        $end = $this->weeks[$this->selectedWeek]['end'];

        $this->items = BuyItem::whereHas('buy', function ($q) use ($companyId, $start, $end) {
            $q->where('company_id', $companyId)->whereDate('fecha', '>=', $start->toDateString())->whereDate('fecha', '<=', $end->toDateString());
        })->get();

        $this->items = $this->sanitizeCollection($this->items);

        $this->expenses = Expense::where('company_id', $companyId)->whereDate('fecha', '>=', $start->toDateString())->whereDate('fecha', '<=', $end->toDateString())->get();

        $this->expenses = $this->sanitizeCollection($this->expenses);

        $cash = Cash::where('company_id', $companyId)->whereDate('fecha', '>=', $start->toDateString())->whereDate('fecha', '<=', $end->toDateString())->sum('monto');

        $this->totalCaja = $cash ?: 0;
        $this->totalCompras = $this->items->sum('total');
        $this->totalGastos = $this->expenses->sum('monto');
        $this->totalVentas = 0;
        $this->sobrante = $this->totalCaja - $this->totalCompras - $this->totalGastos;

        Log::info('Loaded items', ['count' => $this->items->count(), 'materials' => $this->items->pluck('material')->toArray()]);
        Log::info('Loaded expenses', ['count' => $this->expenses->count(), 'descriptions' => $this->expenses->pluck('descripcion')->toArray()]);
        Log::info('Loaded cash', ['totalCaja' => $this->totalCaja]);
        Log::info('Financial summary', [
            'totalCaja' => $this->totalCaja,
            'totalCompras' => $this->totalCompras,
            'totalGastos' => $this->totalGastos,
            'totalVentas' => $this->totalVentas,
            'sobrante' => $this->sobrante,
        ]);
    }

    public function exportToExcel()
    {
        try {
            $materials = ['FIERRO', 'LAMINA', 'COBRE', 'BRONCE', 'ALUMINIO', 'BOTE', 'ARCHIVO', 'CARTON', 'PLASTICO', 'PET', 'BATERIAS', 'OTRO'];
            $materials = array_map([$this, 'sanitizeForJson'], $materials);
            $grouped = $this->items->groupBy('material');
            $maxRows = $grouped->max(fn($group) => $group->count()) ?? 0;

            if ($maxRows === 0 && $this->items->isEmpty() && $this->expenses->isEmpty()) {
                $this->addError('export', 'No hay datos para exportar.');
                return null;
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Company Name (spans all columns used by Compras table)
            $companyName = $this->sanitizeForJson(Auth::user()->company->name ?? 'Reporte Multimetal');
            $materialsMergeEndCol = Coordinate::stringFromColumnIndex(count($materials) * 3);
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
            if ($this->expenses->isNotEmpty()) {
                foreach ($this->expenses as $expense) {
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
                ['Total Caja', $this->totalCaja],
                ['Total Compras', $this->totalCompras],
                ['Total Gastos', $this->totalGastos],
                ['Total Ventas', $this->totalVentas],
                ['Sobrante', $this->sobrante],
            ];
            foreach ($financialData as $data) {
                $sheet->setCellValue("E{$summaryDataRow}", $this->sanitizeForJson($data[0]));
                $sheet->setCellValue("F{$summaryDataRow}", $data[1]);
                $sheet->getStyle("F{$summaryDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("F{$summaryDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $summaryDataRow++;
            }
            $summaryEndRow = $summaryDataRow - 1;
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
            $sheet->getStyle("A{$materialsHeaderRow}:{$materialsMergeEndCol}{$materialsHeaderRow}")->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
            $sheet->getStyle("A{$materialsHeaderRow}:{$materialsMergeEndCol}{$materialsHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4500');
            $sheet->getStyle("A{$materialsHeaderRow}:{$materialsMergeEndCol}{$materialsHeaderRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $materialsSubHeaderRow = $materialsHeaderRow + 1;
            $col = 1;
            foreach ($materials as $material) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $materialsSubHeaderRow, $this->sanitizeForJson('Kgs'));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . $materialsSubHeaderRow, $this->sanitizeForJson('Precio/Kg'));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 2) . $materialsSubHeaderRow, $this->sanitizeForJson('Total'));
                $col += 3;
            }
            $sheet->getStyle("A{$materialsSubHeaderRow}:{$materialsMergeEndCol}{$materialsSubHeaderRow}")->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
            $sheet->getStyle("A{$materialsSubHeaderRow}:{$materialsMergeEndCol}{$materialsSubHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4500');
            $sheet->getStyle("A{$materialsSubHeaderRow}:{$materialsMergeEndCol}{$materialsSubHeaderRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $materialsDataRow = $materialsSubHeaderRow + 1;
            for ($i = 0; $i < $maxRows; $i++) {
                $col = 1;
                foreach ($materials as $material) {
                    $item = $grouped[$material][$i] ?? null;
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $materialsDataRow, $this->sanitizeForJson($item?->kgs ? number_format($item->kgs, 3) : '-'));
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . $materialsDataRow)->getNumberFormat()->setFormatCode('#,##0.000');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . $materialsDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . $materialsDataRow, $this->sanitizeForJson($item?->precio_kg ? number_format($item->precio_kg, 2) : '-'));
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col + 1) . $materialsDataRow)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col + 1) . $materialsDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 2) . $materialsDataRow, $this->sanitizeForJson($item?->total ? number_format($item->total, 2) : '-'));
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col + 2) . $materialsDataRow)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($col + 2) . $materialsDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $col += 3;
                }
                $materialsDataRow++;
            }

            $materialsTotalRow = $materialsDataRow;
            $col = 1;
            foreach ($materials as $material) {
                $materialItems = $grouped[$material] ?? collect();
                $totalKgs = $this->sanitizeForJson(number_format($materialItems->sum('kgs'), 2));
                $totalAmount = $this->sanitizeForJson(number_format($materialItems->sum('total'), 2));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $materialsTotalRow, $totalKgs);
                $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . $materialsTotalRow)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . $materialsTotalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . $materialsTotalRow, $this->sanitizeForJson('-'));
                $sheet->getStyle(Coordinate::stringFromColumnIndex($col + 1) . $materialsTotalRow)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle(Coordinate::stringFromColumnIndex($col + 1) . $materialsTotalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 2) . $materialsTotalRow, $totalAmount);
                $sheet->getStyle(Coordinate::stringFromColumnIndex($col + 2) . $materialsTotalRow)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle(Coordinate::stringFromColumnIndex($col + 2) . $materialsTotalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $col += 3;
            }
            $sheet->getStyle("A{$materialsTotalRow}:{$materialsMergeEndCol}{$materialsTotalRow}")->getFont()->setBold(true);
            $sheet->getStyle("A{$materialsTotalRow}:{$materialsMergeEndCol}{$materialsTotalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Adjust column widths for materials
            $col = 1;
            foreach ($materials as $material) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(10);
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col + 1))->setWidth(12);
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col + 2))->setWidth(12);
                $col += 3;
            }

            $writer = new Xlsx($spreadsheet);
            $filename = $this->sanitizeForJson('reporte_' . $this->selectedYear . '_' . $this->selectedMonth . '_semana_' . $this->selectedWeek . '.xlsx');

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
    <h1 class="text-xl font-bold mb-6">Criterios</h1>

    @if ($errors->has('export'))
        <div class="text-red-500 mb-4">
            {{ $errors->first('export') }}
        </div>
    @endif

    <div class="mb-4 flex gap-4 items-end">
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

        <a href="{{ route('buys.create') }}" class="bg-amber-100 text-black p-2 rounded-sm">Registrar compra</a>
        <button wire:click="exportToExcel" class="bg-green-500 text-white p-2 rounded-sm cursor-pointer">Descargar XLSX</button>
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
                            'OTRO',
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