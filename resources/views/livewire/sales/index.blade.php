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
    public $materials = [
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
    public $years = [];
    public $months = [];

    // For creating sale
    public $creatingSale = false;
    public $selectedBuyItemIds = [];
    public $selectAll = false;
    public $precio_kg = 0;
    public $total = 0;
    public $totalKgs = 0;
    public $buyItems = [];
    public $showConfirmModal = false;
    public $fecha;

    /**
     * Sanitize a value for JSON (Livewire) and display (UTF-8 encoding).
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
        $this->selectedMaterial = $this->materials[0]; // Default to FIERRO
        $this->fecha = $today->format('Y-m-d');

        // Cargar ítems iniciales
        $this->loadItems();
    }

    // Reactividad al cambiar año
    public function updatedSelectedYear($value)
    {
        $this->loadItems();
        $this->resetSaleState();
        Log::info('Year filter changed', ['selectedYear' => $value, 'totalKgs' => $this->totalKgs, 'selectAll' => $this->selectAll]);
    }

    // Reactividad al cambiar mes
    public function updatedSelectedMonth($value)
    {
        $this->loadItems();
        $this->resetSaleState();
        Log::info('Month filter changed', ['selectedMonth' => $value, 'totalKgs' => $this->totalKgs, 'selectAll' => $this->selectAll]);
    }

    // Reactividad al cambiar material
    public function updatedSelectedMaterial($value)
    {
        $this->loadItems();
        $this->resetSaleState();
        Log::info('Material filter changed', ['selectedMaterial' => $value, 'totalKgs' => $this->totalKgs, 'selectAll' => $this->selectAll]);
    }

    private function resetSaleState()
    {
        $this->selectAll = false;
        $this->selectedBuyItemIds = [];
        $this->totalKgs = 0;
        $this->total = 0;
        $this->fecha = now()->format('Y-m-d');
    }

    public function loadItems()
    {
        $companyId = Auth::user()->company_id;

        if (!$this->selectedYear || !$this->selectedMonth || !$this->selectedMaterial) {
            $this->sales = collect();
            $this->buyItems = collect();
            return;
        }

        $start = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = Sale::where('company_id', $companyId)
            ->whereDate('fecha', '>=', $start->toDateString())
            ->whereDate('fecha', '<=', $end->toDateString())
            ->where('material', $this->selectedMaterial);

        $this->sales = $query->get();
        $this->sales = $this->sanitizeCollection($this->sales);

        // Load BuyItems for selected material with purchase date
        $this->buyItems = BuyItem::where('material', $this->selectedMaterial)
            ->whereHas('buy', function ($q) use ($companyId, $start, $end) {
                $q->where('company_id', $companyId)
                  ->whereDate('fecha', '>=', $start->toDateString())
                  ->whereDate('fecha', '<=', $end->toDateString());
            })
            ->with('buy') // Eager load buy relationship for fecha
            ->get();

        Log::info('Loaded sales', ['count' => $this->sales->count()]);
        Log::info('Loaded buy items', ['count' => $this->buyItems->count()]);
    }

    public function startCreatingSale()
    {
        $this->creatingSale = true;
        $this->selectedBuyItemIds = [];
        $this->selectAll = false;
        $this->precio_kg = 0;
        $this->total = 0;
        $this->totalKgs = 0;
        $this->fecha = now()->format('Y-m-d');
    }

    public function cancelCreatingSale()
    {
        $this->creatingSale = false;
        $this->selectedBuyItemIds = [];
        $this->selectAll = false;
        $this->precio_kg = 0;
        $this->total = 0;
        $this->totalKgs = 0;
        $this->showConfirmModal = false;
        $this->fecha = now()->format('Y-m-d');
    }

    public function toggleSelectAll()
    {
        if ($this->selectAll) {
            foreach ($this->buyItems as $buyItem) {
                if ($buyItem->availableKgs() > 0) {
                    $this->selectedBuyItemIds[$buyItem->id] = true;
                }
            }
        } else {
            $this->selectedBuyItemIds = [];
        }
        $this->calculateTotal();
        Log::info('Toggle Select All', ['selectAll' => $this->selectAll, 'totalKgs' => $this->totalKgs, 'total' => $this->total]);
    }

    public function updateTotalKgs()
    {
        $this->selectAll = count(array_filter($this->selectedBuyItemIds)) === count($this->buyItems->filter(function ($item) {
            return $item->availableKgs() > 0;
        }));
        $this->calculateTotal();
        Log::info('Update Total Kgs', ['selectedBuyItemIds' => $this->selectedBuyItemIds, 'totalKgs' => $this->totalKgs, 'total' => $this->total]);
    }

    public function updatedSelectedBuyItemIds($value, $key)
    {
        $this->selectAll = count(array_filter($this->selectedBuyItemIds)) === count($this->buyItems->filter(function ($item) {
            return $item->availableKgs() > 0;
        }));
        $this->calculateTotal();
        Log::info('Selected BuyItem updated', ['buyItemId' => $key, 'selected' => $value, 'totalKgs' => $this->totalKgs, 'total' => $this->total]);
    }

    public function updatedPrecioKg($value)
    {
        Log::debug('Entering updatedPrecioKg', ['input_value' => $value, 'previous_precio_kg' => $this->precio_kg]);
        $this->precio_kg = (float) $value;
        $this->calculateTotal();
        $this->dispatch('update-total'); // Trigger frontend update
        Log::info('Precio Kg updated', ['input_value' => $value, 'precio_kg' => $this->precio_kg, 'totalKgs' => $this->totalKgs, 'total' => $this->total]);
    }

    private function calculateTotal()
    {
        $this->totalKgs = 0;
        foreach ($this->selectedBuyItemIds as $buyItemId => $selected) {
            if ($selected) {
                $buyItem = $this->buyItems->find($buyItemId);
                if ($buyItem) {
                    $this->totalKgs += $buyItem->availableKgs();
                }
            }
        }
        $this->total = $this->totalKgs * $this->precio_kg;
        Log::info('Calculate Total', ['totalKgs' => $this->totalKgs, 'precio_kg' => $this->precio_kg, 'total' => $this->total]);
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
            'totalKgs' => 'required|numeric|min:0.001',
            'precio_kg' => 'required|numeric|min:0.01',
        ]);

        $buyItems = $this->buyItems->whereIn('id', array_keys(array_filter($this->selectedBuyItemIds)));
        $this->totalKgs = 0;
        foreach ($buyItems as $buyItem) {
            $kgs = $buyItem->availableKgs();
            if ($kgs <= 0) {
                $this->addError('saleKgs.' . $buyItem->id, 'No hay kgs disponibles para este ítem.');
                return;
            }
            $this->totalKgs += $kgs;
        }

        if ($this->totalKgs <= 0) {
            $this->addError('kgs', 'Debe seleccionar al menos un ítem con kgs disponibles.');
            return;
        }

        if ($this->precio_kg <= 0) {
            $this->addError('precio_kg', 'El precio por kg debe ser mayor a 0.');
            return;
        }

        $sale = Sale::create([
            'fecha' => $this->fecha,
            'material' => $this->selectedMaterial,
            'company_id' => Auth::user()->company_id,
            'user_id' => Auth::id(),
            'kgs' => $this->totalKgs,
            'precio_kg' => $this->precio_kg,
            'total' => $this->total,
        ]);

        foreach ($buyItems as $buyItem) {
            $kgs = $buyItem->availableKgs();
            if ($kgs > 0) {
                SaleBuyItem::create([
                    'sale_id' => $sale->id,
                    'buy_item_id' => $buyItem->id,
                    'kgs' => $kgs,
                ]);
            }
        }

        session()->flash('success', 'Venta registrada correctamente.');
        $this->loadItems();
        $this->cancelCreatingSale();
    }
}; ?>

<div class="p-6">
    <h1 class="text-xl font-bold mb-6">Criterios</h1>

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
            <label for="material-select" class="block text-sm font-medium">Material:</label>
            <select wire:model.live="selectedMaterial" id="material-select" class="border px-2 py-1 rounded">
                @foreach ($materials as $material)
                    <option value="{{ $material }}">{{ $material }}</option>
                @endforeach
            </select>
        </div>

        <button wire:click="startCreatingSale" class="bg-amber-100 text-black p-2 rounded-sm">Registrar venta</button>
    </div>

    <h1 class="text-xl font-bold mb-6">Ventas registradas</h1>

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
                        <td class="px-2 py-1 border text-center">{{ $sale->fecha }}</td>
                        <td class="px-2 py-1 border text-center">{{ $this->sanitizeForJson($sale->material) }}</td>
                        <td class="px-2 py-1 border text-right">{{ $this->sanitizeForJson(number_format($sale->kgs, 3)) }}</td>
                        <td class="px-2 py-1 border text-right">{{ $this->sanitizeForJson(number_format($sale->precio_kg, 2)) }}</td>
                        <td class="px-2 py-1 border text-right">{{ $this->sanitizeForJson(number_format($sale->total, 2)) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-2 py-1 border text-center">No hay ventas registradas para este período.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-800 text-gray-200 font-bold">
                <tr>
                    <td class="px-2 py-1 border text-center" colspan="2">Total</td>
                    <td class="px-2 py-1 border text-right">{{ $this->sanitizeForJson(number_format($sales->sum('kgs'), 3)) }}</td>
                    <td class="px-2 py-1 border text-center">-</td>
                    <td class="px-2 py-1 border text-right">{{ $this->sanitizeForJson(number_format($sales->sum('total'), 2)) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($creatingSale)
        <h2 class="text-lg font-semibold mb-4 mt-6">Crear Venta</h2>
        <div class="mb-6 overflow-x-auto">
            <div class="mb-4">
                <label for="fecha" class="block text-sm font-medium">Fecha de Venta:</label>
                <input type="date" wire:model="fecha" id="fecha" class="border px-2 py-1 rounded w-full">
                @error('fecha') <span class="text-red-500">{{ $message }}</span> @enderror
            </div>

            <table class="min-w-full border border-gray-700 text-sm text-gray-300">
                <thead class="bg-gray-800 text-gray-200">
                    <tr>
                        <th class="px-2 py-2 border text-center">
                            <input type="checkbox" wire:model="selectAll" wire:change="toggleSelectAll" /> Seleccionar Todos
                        </th>
                        <th class="px-2 py-2 border text-center">Fecha de Compra</th>
                        <th class="px-2 py-2 border text-center">Disponible (kgs)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($buyItems->filter(function ($buyItem) { return $buyItem->availableKgs() > 0; }) as $buyItem)
                        <tr class="hover:bg-gray-800">
                            <td class="px-2 py-1 border text-center">
                                <input type="checkbox" wire:model="selectedBuyItemIds.{{ $buyItem->id }}" wire:change="updateTotalKgs" value="{{ $buyItem->id }}" />
                            </td>
                            <td class="px-2 py-1 border text-center">{{ $buyItem->buy->fecha }}</td>
                            <td class="px-2 py-1 border text-right">{{ number_format($buyItem->availableKgs(), 3) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-2 py-1 border text-center">No hay ítems disponibles para este material.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <label class="block text-sm font-medium mt-4">Total Kgs:</label>
            <p class="text-lg font-bold">{{ number_format($totalKgs, 3) }}</p>

            <label for="precio_kg" class="block text-sm font-medium mt-4">Precio/Kg:</label>
            <input type="number" wire:model.live="precio_kg" id="precio_kg" class="border px-2 py-1 rounded w-full" min="0" step="0.01" x-on:input.debounce.500ms="if ($el.value.match(/^\d*\.?\d*$/)) $el.value = $el.value">
            @error('precio_kg') <span class="text-red-500">{{ $message }}</span> @enderror

            <label class="block text-sm font-medium mt-4">Total:</label>
            <p class="text-lg font-bold" x-on:update-total.window="$refresh">{{ number_format($total, 2) }}</p>

            <div class="mt-4 flex gap-4">
                <button type="button" wire:click="confirmSave" class="bg-green-500 text-white p-2 rounded-sm cursor-pointer">Guardar Venta</button>
                <button wire:click="cancelCreatingSale" class="bg-red-500 text-white p-2 rounded-sm cursor-pointer">Cancelar</button>
            </div>
        </div>

        <!-- Modal de confirmación -->
        <div x-data="{ open: @entangle('showConfirmModal') }" x-show="open" class="fixed inset-0 flex items-center justify-center z-50" x-cloak>
            <!-- Fondo oscuro -->
            <div class="absolute inset-0 bg-black" @click="open = false" style="opacity: 0.7"></div>

            <!-- Caja del modal -->
            <div x-show="open" x-transition.scale class="relative bg-gray-800 text-gray-200 rounded-lg shadow-lg w-96 p-6">
                <h2 class="text-lg font-bold mb-4">Confirmar venta</h2>
                <p class="mb-6">
                    ¿Deseas registrar esta venta con un total de
                    <span class="font-semibold text-green-400">${{ number_format($total, 2) }}</span>?
                </p>
                <div class="flex justify-end space-x-3">
                    <button @click="open = false" class="px-4 py-2 bg-gray-500 rounded text-white hover:bg-gray-600 cursor-pointer">
                        Cancelar
                    </button>
                    <button wire:click="saveConfirmed" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 cursor-pointer">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>