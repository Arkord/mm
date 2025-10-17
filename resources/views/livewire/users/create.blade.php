<?php

use App\Models\User;
use App\Models\Company;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    public $name;
    public $username;
    public $password;
    public $company_id;
    public $role;

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:6',
            'company_id' => 'required|exists:companies,id',
            'role' => 'required|string',
        ]);

        User::create([
            'name' => $this->name,
            'username' => $this->username,
            'password' => Hash::make($this->password),
            'company_id' => $this->company_id,
            'role' => $this->role,
        ]);

        session()->flash('success', 'Usuario creado exitosamente.');

        return redirect()->route('users.index');
    }
};
?>

<div>
    <h1 class="text-xl font-bold mb-4">Nuevo Usuario</h1>

    <form wire:submit.prevent="save" class="space-y-4">
        <div>
            <label class="block font-semibold">Nombre</label>
            <input type="text" wire:model="name" class="w-full border rounded px-3 py-2">
            @error('name') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block font-semibold">Usuario</label>
            <input type="text" wire:model="username" class="w-full border rounded px-3 py-2">
            @error('username') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block font-semibold">Contraseña</label>
            <input type="password" wire:model="password" class="w-full border rounded px-3 py-2">
            @error('password') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block font-semibold">Empresa</label>
            <select wire:model="company_id" class="w-full border rounded px-3 py-2">
                <option value="">-- Seleccionar empresa --</option>
                @foreach (App\Models\Company::all() as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
            @error('company_id') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block font-semibold">Rol</label>
            <select wire:model="role" class="w-full border rounded px-3 py-2">
                <option value="">-- Seleccionar rol --</option>
                <option value="admin">Admin</option>
                <option value="user">Usuario</option>
            </select>
            @error('role') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <flux:button variant="primary" type="submit" class="w-50 cursor-pointer">{{ __('Guardar') }}</flux:button>
        
    </form>
</div>