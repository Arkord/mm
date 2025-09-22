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

Route::middleware(['auth', 'role:admin'])->group(function () {
     // Rutas de Companies
    Volt::route('companies', 'companies.index')->name('companies.index');
    Volt::route('companies/create', 'companies.create')->name('companies.create');
    Volt::route('companies/{company}/edit', 'companies.edit')->name('companies.edit');

    // Rutas de Users
    Volt::route('users', 'users.index')->name('users.index');
    Volt::route('users/create', 'users.create')->name('users.create');
    Volt::route('users/{user}/edit', 'users.edit')->name('users.edit');
});


require __DIR__.'/auth.php';
