<?php

use App\Models\Company;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Company $company;
    public string $name;
    public string $color;
    public $logo;

    public function mount(Company $company)
    {
        $this->company = $company;
        $this->name = $company->name;
        $this->color = $company->color ?? '#000000';
    }

    public function update()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:10240',
        ]);

        if ($this->logo) {
            $path = $this->logo->store('companies', 'public');
            $this->company->logo = $path;
        }

        $this->company->update([
            'name' => $this->name,
            'color' => $this->color,
            'logo' => $this->company->logo,
        ]);

        session()->flash('success', 'Se ha actualizado la empresa.');
        $this->redirectRoute('companies.index');
    }
}; ?>

<div class="max-w-lg mx-auto">
    <h1 class="text-xl font-bold mb-4">Editar empresa</h1>

    <form wire:submit.prevent="update" class="space-y-4" enctype="multipart/form-data">
        <div>
            <label class="block">Nombre</label>
            <input type="text" wire:model="name" class="w-full border rounded px-2 py-1">
            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block">Color</label>
            <input type="color" wire:model="color" class="w-16 h-10 border rounded" style="cursor: pointer">
            @error('color') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block">Logo</label>
            <input type="file" wire:model="logo" class="w-full border-2" style="cursor: pointer">
            @if ($logo)
                <img src="{{ $logo->temporaryUrl() }}" class="h-16 mt-2">
            @elseif ($company->logo)
                <img src="{{ asset('storage/'.$company->logo) }}" class="h-16 mt-2">
            @endif
            @error('logo') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center justify-end cursor-pointer mt-10">
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Actualizar') }}</flux:button>
        </div>
    </form>
</div>