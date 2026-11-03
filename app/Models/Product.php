<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Product extends Model
{
    use SoftDeletes;

    protected $fillable = ['brand', 'weight_kg', 'description', 'quantity', 'purchase_price', 'reseller_price', 'unit_price'];

    protected $casts = ['quantity' => 'integer'];
    public function getLabelAttribute(): string { return "Botija {$this->weight_kg}Kg · {$this->brand}"; }

    public function isReadyForSale(): bool
    {
        return (float) $this->purchase_price > 0
            && (float) $this->reseller_price > 0
            && (float) $this->unit_price > 0;
    }
}
