<?php

use App\Models\Balance;
use App\Models\Company;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public $balanceList;

    // Formulario nuevo registro
    public $company_id = '';
    public $anio = '';
    public $material = '';
    public $kgs = '';
    public $monto = '';
    public $nota = '';

    // Filtros tabla
    public $selectedCompany;
    public $selectedYear;
    public $companies = [];
    public $years = [];

    // Modal agregar
    public $showConfirmModal = false;

    // Edición inline
    public $editingId = null;
    public $editKgs = 0;
    public $editMonto = 0;

    public array $materials = [
        'FIERRO','LAMINA','COBRE','BRONCE','ALUMINIO',
        'BOTE','ARCHIVO','CARTON','PLASTICO','PET','BATERIAS','VIDRIO'
    ];

    public function mount()
    {
        $today = now();

        $this->companies = Company::pluck('name', 'id')->toArray();
        $this->company_id = Auth::user()->company_id ?? array_key_first($this->companies);

        $this->years = range(2020, $today->year + 1);

        $this->selectedCompany = $this->company_id;
        $this->selectedYear = $today->year;
        $this->anio = $today->year;

        $this->loadBalances();
    }

    public function updatedSelectedCompany()
    {
        $this->loadBalances();
    }

    public function updatedSelectedYear()
    {
        $this->loadBalances();
    }

    private function loadBalances()
    {
        $query = Balance::with('company');

        if ($this->selectedCompany) {
            $query->where('company_id', $this->selectedCompany);
        }

        if ($this->selectedYear) {
            $query->where('anio', $this->selectedYear);
        }

        $this->balanceList = $query->orderByDesc('created_at')->get();
    }

    public function openConfirmModal()
    {
        $this->validate([
            'company_id' => 'required|exists:companies,id',
            'anio'       => 'required|integer|min:2020|max:2100',
            'material'   => 'required|in:' . implode(',', $this->materials),
            'kgs'        => 'required|numeric',
            'monto'      => 'required|numeric',
            'nota'       => 'nullable|string|max:255',
        ]);

        $this->showConfirmModal = true;
    }

    public function saveBalance()
    {
        Balance::create([
            'company_id' => $this->company_id,
            'anio'       => $this->anio,
            'material'   => $this->material,
            'kgs'        => $this->kgs,
            'monto'      => $this->monto,
            'nota'       => $this->nota,
        ]);

        $this->loadBalances();

        $this->reset(['material', 'kgs', 'monto', 'nota', 'showConfirmModal']);

        session()->flash('success', 'El balance ha sido agregado correctamente.');
    }

    public function startEdit($balanceId)
    {
        $balance = $this->balanceList->find($balanceId);
        if ($balance) {
            $this->editingId = $balanceId;
            $this->editKgs = $balance->kgs;
            $this->editMonto = $balance->monto;
        }
    }

    public function cancelEdit()
    {
        $this->editingId = null;
        $this->editKgs = 0;
        $this->editMonto = 0;
    }

    public function saveEdit()
    {
        $this->validate([
            'editKgs'   => 'required|numeric',
            'editMonto' => 'required|numeric',
        ]);

        $balance = Balance::find($this->editingId);
        if ($balance) {
            $balance->update([
                'kgs'   => $this->editKgs,
                'monto' => $this->editMonto,
            ]);

            session()->flash('success', 'Balance actualizado correctamente.');
        }

        $this->cancelEdit();
        $this->loadBalances();
    }
};
?>

