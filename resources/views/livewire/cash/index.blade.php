<?php

use App\Models\Cash;
use App\Models\Company;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    public $cashList;

    // Campos del formulario
    public $company_id = '';
    public $monto = '';
    public $fecha = '';

    // Filtros
    public $selectedYear;
    public $selectedMonth;
    public $years = [];
    public $months = [];

    // Modal
    public $showConfirmModal = false;

    public function mount()
    {
        $today = now();

        // Inicializar años y meses
        $this->years = range(2020, $today->year);
        $this->months = range(1, 12);

        // Establecer valores iniciales
        $this->selectedYear = $today->year;
        $this->selectedMonth = $today->month;
        $this->fecha = $today->toDateString();

        // Cargar registros iniciales
        $this->loadCash();
    }

    // Reactividad al cambiar año
    public function updatedSelectedYear($value)
    {
        $this->loadCash();
    }

    // Reactividad al cambiar mes
    public function updatedSelectedMonth($value)
    {
        $this->loadCash();
    }

    private function loadCash()
    {
        if (!$this->selectedYear || !$this->selectedMonth) {
            $this->cashList = collect();
            return;
        }

        $start = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $this->cashList = Cash::with('company')
            ->whereDate('fecha', '>=', $start->toDateString())
            ->whereDate('fecha', '<=', $end->toDateString())
            ->orderBy('fecha', 'desc')
            ->get();
    }

    public function openConfirmModal()
    {
        $this->validate([
            'company_id' => 'required|exists:companies,id',
            'monto' => 'required|numeric|min:0',
            'fecha' => 'required|date|before_or_equal:today',
        ]);

        $this->showConfirmModal = true;
    }

    public function saveCash()
    {
        Cash::create([
            'fecha' => $this->fecha,
            'company_id' => $this->company_id,
            'monto' => $this->monto,
        ]);

        // Refrescar lista
        $this->loadCash();

        // Limpiar
        $this->reset(['company_id', 'monto', 'fecha', 'showConfirmModal']);
        $this->fecha = now()->toDateString();

        session()->flash('success', 'El registro de caja ha sido agregado.');
    }

    public function with()
    {
        return [
            'cashList' => $this->cashList,
        ];
    }
};
?>

<div class="p-6">
    <h1 class="text-xl font-bold mb-4">Caja</h1>

    <!-- Formulario -->
    <div class="mb-6">
        <label class="block text-sm mb-2">Compañía</label>
        <select wire:model="company_id"
                class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-800 dark:text-white">
            <option value="">-- Seleccionar compañía --</option>
            @foreach (App\Models\Company::all() as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
        @error('company_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

        <label class="block text-sm mt-4 mb-2">Fecha</label>
        <input type="date" wire:model="fecha"
               class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-800 dark:text-white"
               max="{{ now()->toDateString() }}">
        @error('fecha') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

        <label class="block text-sm mt-4 mb-2">Monto</label>
        <input type="number" step="0.01" wire:model="monto"
               class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-800 dark:text-white">
        @error('monto') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

        <button wire:click="openConfirmModal"
                class="mt-4 bg-amber-500 text-white rounded px-4 py-2 hover:bg-amber-600 cursor-pointer">
            Guardar
        </button>
    </div>

    <!-- Mensaje -->
    @if (session('success'))
        <div class="p-2 mb-4 text-green-700 bg-green-100 rounded">
            {{ session('success') }}
        </div>
    @endif

    <!-- Filtros -->
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
    </div>

    <!-- Tabla -->
    <table class="table-auto w-full border border-gray-500 text-sm">
        <thead>
            <tr class="bg-gray-800 text-white">
                <th class="px-4 py-2 border">Fecha</th>
                <th class="px-4 py-2 border">Compañía</th>
                <th class="px-4 py-2 border">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($cashList as $cash)
                <tr class="hover:bg-gray-100 dark:hover:bg-gray-800">
                    <td class="border px-4 py-2">{{ $cash->fecha }}</td>
                    <td class="border px-4 py-2">{{ $cash->company?->name }}</td>
                    <td class="border px-4 py-2 text-right">${{ number_format($cash->monto, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="border px-4 py-2 text-center">No hay registros de caja para este período.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot class="bg-gray-800 text-white font-bold">
            <tr>
                <td class="px-4 py-2 border text-center" colspan="2">Total</td>
                <td class="px-4 py-2 border text-right">${{ number_format($cashList->sum('monto'), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <!-- Modal de confirmación -->
    <div x-data="{ open: @entangle('showConfirmModal') }"
         x-show="open"
         class="fixed inset-0 flex items-center justify-center z-50"
         x-cloak>
        <!-- Fondo -->
        <div class="absolute inset-0 bg-black" @click="open = false" style="opacity: 0.7"></div>

        <!-- Caja -->
        <div x-show="open" x-transition.scale
             class="relative bg-gray-800 text-gray-200 rounded-lg shadow-lg w-96 p-6">
            <h2 class="text-lg font-bold mb-4">Confirmar registro</h2>
            <p class="mb-6">
                ¿Registrar en caja 
                <span class="font-semibold text-amber-400">{{ optional(App\Models\Company::find($company_id))->name }}</span>
                un monto de <span class="font-semibold text-green-400">${{ number_format($monto ?: 0, 2) }}</span>
                con fecha <span class="font-semibold text-blue-400">{{ $fecha }}</span>?
            </p>
            <div class="flex justify-end space-x-3">
                <button @click="open = false"
                        class="px-4 py-2 bg-gray-400 rounded text-white hover:bg-gray-500 cursor-pointer">
                    Cancelar
                </button>
                <button wire:click="saveCash"
                        class="px-4 py-2 bg-amber-500 text-white rounded hover:bg-amber-600 cursor-pointer">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>