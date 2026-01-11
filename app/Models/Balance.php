<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Balance extends Model
{
    protected $fillable = [
        'company_id',
        'anio',
        'material',
        'monto',
        'kgs',
        'nota',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}