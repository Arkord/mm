<?php

use App\Models\User;
use App\Models\Company;
use Livewire\Volt\Component;

new class extends Component {
    public $users;

    public $showDeleteModal = false;
    public $userToDelete;

    public function mount()
    {
        $this->users = User::with('company')->get();
        $this->showDeleteModal = false;
    }

    public function confirmDelete($userId)
    {
        $this->userToDelete = User::find($userId);
        $this->showDeleteModal = true;
    }

    public function deleteUser()
    {
        if ($this->userToDelete) {
            $this->userToDelete->delete();
            $this->users = User::with('company')->get();
            session()->flash('success', 'El usuario ha sido eliminado.');
        }

        $this->reset(['showDeleteModal', 'userToDelete']); // cierra modal y limpia
    }
};
?>

<div>
    <h1 class="text-xl font-bold mb-4">Usuarios</h1>

    <a href="{{ route('users.create') }}" class="bg-amber-100 text-black p-2 rounded-sm">Nuevo usuario</a>

    @if (session('success'))
        <div class="p-2 mb-2 mt-2 text-green-700 bg-green-100 rounded">
            {{ session('success') }}
        </div>
    @endif

    <table class="table-auto w-full mt-4 border border-gray-500">
        <thead>
            <tr class="border-gray-500">
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Usuario</th>
                <th class="px-4 py-2">Rol</th>
                <th class="px-4 py-2">Compañía</th>
                <th class="px-4 py-2 text-center w-40">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td class="border px-4 py-2 border-gray-500">{{ $user->name }}</td>
                    <td class="border px-4 py-2 border-gray-500">{{ $user->username }}</td>
                    <td class="border px-4 py-2 border-gray-500">{{ $user->role }}</td>
                    <td class="border px-4 py-2 border-gray-500">{{ $user->company?->name }}</td>
                    <td class="border px-4 py-2 text-center w-70 border-gray-500">
                        <a href="{{ route('users.edit', $user->id) }}"
                           class="bg-amber-500 text-white rounded-sm px-3 py-1 inline-block text-sm w-25">
                            Editar
                        </a>

                        <button wire:click="confirmDelete({{ $user->id }})"
                                class="bg-red-500 text-white rounded-sm px-3 py-1 text-sm hover:bg-red-600 cursor-pointer w-25">
                            Eliminar
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Modal global -->
    <div x-data="{ open: @entangle('showDeleteModal') }"
         x-show="open"
         class="fixed inset-0 flex items-center justify-center z-50"
         x-cloak>

        <!-- Fondo -->
        <div class="absolute inset-0 bg-black" @click="open = false" style="opacity: 0.7"></div>

        <!-- Caja del modal -->
        <div x-show="open" x-transition.scale
             class="relative bg-gray-800 text-gray-400 rounded-lg shadow-lg w-96 p-6">
            <h2 class="text-lg font-bold mb-4">Confirmar eliminación</h2>
            <p class="mb-6">
                ¿Estás seguro de eliminar al usuario
                <span class="font-semibold text-red-400">{{ $userToDelete?->name }}</span>?
            </p>
            <div class="flex justify-end space-x-3">
                <button @click="open = false"
                        class="px-4 py-2 bg-gray-400 rounded text-white hover:bg-gray-500 cursor-pointer">
                    Cancelar
                </button>
                <button wire:click="deleteUser"
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 cursor-pointer">
                    Eliminar
                </button>
            </div>
        </div>
    </div>
</div>