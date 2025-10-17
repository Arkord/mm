<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'fecha',
        'material',
        'company_id',
        'user_id',
        'kgs',
        'precio_kg',
        'total',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function saleBuyItems()
    {
        return $this->hasMany(SaleBuyItem::class);
    }
}
?>