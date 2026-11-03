<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Location extends Model
{
    protected $fillable = ['name', 'kind', 'plate', 'is_sales_point', 'is_active'];

    protected $casts = ['is_sales_point' => 'boolean', 'is_active' => 'boolean'];

    public function getDisplayNameAttribute(): string
    {
        return $this->plate ? "{$this->name} · {$this->plate}" : $this->name;
    }
}
