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

    public $materials = ['FIERRO', 'LAMINA', 'COBRE', 'BRONCE', 'ALUMINIO', 'BOTE', 'ARCHIVO', 'CARTON', 'PLASTICO', 'PET', 'BATERIAS', 'VIDRIO'];

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

                    <!-- Peso -->
                    <div x-data="{ index: {{ $i }}, initial: @js($item['kgs']) ?? 0 }" x-init="initPesoCleave()">
                        <input type="text" x-ref="pesoInput" placeholder="0.000"
                            class="border rounded p-2 text-right font-mono text-sm w-full">
                    </div>

                    <!-- Precio -->
                    <div x-data="{ index: {{ $i }}, initial: @js($item['precio_kg']) ?? 0 }" x-init="initCleave()">
                        <input type="text" x-ref="precioInput" placeholder="$0.00"
                            class="border rounded p-2 text-right font-mono text-sm w-full">
                    </div>

                    <!-- Total -->
                    <input type="text" readonly
                        :value="(@js($item['total']) > 0 ? '$' + Number(@js($item['total'])).toLocaleString(
                            'es-MX', { minimumFractionDigits: 2 }) : '$0.00')"
                        class="border rounded p-2 bg-gray-900 text-right font-mono text-sm">
                </div>
            @endforeach

            <button type="button" wire:click="addItem" class="px-3 py-1 bg-blue-500 text-white rounded cursor-pointer">
                + Agregar material
            </button>
        </div>

        <div class="flex justify-end mt-6">
            <div class="text-lg font-bold text-right">
                Total General: <span class="font-mono">${{ number_format($totalGeneral, 2) }}</span>
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

<script src="https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js"></script>

<script>
    function initCleave() {
        return function() {
            const input = this.$refs.precioInput;
            const index = this.index;
            let initialValue = this.initial ?? 0;

            if (!initialValue || isNaN(initialValue)) initialValue = 0;

            if (input.cleave) input.cleave.destroy();

            // === FORZAR $0.00 SIEMPRE ===
            input.value = '$0.00';

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
                    let num = parseFloat(raw) || 0;

                    // Si el usuario borra todo, mantener 0
                    if (raw === '' || raw === '$') num = 0;

                    @this.set(`items.${index}.precio_kg`, num);

                    // === FORZAR $0.00 SI EL VALOR ES 0 ===
                    if (num === 0) {
                        setTimeout(() => {
                            if (input.value.trim() === '' || input.value === '$') {
                                input.value = '$0.00';
                            }
                        }, 0);
                    }
                }
            });

            // === NO USAR setRawValue(0) → CAUSA EL PROBLEMA ===
            // En su lugar, si hay valor, aplícalo
            if (initialValue > 0) {
                input.cleave.setRawValue(initialValue);
            }
        };
    }

    function initPesoCleave() {
        return function() {
            const input = this.$refs.pesoInput;
            const index = this.index;
            let initialValue = this.initial ?? 0;

            if (!initialValue || isNaN(initialValue)) initialValue = 0;

            if (input.cleave) input.cleave.destroy();

            // === FORZAR 0.000 SIEMPRE ===
            input.value = '0.000';

            input.cleave = new Cleave(input, {
                numeral: true,
                numeralThousandsGroupStyle: 'none',
                numeralDecimalScale: 3,
                numeralDecimalMark: '.',
                delimiter: '',
                numeralPositiveOnly: true,
                onValueChanged: function(e) {
                    const raw = e.target.rawValue || '0';
                    let num = parseFloat(raw) || 0;

                    // Si el usuario borra todo, mantener 0
                    if (raw === '') num = 0;

                    @this.set(`items.${index}.kgs`, num);

                    // === FORZAR 0.000 SI EL VALOR ES 0 ===
                    if (num === 0) {
                        setTimeout(() => {
                            if (input.value.trim() === '') {
                                input.value = '0.000';
                            }
                        }, 0);
                    }
                }
            });

            // === NO USAR setRawValue(0) ===
            if (initialValue > 0) {
                input.cleave.setRawValue(initialValue);
            }
        };
    }
</script>
