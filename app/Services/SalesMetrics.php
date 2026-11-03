<?php

namespace App\Services;

use App\Models\ProductSale;
use App\Models\User;

class SalesMetrics
{
    public function build(?User $user = null): array
    {
        $metrics = [];
        foreach (['wholesale', 'unit'] as $channel) {
            $metrics[$channel] = [
                'quantity' => 0, 'revenue_cents' => 0, 'margin_cents' => 0,
                'today_quantity' => 0, 'today_revenue_cents' => 0, 'today_margin_cents' => 0,
            ];
        }

        $query = ProductSale::whereNull('cancelled_at');
        if ($user && ! $user->is_super_admin && $user->salesLocation) {
            $point = $user->salesLocation;
            $point->kind === 'vehicle'
                ? $query->where('vehicle_location_id', $point->id)
                : $query->where('location_id', $point->id);
        }
        foreach ($query->cursor() as $sale) {
            $priceCents = (int) round((float) $sale->unit_price * 100);
            $costCents = (int) round((float) $sale->unit_cost * 100);
            $revenue = $sale->quantity * $priceCents;
            $margin = $sale->quantity * ($priceCents - $costCents);
            $metrics[$sale->channel]['quantity'] += $sale->quantity;
            $metrics[$sale->channel]['revenue_cents'] += $revenue;
            $metrics[$sale->channel]['margin_cents'] += $margin;
            if ($sale->sold_at->isToday()) {
                $metrics[$sale->channel]['today_quantity'] += $sale->quantity;
                $metrics[$sale->channel]['today_revenue_cents'] += $revenue;
                $metrics[$sale->channel]['today_margin_cents'] += $margin;
            }
        }

        return $metrics;
    }
}
