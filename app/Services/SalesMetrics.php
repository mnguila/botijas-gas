<?php

namespace App\Services;

use App\Models\ProductSale;
use App\Models\StockDamageEvent;
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
                'refund_quantity' => 0, 'refund_cents' => 0,
                'today_refund_quantity' => 0, 'today_refund_cents' => 0,
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

        $refunds = StockDamageEvent::where('kind', 'refund')
            ->whereHas('saleItem.order', fn ($orders) => $orders->whereNull('cancelled_at'))
            ->with('saleItem');
        if ($user && ! $user->is_super_admin && $user->salesLocation) {
            $point = $user->salesLocation;
            $refunds->whereHas('saleItem', fn ($items) => $point->kind === 'vehicle'
                ? $items->where('vehicle_location_id', $point->id)
                : $items->where('location_id', $point->id));
        }
        foreach ($refunds->cursor() as $event) {
            $sale = $event->saleItem;
            $channel = $sale->channel;
            $costCents = (int) round((float) $sale->unit_cost * 100);
            $metrics[$channel]['quantity'] -= $event->quantity;
            $metrics[$channel]['revenue_cents'] -= $event->refund_cents;
            $metrics[$channel]['margin_cents'] -= $event->refund_cents - $event->quantity * $costCents;
            $metrics[$channel]['refund_quantity'] += $event->quantity;
            $metrics[$channel]['refund_cents'] += $event->refund_cents;
            if ($event->created_at->isToday()) {
                $metrics[$channel]['today_quantity'] -= $event->quantity;
                $metrics[$channel]['today_revenue_cents'] -= $event->refund_cents;
                $metrics[$channel]['today_margin_cents'] -= $event->refund_cents - $event->quantity * $costCents;
                $metrics[$channel]['today_refund_quantity'] += $event->quantity;
                $metrics[$channel]['today_refund_cents'] += $event->refund_cents;
            }
        }

        return $metrics;
    }
}
