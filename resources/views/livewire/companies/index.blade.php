<?php

use App\Models\Company;
use Livewire\Volt\Component;

new class extends Component {
    public $companies;

    public function mount()
    {
        $this->companies = Company::all();
    }

    public function deleteCompany(Company $company)
    {
        $company->delete();
        $this->companies = Company::all();
        session()->flash('success', 'Company deleted successfully.');
    }
};
 ?>

<div>
    <h1 class="text-xl font-bold mb-4">Companies</h1>

    <a href="{{ route('companies.create') }}" class="btn btn-primary">New Company</a>

    {% if session('success') %}
        <div class="p-2 mb-2 text-green-700 bg-green-100 rounded">
            {{ session('success') }}
        </div>
    {% endif %}

    <table class="table-auto w-full mt-4 border">
        <thead>
            <tr>
                <th class="px-4 py-2">Logo</th>
                <th class="px-4 py-2">Name</th>
                <th class="px-4 py-2">Color</th>
                <th class="px-4 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            {% for company in companies %}
                <tr>
                    <td class="border px-4 py-2">
                        {% if company.logo %}
                           <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo" class="h-12 w-12 object-contain">
                        {% endif %}
                    </td>
                    <td class="border px-4 py-2">{{ company.name }}</td>
                    <td class="border px-4 py-2">
                        {% if company.color %}
                            <div class="w-6 h-6 rounded" style="background-color: {{ company.color }}"></div>
                        {% endif %}
                    </td>
                    <td class="border px-4 py-2">
                        <a href="{{ route('companies.edit', company.id) }}" class="btn btn-sm btn-warning">Edit</a>

                        <button wire:click="deleteCompany({{ company.id }})"
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('Delete this company?')">
                            Delete
                        </button>
                    </td>
                </tr>
            {% endfor %}
        </tbody>
    </table>
</div>