<div class="p-6">
    <h1 class="text-xl font-bold mb-6">Gestión de Balances de Inventario</h1>

    <!-- Formulario de registro -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium mb-2">Compañía</label>
                <select wire:model="company_id" class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-700">
                    <option value="">-- Seleccionar --</option>
                    @foreach ($companies as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('company_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Año</label>
                <input type="number" wire:model="anio" min="2020" max="2100"
                    class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-700">
                @error('anio') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Material</label>
                <select wire:model="material" class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-700">
                    <option value="">-- Seleccionar --</option>
                    @foreach ($materials as $mat)
                        <option value="{{ $mat }}">{{ $mat }}</option>
                    @endforeach
                </select>
                @error('material') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Kilos (Kgs)</label>
                <div x-data="{ initial: @js($kgs) ?? 0 }" x-init="initKgsCleave()">
                    <input type="text" x-ref="kgsInput" placeholder="0.000"
                        class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-700 text-right font-mono">
                </div>
                @error('kgs') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Valor Monetario ($)</label>
                <div x-data="{ initial: @js($monto) ?? 0 }" x-init="initMontoCleave()">
                    <input type="text" x-ref="montoInput" placeholder="$0.00"
                        class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-700 text-right font-mono">
                </div>
                @error('monto') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="lg:col-span-3">
                <label class="block text-sm font-medium mb-2">Nota (opcional)</label>
                <input type="text" wire:model="nota" placeholder="Ej: Balance inicial, Ajuste por merma, Cierre físico..."
                    class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-700">
            </div>
        </div>

        <div class="mt-8">
            <button wire:click="openConfirmModal"
                class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 font-medium cursor-pointer">
                Agregar Balance
            </button>
        </div>
    </div>

    <!-- Mensaje -->
    @if (session('success'))
        <div class="p-4 mb-6 text-green-700 bg-green-100 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <!-- Filtros para la tabla -->
    <div class="mb-6 flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm font-medium mb-2">Compañía</label>
            <select wire:model.live="selectedCompany" class="border px-4 py-2 rounded">
                <option value="">-- Todas --</option>
                @foreach ($companies as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">Año</label>
            <select wire:model.live="selectedYear" class="border px-4 py-2 rounded">
                <option value="">-- Todos --</option>
                @foreach ($years as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Tabla -->
    <div class="overflow-x-auto">
        <table class="w-full border border-gray-400 text-sm">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="px-4 py-2 border">Fecha registro</th>
                    <th class="px-4 py-2 border">Año</th>
                    <th class="px-4 py-2 border">Material</th>
                    <th class="px-4 py-2 border text-right">Kgs</th>
                    <th class="px-4 py-2 border text-right">Valor ($)</th>
                    <th class="px-4 py-2 border">Nota</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($balanceList as $balance)
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-700">
                        <td class="border px-4 py-2 text-xs text-gray-600">
                            {{ $balance->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="border px-4 py-2 text-center font-medium">{{ $balance->anio }}</td>
                        <td class="border px-4 py-2 font-semibold">{{ $balance->material }}</td>

                        <!-- Kgs editable -->
                        <td class="border px-4 py-2 text-right font-mono">
                            @if ($editingId == $balance->id)
                                <div x-data="{ kgs: @entangle('editKgs') }" x-init="initEditKgsCleave($refs.kgsInput, kgs)" wire:ignore class="flex items-center gap-1 justify-end">
                                    <input type="text" x-ref="kgsInput" placeholder="0.000"
                                        class="border px-1 py-0.5 rounded text-xs w-24 text-right font-mono">
                                    <button wire:click="saveEdit"
                                        class="text-green-400 text-xs hover:underline cursor-pointer">Guardar</button>
                                    <button wire:click="cancelEdit"
                                        class="text-red-400 text-xs hover:underline cursor-pointer">Cancelar</button>
                                </div>
                            @else
                                {{ number_format($balance->kgs, 3) }}
                                <button wire:click="startEdit({{ $balance->id }})"
                                    class="ml-2 text-amber-400 text-xs hover:underline cursor-pointer">Editar</button>
                            @endif
                        </td>

                        <!-- Valor ($) editable - ahora con botones dentro -->
                        <td class="border px-4 py-2 text-right font-mono">
                            @if ($editingId == $balance->id)
                                <div x-data="{ monto: @entangle('editMonto') }" x-init="initEditMontoCleave($refs.montoInput, monto)" wire:ignore class="flex items-center gap-1 justify-end">
                                    <input type="text" x-ref="montoInput" placeholder="$0.00"
                                        class="border px-1 py-0.5 rounded text-xs w-24 text-right font-mono">
                                    <button wire:click="saveEdit"
                                        class="text-green-400 text-xs hover:underline cursor-pointer">Guardar</button>
                                    <button wire:click="cancelEdit"
                                        class="text-red-400 text-xs hover:underline cursor-pointer">Cancelar</button>
                                </div>
                            @else
                                ${{ number_format($balance->monto, 2) }}
                                <!-- No ponemos otro botón "Editar" aquí, ya que el de Kgs inicia la edición para ambos -->
                            @endif
                        </td>

                        <td class="border px-4 py-2 text-sm text-gray-600 italic">
                            {{ $balance->nota ?: '-' }}
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="border px-4 py-2 text-center text-gray-500 py-8">
                            No hay balances registrados con los filtros actuales.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal de confirmación -->
    <div x-data="{ open: @entangle('showConfirmModal') }" x-show="open"
        class="fixed inset-0 flex items-center justify-center z-50" x-cloak>
        <div class="absolute inset-0 bg-black opacity-70" @click="open = false"></div>

        <div x-show="open" x-transition class="relative bg-white dark:bg-gray-800 rounded-lg shadow-2xl w-full max-w-lg p-8">
            <h2 class="text-2xl font-bold mb-6">Confirmar Nuevo Balance</h2>
            <div class="space-y-3 text-gray-700 dark:text-gray-300">
                <p><strong>Compañía:</strong> {{ optional(Company::find($company_id))->name }}</p>
                <p><strong>Año:</strong> {{ $anio }}</p>
                <p><strong>Material:</strong> <span class="text-blue-600 font-bold">{{ $material }}</span></p>
                <p><strong>Kilos:</strong> <span class="font-bold {{ $kgs < 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($kgs ?: 0, 3) }} Kgs</span></p>
                <p><strong>Valor:</strong> <span class="font-bold {{ $monto < 0 ? 'text-red-600' : 'text-green-600' }}">${{ number_format($monto ?: 0, 2) }}</span></p>
                @if($nota)
                    <p class="text-sm italic mt-4"><strong>Nota:</strong> {{ $nota }}</p>
                @endif
            </div>
            <div class="flex justify-end gap-4 mt-8">
                <button @click="open = false"
                    class="px-6 py-3 bg-gray-500 text-white rounded hover:bg-gray-600 cursor-pointer">
                    Cancelar
                </button>
                <button wire:click="saveBalance"
                    class="px-6 py-3 bg-green-600 text-white rounded hover:bg-green-700 font-medium cursor-pointer">
                    Confirmar y Agregar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js"></script>
<script>
    function initKgsCleave() {
        return function() {
            const input = this.$refs.kgsInput;
            if (input.cleave) input.cleave.destroy();

            input.value = '0.000';

            input.cleave = new Cleave(input, {
                numeral: true,
                numeralThousandsGroupStyle: 'thousand',
                numeralDecimalScale: 3,
                numeralDecimalMark: '.',
                delimiter: ',',
                numeralPositiveOnly: false,
                onValueChanged: function(e) {
                    const raw = e.target.rawValue || '0';
                    let num = parseFloat(raw) || 0;
                    @this.set('kgs', num);
                    if (num === 0 && (e.target.value === '' || e.target.value === '0.000')) {
                        setTimeout(() => input.value = '0.000', 0);
                    }
                }
            });

            if (this.initial != null) input.cleave.setRawValue(this.initial);
        };
    }

    function initMontoCleave() {
        return function() {
            const input = this.$refs.montoInput;
            if (input.cleave) input.cleave.destroy();

            input.value = '$0.00';

            input.cleave = new Cleave(input, {
                numeral: true,
                numeralThousandsGroupStyle: 'thousand',
                numeralDecimalScale: 2,
                numeralDecimalMark: '.',
                delimiter: ',',
                prefix: '$',
                rawValueTrimPrefix: true,
                numeralPositiveOnly: false,
                onValueChanged: function(e) {
                    const raw = e.target.rawValue || '0';
                    let num = parseFloat(raw) || 0;
                    @this.set('monto', num);
                    if (num === 0 && (e.target.value === '' || e.target.value === '$0.00')) {
                        setTimeout(() => input.value = '$0.00', 0);
                    }
                }
            });

            if (this.initial != null) input.cleave.setRawValue(this.initial);
        };
    }

    // Edición inline Kgs
    function initEditKgsCleave(input, initialValue) {
        if (!input) return;

        if (input.cleave) input.cleave.destroy();

        const value = initialValue ?? 0;
        input.value = value !== 0 ? '' : '0.000';

        input.cleave = new Cleave(input, {
            numeral: true,
            numeralThousandsGroupStyle: 'thousand',
            numeralDecimalScale: 3,
            numeralDecimalMark: '.',
            delimiter: ',',
            numeralPositiveOnly: false,
            onValueChanged: function(e) {
                const raw = e.target.rawValue || '0';
                const num = parseFloat(raw) || 0;
                @this.set('editKgs', num);
                if (num === 0 && (e.target.value === '' || e.target.value === '0.000')) {
                    setTimeout(() => input.value = '0.000', 0);
                }
            }
        });

        if (value !== 0) {
            input.cleave.setRawValue(value);
        }
    }

    // Edición inline Monto
    function initEditMontoCleave(input, initialValue) {
        if (!input) return;

        if (input.cleave) input.cleave.destroy();

        const value = initialValue ?? 0;
        input.value = value !== 0 ? '' : '$0.00';

        input.cleave = new Cleave(input, {
            numeral: true,
            numeralThousandsGroupStyle: 'thousand',
            numeralDecimalScale: 2,
            numeralDecimalMark: '.',
            delimiter: ',',
            prefix: '$',
            rawValueTrimPrefix: true,
            numeralPositiveOnly: false,
            onValueChanged: function(e) {
                const raw = e.target.rawValue || '0';
                const num = parseFloat(raw) || 0;
                @this.set('editMonto', num);
                if (num === 0 && (e.target.value === '' || e.target.value === '$0.00')) {
                    setTimeout(() => input.value = '$0.00', 0);
                }
            }
        });

        if (value !== 0) {
            input.cleave.setRawValue(value);
        }
    }
</script>