<?php

use App\Models\Sale;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public $sales = [];
    public $selectedYear;
    public $selectedMonth;
    public $selectedMaterial;
    public $materials = ['FIERRO', 'LAMINA', 'COBRE', 'BRONCE', 'ALUMINIO', 'BOTE', 'ARCHIVO', 'CARTON', 'PLASTICO', 'PET', 'BATERIAS', 'VIDRIO'];
    public $years = [];
    public $months = [];

    public $creatingSale = false;
    public $kgs = 0.000;
    public $precio_kg = 0.00;
    public $total = 0.00;
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
        $this->kgs = 0.000;
        $this->precio_kg = 0.00;
        $this->total = 0.00;
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

        $this->sales = Sale::patio()
            ->where('company_id', $companyId)
            ->whereDate('fecha', '>=', $start)
            ->whereDate('fecha', '<=', $end)
            ->where('material', $this->selectedMaterial)
            ->orderBy('fecha', 'desc')
            ->get();

        $this->sales = $this->sanitizeCollection($this->sales);
    }

    public function startCreatingSale()
    {
        $this->creatingSale = true;
        $this->resetSaleState();
    }

    public function cancelCreatingSale()
    {
        $this->creatingSale = false;
        $this->showConfirmModal = false;
    }

    public function updatedKgs() { $this->calculateTotal(); }
    public function updatedPrecioKg($value) 
    { 
        $this->precio_kg = floatval($value);
        $this->calculateTotal(); 
    }

    private function calculateTotal()
    {
        $this->total = round($this->kgs * $this->precio_kg, 2);
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
            'fecha'      => 'required|date',
            'kgs'        => 'required|numeric|min:0.001',
            'precio_kg'  => 'required|numeric|min:0',
        ]);

        $companyId = Auth::user()->company_id;

        Sale::create([
            'fecha'       => $this->fecha,
            'material'    => $this->selectedMaterial,
            'company_id'  => $companyId,
            'user_id'     => Auth::id(),
            'kgs'         => $this->kgs,
            'precio_kg'   => $this->precio_kg,
            'total'       => $this->total,
            'type'        => Sale::TYPE_PATIO,
        ]);

        session()->flash('success', 'Venta de patio registrada correctamente.');
        $this->loadItems();
        $this->cancelCreatingSale();
    }

    private function sanitizeForJson($value)
    {
        if (is_null($value) || $value === '-' || is_numeric($value)) return $value;
        $string = (string) $value;
        return mb_convert_encoding($string, 'UTF-8', 'auto') ?? utf8_encode($string);
    }

    private function sanitizeCollection($collection)
    {
        return $collection->map(function ($item) {
            if (isset($item->material)) $item->material = $this->sanitizeForJson($item->material);
            return $item;
        });
    }
}; ?>

