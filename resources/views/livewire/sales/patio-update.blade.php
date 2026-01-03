<?php

use App\Models\Sale;
use App\Models\Company;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public $sales = [];
    public $selectedYear;
    public $selectedMonth;
    public $selectedMaterial;
    public $selectedCompanyId;
    public $companies = [];
    public $materials = ['FIERRO', 'LAMINA', 'COBRE', 'BRONCE', 'ALUMINIO', 'BOTE', 'ARCHIVO', 'CARTON', 'PLASTICO', 'PET', 'BATERIAS', 'VIDRIO'];
    public $years = [];
    public $months = [];

    public $editingSaleId = null;
    public $editPrecioKg = 0;

    public function mount()
    {
        $today = now();
        $this->loadCompanies();
        $this->years = range(2020, $today->year);
        $this->months = range(1, 12);
        $this->selectedYear = $today->year;
        $this->selectedMonth = $today->month;
        $this->selectedMaterial = $this->materials[0];
        $this->selectedCompanyId = $this->companies->first()?->id ?? Auth::user()->company_id;
        $this->loadItems();
    }

    private function loadCompanies()
    {
        if (auth()->user()?->isAdmin()) {
            $this->companies = Company::orderBy('name')->get();
        } else {
            $this->companies = Company::where('id', Auth::user()->company_id)->get();
        }
    }

    public function updatedSelectedCompanyId()
    {
        $this->loadItems();
    }
    public function updatedSelectedYear()
    {
        $this->loadItems();
    }
    public function updatedSelectedMonth()
    {
        $this->loadItems();
    }
    public function updatedSelectedMaterial()
    {
        $this->loadItems();
    }

    public function loadItems()
    {
        if (!$this->selectedYear || !$this->selectedMonth || !$this->selectedMaterial || !$this->selectedCompanyId) {
            $this->sales = collect();
            return;
        }

        $start = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $this->sales = Sale::patio()->where('company_id', $this->selectedCompanyId)->whereDate('fecha', '>=', $start)->whereDate('fecha', '<=', $end)->where('material', $this->selectedMaterial)->orderBy('fecha', 'desc')->get();

        $this->sales = $this->sanitizeCollection($this->sales);
    }

    public function startEditPrecio($saleId)
    {
        $sale = $this->sales->find($saleId);
        if ($sale) {
            $this->editingSaleId = $saleId;
            $this->editPrecioKg = $sale->precio_kg;
        }
    }

    public function cancelEditPrecio()
    {
        $this->editingSaleId = null;
        $this->editPrecioKg = 0;
    }

    public function savePrecio()
    {
        $this->validate([
            'editPrecioKg' => 'required|numeric|min:0',
        ]);

        $sale = Sale::find($this->editingSaleId);
        if ($sale && $sale->type === Sale::TYPE_PATIO && $sale->company_id == $this->selectedCompanyId) {
            $nuevoTotal = $sale->kgs * $this->editPrecioKg;
            $sale->update([
                'precio_kg' => $this->editPrecioKg,
                'total' => $nuevoTotal,
            ]);

            session()->flash('success', 'Precio y total actualizados correctamente.');
        }

        $this->cancelEditPrecio();
        $this->loadItems(); // ← ¡RECARGA LA TABLA AL INSTANTE!
    }

    private function sanitizeForJson($value)
    {
        if (is_null($value) || $value === '-' || is_numeric($value)) {
            return $value;
        }
        $string = (string) $value;
        return function_exists('mb_convert_encoding') ? mb_convert_encoding($string, 'UTF-8', 'auto') : (function_exists('iconv') ? iconv('UTF-8', 'UTF-8//IGNORE', $string) : utf8_encode($string));
    }

    private function sanitizeCollection($collection)
    {
        return $collection->map(function ($item) {
            if (isset($item->material)) {
                $item->material = $this->sanitizeForJson($item->material);
            }
            return $item;
        });
    }
}; ?>

