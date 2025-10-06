<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'fecha',
        'buy_id',
        'buy_item_id',
        'material',
        'company_id',
        'user_id',
        'kgs',
        'precio_kg',
        'total',
    ];

    // Relaciones
    public function buy()
    {
        return $this->belongsTo(Buy::class);
    }

    public function buyItem()
    {
        return $this->belongsTo(BuyItem::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}