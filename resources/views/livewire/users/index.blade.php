<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Buy;
use App\Models\Expense;
use App\Models\Sale;
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
            $hasBuys = Buy::where('user_id', $this->userToDelete->id)->exists();
            $hasExpenses = Expense::where('user_id', $this->userToDelete->id)->exists();
            $hasSales = Sale::where('user_id', $this->userToDelete->id)->exists();

            if ($hasBuys || $hasExpenses || $hasSales) {
                session()->flash('error', 'No se puede eliminar el usuario porque tiene registros relacionados (compras, gastos o ventas).');
            } else {
                // Eliminar foto si existe
                if ($this->userToDelete->photo) {
                    \Storage::disk('public')->delete($this->userToDelete->photo);
                }

                $this->userToDelete->delete();
                $this->users = User::with('company')->get();
                session()->flash('success', 'El usuario ha sido eliminado.');
            }
        }

        $this->reset(['showDeleteModal', 'userToDelete']);
    }

    public function toggleStatus($userId)
    {
        $user = User::find($userId);
        if ($user) {
            $user->toggleStatus();
            $this->users = User::with('company')->get();
            session()->flash('success', 'El estado del usuario ha sido actualizado.');
        }
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Usuarios</h1>
        <a href="{{ route('users.create') }}"
            class="bg-amber-100 hover:bg-amber-200 text-black font-medium px-4 py-2 rounded-sm transition">
            Nuevo usuario
        </a>
    </div>

    <!-- Mensajes -->
    @if (session('success'))
        <div class="p-3 mb-4 text-green-700 bg-green-100 border border-green-300 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="p-3 mb-4 text-red-700 bg-red-100 border border-red-300 rounded">
            {{ session('error') }}
        </div>
    @endif

    <!-- Tabla -->
    <div class="overflow-x-auto">
        <table class="table-auto w-full mt-4 border border-gray-500">
            <thead>
                <tr class="border-gray-500">
                    <th class="px-4 py-2">Usuario</th>
                    <th class="px-4 py-2">Email</th>
                    <th class="px-4 py-2">Teléfono</th>
                    <th class="px-4 py-2">Rol</th>
                    <th class="px-4 py-2">Empresa</th>
                    <th class="px-4 py-2">Estado</th>
                    <th class="px-4 py-2 w-64">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800">
                        <!-- Avatar + Nombre + Dirección (tooltip) -->
                        <td class="border px-4 py-2">
                            <div class="flex items-center space-x-3">
                                <img src="{{ $user->photo_url }}" alt="{{ $user->name }}"
                                    class="w-10 h-10 rounded-full object-cover shadow-sm">

                                <div>
                                    <p class="font-medium">{{ $user->name }}</p>
                                    <p class="text-xs  truncate max-w-48" title="{{ $user->address }}">
                                        {{ $user->address ?: '—' }}
                                    </p>
                                </div>
                            </div>
                        </td>

                        <!-- Email -->
                        <td class="border x-4 pl-2 py-2 text-sm">{{ $user->email }}</td>

                        <!-- Teléfono -->
                        <td class="border px-4 py-2 text-sm">{{ $user->phone ?: '—' }}</td>

                        <!-- Rol -->
                        <td class="border x-4 py-2 text-center">
                            <span
                                class="px-2 py-1 text-xs font-medium rounded-full
                                {{ $user->role === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ $user->role === 'admin' ? 'Admin' : 'Usuario' }}
                            </span>
                        </td>

                        <!-- Empresa -->
                        <td class="border x-4 pl-2 py-2 text-sm">{{ $user->company?->name ?? '—' }}</td>

                        <!-- Estado -->
                        <td class="border x-4 py-2 text-center">
                            <span
                                class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full
                                {{ $user->isActive() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $user->isActive() ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>

                        <!-- Acciones -->
                        <td class="border x-4 py-2 text-center">
                            <div class="flex justify-center space-x-1">
                                <button wire:click="toggleStatus({{ $user->id }})"
                                    class="bg-blue-500 hover:bg-blue-600 text-white text-xs px-2 py-1 rounded-sm transition cursor-pointer">
                                    {{ $user->isActive() ? 'Desactivar' : 'Activar' }}
                                </button>

                                <a href="{{ route('users.edit', $user->id) }}"
                                    class="bg-amber-500 hover:bg-amber-600 text-white text-xs px-2 py-1 rounded-sm transition">
                                    Editar
                                </a>

                                <button wire:click="confirmDelete({{ $user->id }})"
                                    class="bg-red-500 hover:bg-red-600 text-white text-xs px-2 py-1 rounded-sm transition cursor-pointer">
                                    Eliminar
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            No hay usuarios registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </tabled>

        <!-- Modal de confirmación -->
        <div x-data="{ open: @entangle('showDeleteModal') }" x-show="open" class="fixed inset-0 z-50 flex items-center justify-center" x-cloak>
            <div class="absolute inset-0 bg-black opacity-70" @click="open = false"></div>

            <div x-show="open" x-transition
                class="relative bg-gray-800 text-gray-200 rounded-lg shadow-xl w-full max-w-md p-6 mx-4">
                <h3 class="text-lg font-bold mb-3">Confirmar eliminación</h3>
                <p class="mb-5">
                    ¿Estás seguro de eliminar al usuario
                    <span class="font-semibold text-amber-400">{{ $userToDelete?->name }}</span>?
                </p>
                <div class="flex justify-end space-x-3">
                    <button @click="open = false"
                        class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded transition">
                        Cancelar
                    </button>
                    <button wire:click="deleteUser"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded transition">
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>
