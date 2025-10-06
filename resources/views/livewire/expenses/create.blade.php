<?php

use App\Models\Expense;
use App\Models\Company;
use Livewire\Volt\Component;

new class extends Component {
    public $description;
    public $amount;
    public $date;
    public $company_id;

    public $confirmingSave = false;

    public function save()
    {
        $this->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'company_id' => 'required|exists:companies,id',
        ]);

        Expense::create([
            'description' => $this->description,
            'amount' => $this->amount,
            'date' => $this->date,
            'company_id' => $this->company_id,
        ]);

        session()->flash('success', 'Gasto registrado exitosamente.');

        return redirect()->route('expenses.index');
    }
};
?>

<div>
    <h1 class="text-xl font-bold mb-4">Nuevo Gasto</h1>

    <form wire:submit.prevent="save" class="space-y-4">
        <div>
            <label class="block font-semibold">Descripción</label>
            <input type="text" wire:model="description" class="w-full border rounded px-3 py-2">
            @error('description') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block font-semibold">Monto</label>
            <input type="number" step="0.01" wire:model="amount" class="w-full border rounded px-3 py-2">
            @error('amount') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block font-semibold">Fecha</label>
            <input type="date" wire:model="date" class="w-full border rounded px-3 py-2">
            @error('date') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block font-semibold">Compañía</label>
            <select wire:model="company_id" class="w-full border rounded px-3 py-2">
                <option value="">-- Seleccionar compañía --</option>
                @foreach (App\Models\Company::all() as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
            @error('company_id') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <flux:button 
            variant="primary" 
            type="button"
            wire:click="$set('confirmingSave', true)"
            class="w-50 cursor-pointer">
            {{ __('Guardar') }}
        </flux:button>
    </form>

    <!-- Modal de confirmación -->
    <flux:modal wire:model="confirmingSave">
        <div class="p-4">
            <h2 class="text-lg font-bold">Confirmar creación</h2>
            <p class="mt-2">¿Estás seguro de registrar este gasto?</p>

            <div class="mt-4 flex justify-end space-x-2">
                <flux:button 
                    variant="secondary" 
                    wire:click="$set('confirmingSave', false)">
                    Cancelar
                </flux:button>

                <flux:button 
                    variant="primary" 
                    wire:click="save">
                    Confirmar
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
