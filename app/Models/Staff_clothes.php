<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff_clothes extends Model
{
    /** @use HasFactory<\Database\Factories\StaffClothesFactory> */
    use HasFactory;

    protected $fillable = ['staff_id', 'clothe_name', 'clothing_size', 'cloth_id', 'epp_id', 'status', 'color_id', 'quantity', 'condition'];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function cloth()
    {
        return $this->belongsTo(Cloth::class);
    }

    public function epp()
    {
        return $this->belongsTo(Epp::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    /**
     * Tallas válidas para las prendas de perfil (cloth_id/epp_id nulos), en el mismo
     * formato que usan los <SelectItem> de SizesTab.vue (Ficha) y StaffClothesDialog.vue
     * (Ropa/EPP) — ambos forms deben coincidir siempre con este mapa. Devuelve null para
     * prendas sin lista fija (texto libre), en cuyo caso no se restringe el valor.
     */
    public static function allowedSizesFor(?string $clotheName): ?array
    {
        if ($clotheName === null) {
            return null;
        }

        if (str_contains($clotheName, 'Pantalón')) {
            return ['S - 28', 'M - 30', 'L - 32', 'XL - 34', 'XXL - 36', 'XXXL - 38', 'XXXXL - 40'];
        }

        if (in_array($clotheName, ['Polo', 'Cafarena', 'Overall', 'Casaca', 'Chaleco', 'Chaqueta Blanca', 'Camisa/Blusa', 'Guardapolvo'], true)) {
            return ['S', 'M', 'L', 'XL', 'XXL'];
        }

        if (str_contains(strtolower($clotheName), 'guante')) {
            return ['8', '9', '10'];
        }

        if (str_contains(strtolower($clotheName), 'zapatos') || str_contains(strtolower($clotheName), 'botas')) {
            return array_map('strval', range(35, 45));
        }

        if (str_contains(strtolower($clotheName), 'lentes')) {
            return ['Lentes', 'Sobrelentes'];
        }

        return null;
    }
}
