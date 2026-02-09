<?php

use App\Models\Company;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $name = '';
    public string $color = '#000000';
    public $logo;

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:10240',
        ]);

        $path = $this->logo ? $this->logo->store('companies', 'public') : null;

        Company::create([
            'name' => $this->name,
            'color' => $this->color,
            'logo' => $path,
        ]);

        session()->flash('success', 'Se ha creado la empresa.');
        $this->redirectRoute('companies.index');
    }
}; ?>

<div>
    <h1 class="text-xl font-bold mb-4">Nueva empresa</h1>

    <form wire:submit.prevent="save" class="space-y-4" enctype="multipart/form-data">
        <div>
            <label class="block">Nombre</label>
            <input type="text" wire:model="name" class="w-full border rounded px-2 py-1">
            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block">Logo</label>
            <input type="file" wire:model="logo" class="w-full border-2" accept="image/png,image/jpeg,image/gif" style="cursor: pointer">
            @if ($logo)
                <img src="{{ $logo->temporaryUrl() }}" class="h-16 mt-2">
            @endif
            @error('logo') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block">Esquema de color</label>
            <input type="color" wire:model="color" class="w-16 h-10" style="cursor: pointer">
            @error('color') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <flux:button variant="primary" type="submit" class="w-50 cursor-pointer">{{ __('Guardar') }}</flux:button>
        
        
    </form>
</div>