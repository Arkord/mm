<?php

use App\Models\Expense;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $expenses;

    // Campos del formulario
    public $descripcion = '';
    public $monto = '';

    // Modal
    public $showConfirmModal = false;

    public function mount()
    {
        $companyId = Auth::user()->company_id;
        $this->expenses = Expense::where('company_id', $companyId)
            ->orderBy('fecha', 'desc')
            ->get();
    }

    public function openConfirmModal()
    {
        $this->validate([
            'descripcion' => 'required|string|max:255',
            'monto' => 'required|numeric|min:0',
        ]);

        $this->showConfirmModal = true;
    }

    public function saveExpense()
    {
        $companyId = Auth::user()->company_id;
        $userId = Auth::id();

        Expense::create([
            'fecha' => now()->toDateString(),
            'descripcion' => $this->descripcion,
            'user_id' => $userId,
            'company_id' => $companyId,
            'monto' => $this->monto,
        ]);

        // refrescar lista
        $this->expenses = Expense::where('company_id', $companyId)
            ->orderBy('fecha', 'desc')
            ->get();

        // limpiar
        $this->reset(['descripcion', 'monto', 'showConfirmModal']);

        session()->flash('success', 'El gasto ha sido registrado.');
    }

    public function with()
    {
        return [
            'expenses' => $this->expenses,
        ];
    }
};
?>

<div class="p-6">
    <h1 class="text-xl font-bold mb-4">Gastos</h1>

    <!-- Formulario -->
    <div class="mb-6">
        <label class="block text-sm mb-2">Descripción</label>
        <input type="text" wire:model="descripcion"
               class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-800 dark:text-white">

        @error('descripcion') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

        <label class="block text-sm mt-4 mb-2">Monto</label>
        <input type="number" step="0.01" wire:model="monto"
               class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-800 dark:text-white">

        @error('monto') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

        <button wire:click="openConfirmModal"
                class="mt-4 bg-amber-500 text-white rounded px-4 py-2 hover:bg-amber-600">
            Guardar gasto
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
                <th class="px-4 py-2 border">Descripción</th>
                <th class="px-4 py-2 border">Monto</th>
                <th class="px-4 py-2 border">Usuario</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($expenses as $expense)
                <tr class="hover:bg-gray-100 dark:hover:bg-gray-800">
                    <td class="border px-4 py-2">{{ $expense->fecha }}</td>
                    <td class="border px-4 py-2">{{ $expense->descripcion }}</td>
                    <td class="border px-4 py-2 text-right">${{ number_format($expense->monto, 2) }}</td>
                    <td class="border px-4 py-2">{{ $expense->user?->name }}</td>
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
            <h2 class="text-lg font-bold mb-4">Confirmar gasto</h2>
            <p class="mb-6">
                ¿Registrar el gasto 
                <span class="font-semibold text-amber-400">"{{ $descripcion }}"</span>
                por <span class="font-semibold text-green-400">${{ number_format($monto ?: 0, 2) }}</span>?
            </p>
            <div class="flex justify-end space-x-3">
                <button @click="open = false"
                        class="px-4 py-2 bg-gray-400 rounded text-white hover:bg-gray-500 cursor-pointer">
                    Cancelar
                </button>
                <button wire:click="saveExpense"
                        class="px-4 py-2 bg-amber-500 text-white rounded hover:bg-amber-600 cursor-pointer">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>
