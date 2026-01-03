<?php

use App\Models\Cash;
use App\Models\Company;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    public $cashList;

    // Formulario
    public $company_id = '';
    public $monto = '';
    public $fecha = '';

    // Filtros
    public $selectedYear;
    public $selectedMonth;
    public $selectedWeek;
    public $years = [];
    public $months = [];
    public $weeks = []; // ← NUEVO: semanas del mes

    // Modal
    public $showConfirmModal = false;

    public function mount()
    {
        $today = now();

        // Inicializar años y meses
        $this->years = range(2020, $today->year);
        $this->months = range(1, 12);

        // Valores iniciales
        $this->selectedYear = $today->year;
        $this->selectedMonth = $today->month;
        $this->fecha = $today->toDateString();

        // Generar semanas y seleccionar la actual
        $this->updateWeeks();
        $this->selectedWeek = $this->getCurrentWeekOfMonth($today);

        // Cargar datos iniciales
        $this->loadCash();
    }

    // Reactividad: cambiar año
    public function updatedSelectedYear()
    {
        $this->adjustWeeksAndReload();
    }

    // Reactividad: cambiar mes
    public function updatedSelectedMonth()
    {
        $this->adjustWeeksAndReload();
    }

    // Reactividad: cambiar semana
    public function updatedSelectedWeek()
    {
        $this->loadCash();
    }

    // Actualiza semanas y recarga datos
    private function adjustWeeksAndReload()
    {
        $this->updateWeeks();

        $today = now();
        if ($this->selectedYear == $today->year && $this->selectedMonth == $today->month) {
            $this->selectedWeek = $this->getCurrentWeekOfMonth($today);
        } else {
            $this->selectedWeek = array_key_first($this->weeks) ?? 1;
        }

        $this->loadCash();
    }

    // Genera las semanas del mes seleccionado
    public function updateWeeks()
    {
        $this->weeks = [];

        $firstDay = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfDay();
        $lastDay = $firstDay->copy()->endOfMonth()->endOfDay();

        // Empezar en el primer lunes del mes o después
        $currentStart = $firstDay->dayOfWeek === Carbon::MONDAY ? $firstDay->copy() : $firstDay->copy()->next(Carbon::MONDAY);

        $weekNumber = 1;

        while ($currentStart->lte($lastDay)) {
            $weekEnd = $currentStart->copy()->endOfWeek(Carbon::SUNDAY);
            $start = $currentStart->copy();
            $end = $weekEnd->copy();

            if ($end->gt($lastDay)) {
                $end = $lastDay;
            }

            $this->weeks[$weekNumber] = ['start' => $start, 'end' => $end];
            $weekNumber++;

            $currentStart = $weekEnd->copy()->addDay();
        }
    }

    // Obtiene la semana actual del mes
    public function getCurrentWeekOfMonth(Carbon $date)
    {
        foreach ($this->weeks as $num => $range) {
            if ($date->between($range['start'], $range['end'])) {
                return $num;
            }
        }
        return array_key_first($this->weeks) ?? 1;
    }

    // Carga los registros de caja
    private function loadCash()
    {
        if (!$this->selectedYear || !$this->selectedMonth || !isset($this->weeks[$this->selectedWeek])) {
            $this->cashList = collect();
            return;
        }

        $start = $this->weeks[$this->selectedWeek]['start'];
        $end = $this->weeks[$this->selectedWeek]['end'];

        $this->cashList = Cash::with('company')->whereDate('fecha', '>=', $start->toDateString())->whereDate('fecha', '<=', $end->toDateString())->orderBy('fecha', 'desc')->get();
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

        $this->loadCash();

        $this->reset(['company_id', 'monto', 'fecha', 'showConfirmModal']);
        $this->fecha = now()->toDateString();

        session()->flash('success', 'El registro de caja ha sido agregado.');
    }
};
?>

<div class="p-6">
    <h1 class="text-xl font-bold mb-4">Caja</h1>

    <!-- Formulario -->
    <div class="mb-6">
        <label class="block text-sm mb-2">Compañía</label>
        <select wire:model="company_id" class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-800 dark:text-white">
            <option value="">-- Seleccionar compañía --</option>
            @foreach (App\Models\Company::all() as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
        @error('company_id')
            <span class="text-red-500 text-sm">{{ $message }}</span>
        @enderror

        <label class="block text-sm mt-4 mb-2">Fecha</label>
        <input type="date" wire:model="fecha"
            class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-800 dark:text-white"
            max="{{ now()->toDateString() }}">
        @error('fecha')
            <span class="text-red-500 text-sm">{{ $message }}</span>
        @enderror

        <label class="block text-sm mt-4 mb-2">Monto</label>
        <div x-data="{ initial: @js($monto) ?? 0 }" x-init="initMontoCleave()">
            <input type="text" x-ref="montoInput" placeholder="$0.00"
                class="w-full p-2 border rounded bg-gray-100 dark:bg-gray-800 dark:text-white text-right font-mono text-sm">
        </div>
        @error('monto')
            <span class="text-red-500 text-sm">{{ $message }}</span>
        @enderror

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

    <!-- Filtros: Año, Mes, Semana -->
    <div class="mb-4 flex flex-wrap gap-4 items-end">
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
            <label class="block text-sm font-medium">Semana:</label>
            <select wire:model.live="selectedWeek" class="border px-2 py-1 rounded min-w-[260px]">
                @foreach ($weeks as $num => $range)
                    <option value="{{ $num }}">
                        Semana {{ $num }} ({{ $range['start']->locale('es')->isoFormat('DD MMM YYYY') }} -
                        {{ $range['end']->locale('es')->isoFormat('DD MMM YYYY') }})
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
                    <td colspan="3" class="border px-4 py-2 text-center">No hay registros de caja para esta semana.
                    </td>
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
    <div x-data="{ open: @entangle('showConfirmModal') }" x-show="open" class="fixed inset-0 flex items-center justify-center z-50" x-cloak>
        <div class="absolute inset-0 bg-black" @click="open = false" style="opacity: 0.7"></div>

        <div x-show="open" x-transition.scale class="relative bg-gray-800 text-gray-200 rounded-lg shadow-lg w-96 p-6">
            <h2 class="text-lg font-bold mb-4">Confirmar registro</h2>
            <p class="mb-6">
                ¿Registrar en caja
                <span
                    class="font-semibold text-amber-400">{{ optional(App\Models\Company::find($company_id))->name }}</span>
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

<script src="https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js"></script>

<script>
    function initMontoCleave() {
        return function() {
            const input = this.$refs.montoInput;
            let initialValue = this.initial ?? 0;

            if (!initialValue || isNaN(initialValue)) initialValue = 0;

            // Destruir instancia anterior
            if (input.cleave) input.cleave.destroy();

            // === FORZAR $0.00 VISUALMENTE ===
            input.value = '$0.00';

            // Crear Cleave
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

                    // Actualizar Livewire
                    @this.set('monto', num);

                    // === FORZAR $0.00 SI ES 0 ===
                    if (num === 0) {
                        setTimeout(() => {
                            if (input.value.trim() === '' || input.value === '$') {
                                input.value = '$0.00';
                            }
                        }, 0);
                    }
                }
            });

            // Aplicar valor inicial solo si > 0
            if (initialValue > 0) {
                input.cleave.setRawValue(initialValue);
            }
        };
    }
</script>
