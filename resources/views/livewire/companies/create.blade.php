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
            'logo' => 'nullable|image|max:10240', // 1MB
        ]);

        $path = $this->logo ? $this->logo->store('companies', 'public') : null;

        Company::create([
            'name' => $this->name,
            'color' => $this->color,
            'logo' => $path,
        ]);

        session()->flash('success', 'Company created successfully.');
        $this->redirectRoute('companies.index');
    }
}; ?>

<div class="max-w-lg mx-auto">
    <h1 class="text-xl font-bold mb-4">New Company</h1>

    <form wire:submit.prevent="save" class="space-y-4">
        <div>
            <label class="block">Name</label>
            <input type="text" wire:model="name" class="w-full border rounded px-2 py-1">
            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block">Color</label>
            <input type="color" wire:model="color" class="w-16 h-10 border rounded">
            @error('color') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block">Logo</label>
            <input type="file" wire:model="logo" class="w-full">
            @if ($logo)
                <img src="{{ $logo->temporaryUrl() }}" class="h-16 mt-2">
            @endif
            @error('logo') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>