<?php

use App\Models\Buy;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public $fecha;
    public $company_id;
    public $items = [];

    public $totalGeneral = 0;

    public $materials = ['FIERRO', 'LAMINA', 'COBRE', 'BRONCE', 'ALUMINIO', 'BOTE', 'ARCHIVO', 'CARTON', 'PLASTICO', 'PET', 'BATERIAS', 'OTRO'];

    public $showConfirmModal = false;

    public function mount()
    {
        $this->fecha = now()->setTimezone('America/Mexico_City')->format('Y-m-d');
        $this->company_id = Auth::user()->company_id;
        $this->items = [['material' => '', 'kgs' => 0, 'precio_kg' => 0, 'total' => 0]];
    }

    public function confirmSave()
    {
        $this->showConfirmModal = true;
    }

    public function saveConfirmed()
    {
        $this->save();
        $this->showConfirmModal = false;
    }

    public function addItem()
    {
        $this->items[] = ['material' => '', 'kgs' => 0, 'precio_kg' => 0, 'total' => 0];
    }

    public function updatedItems()
    {
        $this->totalGeneral = 0;

        foreach ($this->items as $i => $item) {
            $kgs = isset($item['kgs']) && is_numeric($item['kgs']) ? (float) $item['kgs'] : 0;
            $precio = isset($item['precio_kg']) && is_numeric($item['precio_kg']) ? (float) $item['precio_kg'] : 0;

            $this->items[$i]['total'] = $kgs * $precio;
            $this->totalGeneral += $this->items[$i]['total'];
        }
    }

    public function save()
    {
        $this->validate([
            'items.*.material' => 'required|string',
            'items.*.kgs' => 'required|numeric|min:0.01',
            'items.*.precio_kg' => 'required|numeric|min:0.01',
        ]);

        $buy = Buy::create([
            'fecha' => now(),
            'user_id' => Auth::id(),
            'company_id' => $this->company_id,
        ]);

        foreach ($this->items as $item) {
            $buy->items()->create($item);
        }

        session()->flash('success', 'Compra registrada correctamente.');
        return redirect()->route('buys.index');
    }

    public function with()
    {
        return [
            'companies' => Company::all(),
        ];
    }
};

?>

<div class="p-6">
    <h1 class="text-xl font-bold mb-4">Nueva Compra</h1>

    <form wire:submit.prevent="save" class="space-y-4">
        <div>
            <label class="block">Fecha</label>
            <input type="date" wire:model="fecha" class="border rounded p-2 w-full bg-gray-900"
                value="{{ $fecha }}" readonly>
        </div>

        <div>
            <label class="block">Empresa</label>
            <input type="text" value="{{ Auth::user()->company->name }}"
                class="border rounded p-2 w-full bg-gray-900" readonly>
        </div>

        <div class="space-y-2">
            <h2 class="font-semibold">Elementos</h2>
            <div class="grid grid-cols-4 gap-2">
                <label for="">Material</label>
                <label for="">Peso</label>
                <label for="">Precio por Kg</label>
                <label for="">Total</label>
            </div>
            @foreach ($items as $i => $item)
                <div class="grid grid-cols-4 gap-2">
                    <select wire:model="items.{{ $i }}.material" class="border rounded p-2">
                        <option value="">-- Material --</option>
                        @foreach ($materials as $m)
                            <option value="{{ $m }}">{{ $m }}</option>
                        @endforeach
                    </select>

                    <input type="number" step="0.001" wire:model.live="items.{{ $i }}.kgs"
                        placeholder="Kgs" class="border rounded p-2">

                    <input type="number" step="0.01" wire:model.live="items.{{ $i }}.precio_kg"
                        placeholder="Precio/kg" class="border rounded p-2">

                    <input type="text" readonly value="{{ number_format($item['total'], 2) }}"
                        class="border rounded p-2 bg-gray-900">
                </div>
            @endforeach

            <button type="button" wire:click="addItem" class="px-3 py-1 bg-blue-500 text-white rounded cursor-pointer">
                + Agregar material
            </button>
        </div>

        <div class="flex justify-end mt-6">
            <div class="text-lg font-bold">
                Total General: ${{ number_format($totalGeneral, 2) }}
            </div>
        </div>

        <div class="flex justify-center mt-10">
            <button type="button" wire:click="confirmSave"
                class="px-4 py-2 bg-green-600 text-white rounded cursor-pointer">
                Guardar Compra
            </button>
        </div>
    </form>


    <!-- Modal de confirmación -->
    <div x-data="{ open: @entangle('showConfirmModal') }" x-show="open" class="fixed inset-0 flex items-center justify-center z-50" x-cloak>

        <!-- Fondo oscuro -->
        <div class="absolute inset-0 bg-black" @click="open = false" style="opacity: 0.7"></div>

        <!-- Caja del modal -->
        <div x-show="open" x-transition.scale class="relative bg-gray-800 text-gray-200 rounded-lg shadow-lg w-96 p-6">
            <h2 class="text-lg font-bold mb-4">Confirmar compra</h2>
            <p class="mb-6">
                ¿Deseas registrar esta compra con un total de
                <span class="font-semibold text-green-400">${{ number_format($totalGeneral, 2) }}</span>?
            </p>
            <div class="flex justify-end space-x-3">
                <button @click="open = false"
                    class="px-4 py-2 bg-gray-500 rounded text-white hover:bg-gray-600 cursor-pointer">
                    Cancelar
                </button>
                <button wire:click="saveConfirmed"
                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 cursor-pointer">
                    Confirmar
                </button>
            </div>
        </div>
    </div>

</div>
