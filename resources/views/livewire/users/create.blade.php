<?php

use App\Models\User;
use App\Models\Company;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Hash;
use App\Mail\UserPasswordMail;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;
    
    public $name;
    public $username;
    public $password;
    public $email;
    public $company_id;
    public $role;
    public $photo;
    public $address;
    public $phone;

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:6',
            'email' => 'required|email|max:255|unique:users,email',
            'company_id' => 'required|exists:companies,id',
            'role' => 'required|string',
            'photo'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'address'    => 'nullable|string|max:500',
            'phone'      => 'nullable|string|max:20',
        ]);

        $path = $this->photo ? $this->photo->store('users', 'public') : null;

        $user = User::create([
            'name' => $this->name,
            'username' => $this->username,
            'password' => Hash::make($this->password),
            'email' => $this->email,
            'company_id' => $this->company_id,
            'role' => $this->role,
            'address'    => $this->address,
            'phone'      => $this->phone,
            'photo' => $path,
        ]);

        //  Enviar el correo con la contraseña en texto plano
        Mail::to($this->email)->send(new UserPasswordMail($user, $this->password));

        session()->flash('success', 'Usuario creado exitosamente.');

        return redirect()->route('users.index');
    }
};
?>

<div>
    <h1 class="text-xl font-bold mb-4">Nuevo Usuario</h1>

    <form wire:submit.prevent="save" class="space-y-4" enctype="multipart/form-data">
        <!-- Foto -->
        <div>
            <label class="block font-semibold mb-1">Foto de perfil</label>
            <input type="file" wire:model="photo" accept="image/png,image/jpeg,image/gif" class="w-full border rounded px-3 py-2 cursor-pointer">
            @error('photo')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror

            @if ($photo)
                <div class="mt-3">
                    <img src="{{ $photo->temporaryUrl() }}" alt="Vista previa" class="w-24 h-24 rounded-full object-cover shadow">
                </div>
            @endif
        </div>

        <div>
            <label class="block font-semibold">Nombre</label>
            <input type="text" wire:model="name" class="w-full border rounded px-3 py-2">
            @error('name')
                <span class="text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label class="block font-semibold">Usuario</label>
            <input type="text" wire:model="username" class="w-full border rounded px-3 py-2">
            @error('username')
                <span class="text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label class="block font-semibold">Contraseña</label>
            <input type="password" wire:model="password" class="w-full border rounded px-3 py-2">
            @error('password')
                <span class="text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label class="block font-semibold">Correo electrónico</label>
            <input type="email" wire:model="email" class="w-full border rounded px-3 py-2">
            @error('email')
                <span class="text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <!-- Dirección -->
        <div>
            <label class="block font-semibold mb-1">Dirección</label>
            <textarea wire:model="address" class="w-full border rounded px-3 py-2" rows="2" placeholder="Calle 123, Ciudad"></textarea>
            @error('address') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <!-- Teléfono -->
        <div>
            <label class="block font-semibold mb-1">Teléfono</label>
            <input type="text" wire:model="phone" class="w-full border rounded px-3 py-2" placeholder="+58 412 1234567">
            @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block font-semibold">Empresa</label>
            <select wire:model="company_id" class="w-full border rounded px-3 py-2">
                <option value="">-- Seleccionar empresa --</option>
                @foreach (App\Models\Company::all() as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
            @error('company_id')
                <span class="text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <div>
            <label class="block font-semibold">Rol</label>
            <select wire:model="role" class="w-full border rounded px-3 py-2">
                <option value="">-- Seleccionar rol --</option>
                <option value="admin">Admin</option>
                <option value="user">Usuario</option>
            </select>
            @error('role')
                <span class="text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <flux:button variant="primary" type="submit" class="w-50 cursor-pointer">{{ __('Guardar') }}</flux:button>

    </form>
</div>
