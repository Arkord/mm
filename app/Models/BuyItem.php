<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuyItem extends Model
{
    protected $fillable = ['buy_id', 'material', 'kgs', 'precio_kg', 'total'];

    public function buy()
    {
        return $this->belongsTo(Buy::class);
    }
}