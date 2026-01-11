<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

Route::middleware(['auth', 'role:user'])->group(function () {
    Volt::route('buys', 'buys.index')->name('buys.index');
    Volt::route('buys/create', 'buys.create')->name('buys.create');

    // Rutas de Expenses
    Volt::route('expenses', 'expenses.index')->name('expenses.index');
    Volt::route('expenses/create', 'expenses.create')->name('expenses.create');

    Volt::route('patio', 'sales.patio')->name('sales.patio');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    // Rutas de Gastos
    Volt::route('cash', 'cash.index')->name('cash.index');
    Volt::route('cash/create', 'cash.create')->name('cash.create');

    // Rutas de Companies
    Volt::route('companies', 'companies.index')->name('companies.index');
    Volt::route('companies/create', 'companies.create')->name('companies.create');
    Volt::route('companies/{company}/edit', 'companies.edit')->name('companies.edit');

    // Rutas de Users
    Volt::route('users', 'users.index')->name('users.index');
    Volt::route('users/create', 'users.create')->name('users.create');
    Volt::route('users/{user}/edit', 'users.edit')->name('users.edit');

    // Ruta de Reportes
    Volt::route('reports/financiero', 'buys.reporte')->name('buys.reporte');
    Volt::route('reports/inventario', 'buys.reporte-inventario')->name('buys.reporte-inventario');

    Volt::route('sales', 'sales.index')->name('sales.index');
    Volt::route('sales/create', 'sales.create')->name('sales.create');

    // Volt::route('patio-update', 'sales.patio-update')->name('sales.patio-update');

     // Rutas de Balances
    Volt::route('balances', 'balances.index')->name('balances.index');
});

require __DIR__ . '/auth.php';
