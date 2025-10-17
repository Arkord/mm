<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleBuyItem extends Model
{
    protected $fillable = ['sale_id', 'buy_item_id', 'kgs'];
    
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function buyItem()
    {
        return $this->belongsTo(BuyItem::class);
    }
}
?>