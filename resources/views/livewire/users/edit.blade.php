<?php

use App\Models\User;
use App\Models\Company;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Hash;
use App\Mail\UserPasswordMail;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;
    
    public User $user;

    public $name;
    public $username;
    public $password;
    public $email;
    public $company_id;
    public $role;
    public $photo; 
    public $address;
    public $phone;

    public function mount(User $user)
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->company_id = $user->company_id;
        $this->role = $user->role;
        $this->address = $user->address;
        $this->phone = $user->phone;
    }

    public function update()
    {
        $this->validate([
            'name'       => 'required|string|max:255',
            'username'   => 'required|string|max:255|unique:users,username,' . $this->user->id,
            'password'   => 'nullable|string|min:6',
            'email'      => 'required|email|max:255|unique:users,email,' . $this->user->id,
            'company_id' => 'required|exists:companies,id',
            'role'       => 'required|in:admin,user',
            'photo'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB como en create
            'address'    => 'nullable|string|max:500',
            'phone'      => 'nullable|string|max:20',
        ]);

        $data = [
            'name'       => $this->name,
            'username'   => $this->username,
            'email'      => $this->email,
            'company_id' => $this->company_id,
            'role'       => $this->role,
            'address'    => $this->address,
            'phone'      => $this->phone,
        ];

        // Solo hashear si se ingresó contraseña
        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        // Manejar foto: si se sube nueva, reemplazar
        if ($this->photo) {
            // Opcional: eliminar foto anterior
            if ($this->user->photo) {
                \Storage::disk('public')->delete($this->user->photo);
            }
            $data['photo'] = $this->photo->store('users', 'public');
        }

        $this->user->update($data);

        // Enviar correo solo si cambió contraseña o email
        $emailChanged = $this->email !== $this->user->getOriginal('email');
        $passwordChanged = !empty($this->password);

        if ($passwordChanged || $emailChanged) {
            \Mail::to($this->email)->send(new UserPasswordMail(
                $this->user,
                $passwordChanged ? $this->password : null
            ));
        }

        session()->flash('success', 'Usuario actualizado exitosamente.');

        return redirect()->route('users.index');
    }
};
?>

<div>
    <h1 class="text-xl font-bold mb-4">Editar Usuario</h1>

    <form wire:submit.prevent="update" class="space-y-4" enctype="multipart/form-data">
        <!-- Foto actual + subida -->
        <div>
            <label class="block font-semibold mb-1">Foto de perfil</label>

            <div class="flex items-center gap-4">
                <!-- Foto actual (o avatar por defecto) -->
                <img
                    src="{{ $user->photo_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=6366f1&color=fff' }}"
                    alt="{{ $user->name }}"
                    class="w-20 h-20 rounded-full object-cover shadow"
                >

                <div class="flex-1">
                    <input type="file" wire:model="photo" accept="image/png,image/jpeg,image/gif" class="w-full border rounded px-3 py-2 text-sm cursor-pointer">
                    <p class="text-xs text-gray-500 mt-1">Deja vacío para mantener la actual</p>
                </div>
            </div>

            @error('photo')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror

            <!-- Vista previa de nueva foto -->
            @if ($photo)
                <div class="mt-3">
                    <p class="text-sm font-medium text-green-600">Vista previa:</p>
                    <img src="{{ $photo->temporaryUrl() }}" alt="Nueva foto" class="w-20 h-20 rounded-full object-cover shadow mt-1">
                </div>
            @endif
        </div>

        <!-- Nombre -->
        <div>
            <label class="block font-semibold">Nombre</label>
            <input type="text" wire:model="name" class="w-full border rounded px-3 py-2">
            @error('name') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <!-- Usuario -->
        <div>
            <label class="block font-semibold">Usuario</label>
            <input type="text" wire:model="username" class="w-full border rounded px-3 py-2">
            @error('username') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <!-- Contraseña -->
        <div>
            <label class="block font-semibold">Contraseña (dejar vacío si no cambia)</label>
            <input type="password" wire:model="password" class="w-full border rounded px-3 py-2">
            @error('password') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <!-- Email -->
        <div>
            <label class="block font-semibold">Correo electrónico</label>
            <input type="email" wire:model="email" class="w-full border rounded px-3 py-2">
            @error('email') <span class="text-red-500">{{ $message }}</span> @enderror
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

        <!-- Empresa -->
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

        <!-- Rol -->
        <div>
            <label class="block font-semibold">Rol</label>
            <select wire:model="role" class="w-full border rounded px-3 py-2">
                <option value="">-- Seleccionar rol --</option>
                <option value="admin">Administrador</option>
                <option value="user">Usuario</option>
            </select>
            @error('role') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <!-- Botón -->
        <flux:button variant="primary" type="submit" class="w-50 cursor-pointer">
            {{ __('Actualizar') }}
        </flux:button>
    </form>
</div>