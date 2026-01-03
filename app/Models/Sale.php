<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    // Valores permitidos para el campo 'type'
    const TYPE_GENERAL = 'general';
    const TYPE_PATIO   = 'patio';

    protected $fillable = [
        'fecha',
        'material',
        'company_id',
        'user_id',
        'kgs',
        'precio_kg',
        'total',
        'type',
    ];

    protected $casts = [
        'fecha'      => 'date',
        'kgs'        => 'decimal:3',
        'precio_kg'  => 'decimal:2',
        'total'      => 'decimal:2',
        'type'       => 'string',
    ];

    // === SCOPES (para filtrar fácilmente) ===
    public function scopeGeneral($query)
    {
        return $query->where('type', self::TYPE_GENERAL);
    }

    public function scopePatio($query)
    {
        return $query->where('type', self::TYPE_PATIO);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_GENERAL => 'General',
            self::TYPE_PATIO   => 'Patio',
            default            => 'Desconocido',
        };
    }

    public function getTypeBadgeAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_GENERAL => '<span class="px-2 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded-full">General</span>',
            self::TYPE_PATIO   => '<span class="px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full">Patio</span>',
            default            => '<span class="px-2 py-1 text-xs font-medium text-gray-800 bg-gray-200 rounded-full">N/A</span>',
        };
    }

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

    public static function types(): array
    {
        return [
            self::TYPE_GENERAL => 'General',
            self::TYPE_PATIO   => 'Patio',
        ];
    }
}
?>