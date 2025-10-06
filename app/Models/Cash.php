<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cash extends Model
{
    protected $fillable = [
        'fecha',
        'monto',
        'company_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}