<div class="p-6">
    <h1 class="text-xl font-bold mb-6 text-gray-200">Ventas de Patio</h1>

    <!-- Filtros -->
    <div class="mb-6 flex gap-4 items-end flex-wrap">
        <div>
            <label class="block text-sm font-medium text-gray-300">Año:</label>
            <select wire:model.live="selectedYear" class="border border-gray-600 bg-gray-800 text-gray-200 px-3 py-1.5 rounded">
                @foreach ($years as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-300">Mes:</label>
            <select wire:model.live="selectedMonth" class="border border-gray-600 bg-gray-800 text-gray-200 px-3 py-1.5 rounded">
                @foreach ($months as $month)
                    <option value="{{ $month }}">
                        {{ \Carbon\Carbon::create()->month($month)->locale('es')->isoFormat('MMMM') }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-300">Material:</label>
            <select wire:model.live="selectedMaterial" class="border border-gray-600 bg-gray-800 text-gray-200 px-3 py-1.5 rounded">
                @foreach ($materials as $material)
                    <option value="{{ $material }}">{{ $material }}</option>
                @endforeach
            </select>
        </div>

        <button wire:click="startCreatingSale" 
                class="bg-amber-100 hover:bg-amber-200 text-black px-4 py-1.5 rounded-sm font-medium cursor-pointer">
            Registrar venta
        </button>
    </div>

    <!-- Tabla de ventas -->
    <h2 class="text-xl font-bold mb-4 text-gray-200">Ventas registradas</h2>

    <div class="overflow-x-auto mb-8">
        <table class="min-w-full border border-gray-700 text-sm text-gray-300">
            <thead class="bg-gray-800 text-gray-200">
                <tr>
                    <th class="px-3 py-2 border text-center">Fecha</th>
                    <th class="px-3 py-2 border text-center">Material</th>
                    <th class="px-3 py-2 border text-center">Kgs</th>
                    <th class="px-3 py-2 border text-center">Precio/Kg</th>
                    <th class="px-3 py-2 border text-center">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr class="hover:bg-gray-800/70">
                        <td class="px-3 py-2 border text-center">{{ $sale->fecha->format('d/m/Y') }}</td>
                        <td class="px-3 py-2 border text-center">{{ $this->sanitizeForJson($sale->material) }}</td>
                        <td class="px-3 py-2 border text-right">{{ number_format($sale->kgs, 3) }}</td>
                        <td class="px-3 py-2 border text-right">${{ number_format($sale->precio_kg, 2) }}</td>
                        <td class="px-3 py-2 border text-right font-medium text-green-400">${{ number_format($sale->total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-4 border text-center text-gray-500">No hay ventas registradas este mes.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-800 text-gray-200 font-bold">
                <tr>
                    <td colspan="2" class="px-3 py-2 border text-right">Total</td>
                    <td class="px-3 py-2 border text-right">{{ number_format($sales->sum('kgs'), 3) }}</td>
                    <td class="px-3 py-2 border text-center">-</td>
                    <td class="px-3 py-2 border text-right text-green-400">${{ number_format($sales->sum('total'), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($creatingSale)
        <div class="mt-8 bg-gray-900 p-6 rounded-lg border border-gray-700">
            <h2 class="text-lg font-semibold mb-6 text-gray-200">Nueva Venta de Patio</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Fecha</label>
                    <input type="date" wire:model="fecha" class="w-full border border-gray-600 bg-gray-800 text-gray-200 px-3 py-2 rounded">
                    @error('fecha') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Kilos</label>
                    <div x-data="{ initial: @js($kgs) }" x-init="initKgsCleave()">
                        <input type="text" x-ref="kgsInput" placeholder="0.000"
                               class="w-full border border-gray-600 bg-gray-800 text-gray-200 px-3 py-2 rounded text-right font-mono">
                    </div>
                    @error('kgs') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Precio por kg</label>
                    <div x-data="{ initial: @js($precio_kg) }" x-init="initPrecioCleave()">
                        <input type="text" x-ref="precioInput" placeholder="$0.00"
                               class="w-full border border-gray-600 bg-gray-800 text-gray-200 px-3 py-2 rounded text-right font-mono">
                    </div>
                    @error('precio_kg') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Total</label>
                    <div class="w-full border border-gray-600 bg-gray-700 text-gray-100 px-3 py-2 rounded text-right font-mono font-semibold">
                        ${{ number_format($total, 2) }}
                    </div>
                </div>
            </div>

            <div class="mt-8 flex gap-4">
                <button wire:click="confirmSave" 
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded font-medium transition cursor-pointer">
                    Guardar venta
                </button>
                <button wire:click="cancelCreatingSale" 
                        class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded font-medium transition cursor-pointer">
                    Cancelar
                </button>
            </div>
        </div>

        <!-- Modal de confirmación -->
        <div x-data="{ open: @entangle('showConfirmModal') }" 
             x-show="open" 
             class="fixed inset-0 z-50 flex items-center justify-center" 
             x-cloak>
            <div class="absolute inset-0 bg-black opacity-70" @click="open = false"></div>
            
            <div x-show="open" 
                 x-transition.scale.origin.center 
                 class="relative bg-gray-800 text-gray-200 rounded-lg shadow-2xl w-full max-w-md mx-4 p-6 border border-gray-700">
                
                <h3 class="text-lg font-bold mb-4 text-gray-100">Confirmar venta</h3>
                
                <div class="space-y-3 mb-6 text-sm">
                    <p>Material: <span class="font-medium text-amber-300">{{ $selectedMaterial }}</span></p>
                    <p>Cantidad: <span class="font-medium">{{ number_format($kgs, 3) }} kg</span></p>
                    <p>Precio/kg: <span class="font-medium text-green-400">${{ number_format($precio_kg, 2) }}</span></p>
                    <p class="pt-2 border-t border-gray-700 text-lg font-semibold">
                        Total: <span class="text-green-400">${{ number_format($total, 2) }}</span>
                    </p>
                </div>

                <div class="flex justify-end gap-3">
                    <button @click="open = false" 
                            class="px-5 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded font-medium transition">
                        Cancelar
                    </button>
                    <button wire:click="saveConfirmed" 
                            class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white rounded font-medium transition">
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
                @this.set('kgs', parseFloat(e.target.rawValue) || 0);
            }
        });

        if (initialValue > 0) {
            input.cleave.setRawValue(initialValue);
        }
    };
}

function initPrecioCleave() {
    return function() {
        const input = this.$refs.precioInput;
        let initialValue = this.initial ?? 0.00;

        if (input.cleave) input.cleave.destroy();

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
                const value = parseFloat(raw) || 0;
                @this.set('precio_kg', value);
            }
        });

        // Establecemos el valor inicial correctamente
        input.cleave.setRawValue(initialValue);
        
        // Si es exactamente 0, forzamos visualmente $0.00 sin romper la funcionalidad
        if (initialValue === 0 || initialValue === 0.00) {
            setTimeout(() => {
                input.value = '$0.00';
            }, 10);
        }
    };
}
</script>