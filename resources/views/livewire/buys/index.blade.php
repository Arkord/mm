<?php

use App\Models\BuyItem;
use App\Models\Expense;
use App\Models\Cash;
use App\Models\Sale;
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
    public $years = [];
    public $months = [];
    public $weeks = [];

    private function sanitizeForJson($value)
    {
        if (is_null($value) || $value === '-' || is_numeric($value)) {
            return $value;
        }

        $string = (string) $value;

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($string, 'UTF-8', 'auto');
        } elseif (function_exists('iconv')) {
            return iconv('UTF-8', 'UTF-8//IGNORE', $string);
        }

        return utf8_encode($string);
    }

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

        $this->years = range(2020, $today->year);
        $this->months = range(1, 12);

        $this->selectedYear = $today->year;
        $this->selectedMonth = $today->month;

        $this->updateWeeks();
        $this->selectedWeek = $this->getCurrentWeekOfMonth($today);

        $this->loadItems();
    }

    public function updatedSelectedYear($value)
    {
        $this->adjustWeeksAndReload();
    }

    public function updatedSelectedMonth($value)
    {
        $this->adjustWeeksAndReload();
    }

    public function updatedSelectedWeek($value)
    {
        $this->loadItems();
    }

    private function adjustWeeksAndReload()
    {
        $this->updateWeeks();

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

        if ($firstDay->dayOfWeek === Carbon::MONDAY) {
            $currentStart = $firstDay->copy();
        } else {
            $currentStart = $firstDay->copy()->next(Carbon::MONDAY);
        }

        $weekNumber = 1;

        while ($currentStart->lte($lastDay)) {
            $weekEnd = $currentStart->copy()->endOfWeek(Carbon::SUNDAY);
            $this->weeks[$weekNumber] = [
                'start' => $currentStart->copy(),
                'end' => $weekEnd->copy()
            ];
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
            $q->where('company_id', $companyId)
              ->whereDate('fecha', '>=', $start->toDateString())
              ->whereDate('fecha', '<=', $end->toDateString());
        })->get();

        $this->items = $this->sanitizeCollection($this->items);

        $this->expenses = Expense::where('company_id', $companyId)
            ->whereDate('fecha', '>=', $start->toDateString())
            ->whereDate('fecha', '<=', $end->toDateString())
            ->get();

        $this->expenses = $this->sanitizeCollection($this->expenses);

        $this->totalCaja = Cash::where('company_id', $companyId)
            ->whereDate('fecha', '>=', $start->toDateString())
            ->whereDate('fecha', '<=', $end->toDateString())
            ->sum('monto') ?: 0;

        // ← Cambio principal: SOLO ventas tipo PATIO
        $this->totalVentas = Sale::patio()
            ->where('company_id', $companyId)
            ->whereDate('fecha', '>=', $start->toDateString())
            ->whereDate('fecha', '<=', $end->toDateString())
            ->sum('total') ?: 0;

        $this->totalCompras = $this->items->sum('total');
        $this->totalGastos = $this->expenses->sum('monto');
        // $this->sobrante = $this->totalCaja + $this->totalVentas - $this->totalCompras - $this->totalGastos;
        $this->sobrante = $this->totalCaja - $this->totalCompras - $this->totalGastos;
    }

    private function getDataForWeek($week)
    {
        $companyId = Auth::user()->company_id;

        if (!isset($this->weeks[$week])) {
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
            $q->where('company_id', $companyId)
              ->whereDate('fecha', '>=', $start->toDateString())
              ->whereDate('fecha', '<=', $end->toDateString());
        })->get();

        $items = $this->sanitizeCollection($items);

        $expenses = Expense::where('company_id', $companyId)
            ->whereDate('fecha', '>=', $start->toDateString())
            ->whereDate('fecha', '<=', $end->toDateString())
            ->get();

        $expenses = $this->sanitizeCollection($expenses);

        $totalCaja = Cash::where('company_id', $companyId)
            ->whereDate('fecha', '>=', $start->toDateString())
            ->whereDate('fecha', '<=', $end->toDateString())
            ->sum('monto') ?: 0;

        // ← Cambio principal: SOLO ventas tipo PATIO
        $salesQuery = Sale::patio()
            ->where('company_id', $companyId)
            ->whereDate('fecha', '>=', $start->toDateString())
            ->whereDate('fecha', '<=', $end->toDateString());

        $totalVentas = $salesQuery->sum('total') ?: 0;
        $sales = $salesQuery->get();
        $sales = $this->sanitizeCollection($sales);

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

    // ... (el resto del método populateSheet y exportToExcel se mantiene igual, 
    //      ya que usan los valores calculados en getDataForWeek)

    // ... (el método exportToExcel permanece sin cambios significativos)
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
                        <td class="px-2 py-1 border text-right">${{ number_format($expenses->sum('monto'), 2) }}</td>
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
                        <td class="px-2 py-1 border text-right">${{ number_format($totalCaja, 2) }}</td>
                    </tr>
                    <tr class="hover:bg-gray-800">
                        <td class="px-2 py-1 border text-left">Total Compras</td>
                        <td class="px-2 py-1 border text-right">${{ number_format($totalCompras, 2) }}</td>
                    </tr>
                    <tr class="hover:bg-gray-800">
                        <td class="px-2 py-1 border text-left">Total Gastos</td>
                        <td class="px-2 py-1 border text-right">${{ number_format($totalGastos, 2) }}</td>
                    </tr>
                    <tr class="hover:bg-gray-800">
                        <td class="px-2 py-1 border text-left">Total Ventas (Patio)</td>
                        <td class="px-2 py-1 border text-right">${{ number_format($totalVentas, 2) }}</td>
                    </tr>
                    <tr class="hover:bg-gray-800">
                        <td class="px-2 py-1 border text-left">Sobrante</td>
                        <td class="px-2 py-1 border text-right">${{ number_format($sobrante, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>