<div class="p-6">
    <h1 class="text-xl font-bold mb-6 text-amber-600">Actualizar Precios de Patio</h1>

    <div class="mb-4 flex gap-4 items-end flex-wrap">
        <div>
            <label class="block text-sm font-medium">Empresa:</label>
            <select wire:model.live="selectedCompanyId" class="border px-2 py-1 rounded">
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Año:</label>
            <select wire:model.live="selectedYear" class="border px-2 py-1 rounded">
                @foreach ($years as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Mes:</label>
            <select wire:model.live="selectedMonth" class="border px-2 py-1 rounded">
                @foreach ($months as $month)
                    <option value="{{ $month }}">
                        {{ \Carbon\Carbon::create()->month($month)->locale('es')->isoFormat('MMMM') }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Material:</label>
            <select wire:model.live="selectedMaterial" class="border px-2 py-1 rounded">
                @foreach ($materials as $material)
                    <option value="{{ $material }}">{{ $material }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <h1 class="text-xl font-bold mb-6">Ventas de Patio Registradas</h1>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-700 text-sm text-gray-300">
            <thead class="bg-gray-800 text-gray-200">
                <tr>
                    <th class="px-2 py-2 border text-center">Fecha</th>
                    <th class="px-2 py-2 border text-center">Material</th>
                    <th class="px-2 py-2 border text-center">Kgs</th>
                    <th class="px-2 py-2 border text-center">Precio/Kg</th>
                    <th class="px-2 py-2 border text-center">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr class="hover:bg-gray-800">
                        <td class="px-2 py-1 border text-center">{{ $sale->fecha->format('Y-m-d') }}</td>
                        <td class="px-2 py-1 border text-center">{{ $this->sanitizeForJson($sale->material) }}</td>
                        <td class="px-2 py-1 border text-right font-mono">
                            {{ $this->sanitizeForJson(number_format($sale->kgs, 3)) }}
                        </td>
                        <td class="px-2 py-1 border text-right font-mono">
                            @if ($editingSaleId == $sale->id)
                                <div x-data="{ precio: @entangle('editPrecioKg') }" x-init="initPrecioCleave($refs.precioInput, precio)" wire:ignore
                                    class="flex items-center gap-1">
                                    <input type="text" x-ref="precioInput" placeholder="$0.00"
                                        class="border px-1 py-0.5 rounded text-xs w-24 text-right font-mono">
                                    <button wire:click="savePrecio"
                                        class="text-green-400 text-xs hover:underline cursor-pointer">Guardar</button>
                                    <button wire:click="cancelEditPrecio"
                                        class="text-red-400 text-xs hover:underline cursor-pointer">Cancelar</button>
                                </div>
                            @else
                                ${{ number_format($sale->precio_kg, 2) }}
                                <button wire:click="startEditPrecio({{ $sale->id }})"
                                    class="ml-2 text-amber-400 text-xs hover:underline cursor-pointer">Editar</button>
                            @endif
                        </td>
                        <td class="px-2 py-1 border text-right font-mono font-bold text-green-400">
                            ${{ number_format($sale->kgs * $sale->precio_kg, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-2 py-1 border text-center text-gray-500">
                            No hay ventas de patio registradas en este período.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-800 text-gray-200 font-bold">
                <tr>
                    <td class="px-2 py-1 border text-center" colspan="2">Total</td>
                    <td class="px-2 py-1 border text-right font-mono">
                        {{ $this->sanitizeForJson(number_format($sales->sum('kgs'), 3)) }} kgs
                    </td>
                    <td class="px-2 py-1 border text-center">-</td>
                    <td class="px-2 py-1 border text-right font-mono text-green-400">
                        ${{ number_format($sales->sum(fn($s) => $s->kgs * $s->precio_kg), 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js"></script>
<script>
    function initPrecioCleave(input, initialValue) {
        if (!input) return;

        if (input.cleave) input.cleave.destroy();

        const value = initialValue ?? 0;
        input.value = value > 0 ? '' : '$0.00';

        input.cleave = new Cleave(input, {
            numeral: true,
            numeralThousandsGroupStyle: 'thousand',
            numeralDecimalScale: 2,
            numeralDecimalMark: '.',
            delimiter: ',',
            prefix: '$',
            rawValueTrimPrefix: true,
            numeralPositiveOnly: true,
            onValueChanged: function(e) {
                const raw = e.target.rawValue || '0';
                const num = parseFloat(raw) || 0;
                @this.set('editPrecioKg', num);

                if (num === 0 && (e.target.value === '' || e.target.value === '$')) {
                    setTimeout(() => input.value = '$0.00', 0);
                }
            }
        });

        if (value > 0) {
            input.cleave.setRawValue(value);
        }
    }
</script>
