<?php

use App\Models\User;
use App\Models\Company;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    public User $user;

    public $name;
    public $username;
    public $password;
    public $company_id;
    public $role;

    public function mount(User $user)
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->company_id = $user->company_id;
        $this->role = $user->role;
    }

    public function update()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $this->user->id,
            'company_id' => 'required|exists:companies,id',
            'role' => 'required|string',
        ]);

        $this->user->update([
            'name' => $this->name,
            'username' => $this->username,
            'company_id' => $this->company_id,
            'role' => $this->role,
            'password' => $this->password ? Hash::make($this->password) : $this->user->password,
        ]);

        session()->flash('success', 'Usuario actualizado exitosamente.');

        return redirect()->route('users.index');
    }
};
?>

<div>
    <h1 class="text-xl font-bold mb-4">Editar Usuario</h1>

    <form wire:submit.prevent="update" class="space-y-4">
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
            <label class="block font-semibold">Contraseña (dejar vacío si no cambia)</label>
            <input type="password" wire:model="password" class="w-full border rounded px-3 py-2">
            @error('password') <span class="text-red-500">{{ $message }}</span> @enderror
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

        <div>
            <label class="block font-semibold">Rol</label>
            <select wire:model="role" class="w-full border rounded px-3 py-2">
                <option value="">-- Seleccionar rol --</option>
                <option value="admin">Admin</option>
                <option value="user">Usuario</option>
            </select>
            @error('role') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="bg-amber-500 text-white px-4 py-2 rounded">Actualizar</button>
    </form>
</div>