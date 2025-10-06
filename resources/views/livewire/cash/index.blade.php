<?php

use App\Models\Cash;
use App\Models\Company;
use Livewire\Volt\Component;

new class extends Component {
    public $cashList;

    // Campos del formulario
    public $company_id = '';
    public $monto = '';

    // Modal
    public $showConfirmModal = false;

    public function mount()
    {
        $this->cashList = Cash::with('company')
            ->orderBy('fecha', 'desc')
            ->get();
    }

    public function openConfirmModal()
    {
        $this->validate([
            'company_id' => 'required|exists:companies,id',
            'monto' => 'required|numeric|min:0',
        ]);

        $this->showConfirmModal = true;
    }

    public function saveCash()
    {
        Cash::create([
            'fecha' => now()->toDateString(),
            'company_id' => $this->company_id,
            'monto' => $this->monto,
        ]);

        // refrescar lista
        $this->cashList = Cash::with('company')
            ->orderBy('fecha', 'desc')
            ->get();

        // limpiar
        $this->reset(['company_id', 'monto', 'showConfirmModal']);

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

        <label class="block text-sm mt-4 mb-2">Monto</label>
        <input type="number" step="0.01" wire:model="monto"
               class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-800 dark:text-white">
        @error('monto') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

        <button wire:click="openConfirmModal"
                class="mt-4 bg-amber-500 text-white rounded px-4 py-2 hover:bg-amber-600">
            Guardar
        </button>
    </div>

    <!-- Mensaje -->
    @if (session('success'))
        <div class="p-2 mb-4 text-green-700 bg-green-100 rounded">
            {{ session('success') }}
        </div>
    @endif

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
            @foreach ($cashList as $cash)
                <tr class="hover:bg-gray-100 dark:hover:bg-gray-800">
                    <td class="border px-4 py-2">{{ $cash->fecha }}</td>
                    <td class="border px-4 py-2">{{ $cash->company?->name }}</td>
                    <td class="border px-4 py-2 text-right">${{ number_format($cash->monto, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
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
                un monto de <span class="font-semibold text-green-400">${{ number_format($monto ?: 0, 2) }}</span>?
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
