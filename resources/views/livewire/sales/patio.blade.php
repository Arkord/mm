<?php

use App\Models\Sale;
use App\Models\BuyItem;
use App\Models\SaleBuyItem;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

new class extends Component {
    public $sales = [];
    public $selectedYear;
    public $selectedMonth;
    public $selectedMaterial;
    public $materials = ['FIERRO', 'LAMINA', 'COBRE', 'BRONCE', 'ALUMINIO', 'BOTE', 'ARCHIVO', 'CARTON', 'PLASTICO', 'PET', 'BATERIAS', 'VIDRIO'];
    public $years = [];
    public $months = [];

    // Crear venta de patio
    public $creatingSale = false;
    public $kgs = 0;
    public $showConfirmModal = false;
    public $fecha;

    public function mount()
    {
        $today = now();

        $this->years = range(2020, $today->year);
        $this->months = range(1, 12);

        $this->selectedYear = $today->year;
        $this->selectedMonth = $today->month;
        $this->selectedMaterial = $this->materials[0];
        $this->fecha = $today->format('Y-m-d');

        $this->loadItems();
    }

    public function updatedSelectedYear() { $this->loadItems(); $this->resetSaleState(); }
    public function updatedSelectedMonth() { $this->loadItems(); $this->resetSaleState(); }
    public function updatedSelectedMaterial() { $this->loadItems(); $this->resetSaleState(); }

    private function resetSaleState()
    {
        $this->kgs = 0;
        $this->fecha = now()->setTimezone('America/Mexico_City')->format('Y-m-d');
    }

    public function loadItems()
    {
        $companyId = Auth::user()->company_id;

        if (!$this->selectedYear || !$this->selectedMonth || !$this->selectedMaterial) {
            $this->sales = collect();
            return;
        }

        $start = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Solo ventas de tipo PATIO
        $this->sales = Sale::patio()
            ->where('company_id', $companyId)
            ->whereDate('fecha', '>=', $start)
            ->whereDate('fecha', '<=', $end)
            ->where('material', $this->selectedMaterial)
            ->get();

        $this->sales = $this->sanitizeCollection($this->sales);
    }

    public function startCreatingSale()
    {
        $this->creatingSale = true;
        $this->kgs = 0;
        $this->fecha = now()->setTimezone('America/Mexico_City')->format('Y-m-d');
    }

    public function cancelCreatingSale()
    {
        $this->creatingSale = false;
        $this->kgs = 0;
        $this->showConfirmModal = false;
    }

    public function updatedKgs($value)
    {
        $this->kgs = (float) str_replace(',', '', $value);
    }

    public function confirmSave()
    {
        $this->showConfirmModal = true;
    }

    public function saveConfirmed()
    {
        $this->saveSale();
        $this->showConfirmModal = false;
    }

    public function saveSale()
    {
        $this->validate([
            'fecha' => 'required|date',
            'kgs' => 'required|numeric|min:0.001',
        ]);

        $companyId = Auth::user()->company_id;

        // Guardamos con precio y total en 0, tipo patio
        Sale::create([
            'fecha'       => $this->fecha,
            'material'    => $this->selectedMaterial,
            'company_id'  => $companyId,
            'user_id'     => Auth::id(),
            'kgs'         => $this->kgs,
            'precio_kg'   => 0,
            'total'       => 0,
            'type'        => Sale::TYPE_PATIO,
        ]);

        session()->flash('success', 'Venta de patio registrada correctamente.');
        $this->loadItems();
        $this->cancelCreatingSale();
    }

    private function sanitizeForJson($value)
    {
        if (is_null($value) || $value === '-' || is_numeric($value)) {
            return $value;
        }
        $string = (string) $value;
        return function_exists('mb_convert_encoding')
            ? mb_convert_encoding($string, 'UTF-8', 'auto')
            : (function_exists('iconv') ? iconv('UTF-8', 'UTF-8//IGNORE', $string) : utf8_encode($string));
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
    <h1 class="text-xl font-bold mb-6">Ventas de Patio</h1>

    <div class="mb-4 flex gap-4 items-end flex-wrap">
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

        <button wire:click="startCreatingSale" class="bg-amber-100 text-black p-2 rounded-sm cursor-pointer">
            Registrar venta
        </button>
    </div>

    <h1 class="text-xl font-bold mb-6">Ventas registradas</h1>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-700 text-sm text-gray-300">
            <thead class="bg-gray-800 text-gray-200">
                <tr>
                    <th class="px-2 py-2 border text-center">Fecha</th>
                    <th class="px-2 py-2 border text-center">Material</th>
                    <th class="px-2 py-2 border text-center">Kgs</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr class="hover:bg-gray-800">
                        <td class="px-2 py-1 border text-center">{{ $sale->fecha->format('Y-m-d') }}</td>
                        <td class="px-2 py-1 border text-center">{{ $this->sanitizeForJson($sale->material) }}</td>
                        <td class="px-2 py-1 border text-right">
                            {{ $this->sanitizeForJson(number_format($sale->kgs, 3)) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-2 py-1 border text-center">No hay ventas registradas.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-800 text-gray-200 font-bold">
                <tr>
                    <td class="px-2 py-1 border text-center" colspan="2">Total</td>
                    <td class="px-2 py-1 border text-right">
                        {{ $this->sanitizeForJson(number_format($sales->sum('kgs'), 3)) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($creatingSale)
        <h2 class="text-lg font-semibold mb-4 mt-6">Registrar Venta de Patio</h2>
        <div class="mb-6">

            <div class="mb-4">
                <label class="block text-sm font-medium">Fecha:</label>
                <input type="date" wire:model="fecha" class="border px-2 py-1 rounded w-full">
                @error('fecha')
                    <span class="text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium">Kgs a sacar:</label>
                <div x-data="{ initial: @js($kgs) }" x-init="initKgsCleave()">
                    <input type="text" x-ref="kgsInput" placeholder="0.000"
                        class="border px-2 py-1 rounded w-full text-right font-mono text-sm">
                </div>
                @error('kgs')
                    <span class="text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="mt-4 flex gap-4">
                <button wire:click="confirmSave" class="bg-green-500 text-white p-2 rounded-sm cursor-pointer">
                    Guardar Venta
                </button>
                <button wire:click="cancelCreatingSale" class="bg-red-500 text-white p-2 rounded-sm cursor-pointer">
                    Cancelar
                </button>
            </div>
        </div>

        <!-- Modal de confirmación -->
        <div x-data="{ open: @entangle('showConfirmModal') }" x-show="open" class="fixed inset-0 flex items-center justify-center z-50" x-cloak>
            <div class="absolute inset-0 bg-black" @click="open = false" style="opacity: 0.7"></div>
            <div x-show="open" x-transition.scale
                class="relative bg-gray-800 text-gray-200 rounded-lg shadow-lg w-96 p-6">
                <h2 class="text-lg font-bold mb-4">Confirmar venta</h2>
                <p class="mb-6">
                    ¿Registrar venta de <strong>{{ number_format($kgs, 3) }} kgs</strong>
                    de <strong>{{ $selectedMaterial }}</strong>?
                </p>
                <div class="flex justify-end space-x-3">
                    <button @click="open = false" class="px-4 py-2 bg-gray-500 rounded text-white hover:bg-gray-600">
                        Cancelar
                    </button>
                    <button wire:click="saveConfirmed"
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js"></script>
<script>
    function initKgsCleave() {
        return function() {
            const input = this.$refs.kgsInput;
            let initialValue = this.initial ?? 0;

            if (input.cleave) input.cleave.destroy();

            input.cleave = new Cleave(input, {
                numeral: true,
                numeralThousandsGroupStyle: 'thousand',
                numeralDecimalScale: 3,
                numeralDecimalMark: '.',
                delimiter: ',',
                numeralPositiveOnly: true,
                onValueChanged: function(e) {
                    const raw = e.target.rawValue || '0';
                    let num = parseFloat(raw) || 0;
                    @this.set('kgs', num);
                }
            });

            if (initialValue > 0) {
                input.cleave.setRawValue(initialValue);
            }
        };
    }
</script>