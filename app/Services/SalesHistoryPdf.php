<?php

namespace App\Services;

use Illuminate\Support\Collection;

class SalesHistoryPdf
{
    public function render(Collection $orders, string $periodLabel, array $summary, array $expenseSummary, bool $canViewSalesProfit = false, ?array $pointDaily = null): string
    {
        $pages = [];
        $commands = $this->pageHeader($periodLabel, $summary, $expenseSummary, $canViewSalesProfit);
        $y = 340;

        foreach ($orders as $order) {
            if ($y < 92) {
                $pages[] = $commands;
                $commands = $this->pageHeader($periodLabel, $summary, $expenseSummary, $canViewSalesProfit);
                $y = 340;
            }

            $quantity = $order->items->sum('quantity');
            $amount = $order->items->sum(fn ($item) => $item->quantity * $this->cents($item->unit_price));
            $number = str_pad((string) $order->items->min('id'), 6, '0', STR_PAD_LEFT);
            $destination = $order->channel === 'wholesale'
                ? trim(($order->truck_plate ?? '').' · '.($order->driver_name ?? ''))
                : ($order->location?->name ?? '-');

            $commands[] = sprintf('0.92 0.96 0.95 rg 40 %d 762 29 re f', $y - 17);
            $this->text($commands, '#'.$number, 48, $y, 9, true);
            $this->text($commands, $order->channel === 'wholesale' ? 'Grosso' : 'Unidade', 145, $y, 9, true);
            $this->text($commands, $order->customer?->name ?? 'Cliente genérico', 215, $y, 9, true, 29);
            $this->text($commands, $destination, 400, $y, 9, false, 29);
            $this->text($commands, (string) $quantity, 646, $y, 9, true);
            $this->text($commands, $this->money($amount), 705, $y, 9, true);
            $this->text($commands, $order->sold_at->format('d/m/Y H:i').' · Por '.$order->createdBy?->name, 48, $y - 12, 7, false, 43);
            $y -= 34;

            foreach ($order->items as $item) {
                if ($y < 60) {
                    $pages[] = $commands;
                    $commands = $this->pageHeader($periodLabel, $summary, $expenseSummary, $canViewSalesProfit);
                    $y = 340;
                    $this->text($commands, 'Continuação da venda #'.$number, 48, $y, 9, true);
                    $y -= 20;
                }
                $this->text($commands, $item->product->label, 215, $y, 8, false, 46);
                $this->text($commands, $this->money($this->cents($item->unit_price)), 558, $y, 8);
                $this->text($commands, (string) $item->quantity, 646, $y, 8);
                $this->text($commands, $this->money($item->quantity * $this->cents($item->unit_price)), 705, $y, 8);
                $y -= 15;
            }
            $y -= 7;
        }

        if ($orders->isEmpty()) {
            $this->text($commands, 'Não há vendas efetivas neste período.', 48, 340, 11);
        }
        $pages[] = $commands;
        if ($pointDaily !== null) $pages = array_merge($pages, $this->pointPages($pointDaily, $canViewSalesProfit));

        foreach ($pages as $index => &$page) {
            $this->text($page, 'Histórico geral de vendas · Gerado em '.now()->format('d/m/Y H:i'), 40, 25, 8, false, 85);
            $this->text($page, 'Página '.($index + 1).' de '.count($pages), 735, 25, 8);
        }
        unset($page);

        return $this->build(array_map(fn ($page) => implode("\n", $page)."\n", $pages));
    }

    private function pageHeader(string $periodLabel, array $summary, array $expenseSummary, bool $canViewSalesProfit): array
    {
        $commands = [
            '0.06 0.17 0.27 rg 0 505 842 90 re f',
            '0.04 0.56 0.65 rg 40 500 762 3 re f',
            '0.93 0.97 0.96 rg 40 390 762 95 re f',
            '0.91 0.94 0.94 rg 40 360 762 24 re f',
        ];
        $this->text($commands, 'HISTÓRICO GERAL DE VENDAS', 40, 558, 20, true, 70, [1, 1, 1]);
        $this->text($commands, $periodLabel, 40, 531, 12, false, 90, [0.8, 0.91, 0.94]);
        $this->text($commands, 'Vendas efetivas · A grosso e por unidade', 40, 513, 9, false, 100, [0.8, 0.91, 0.94]);

        foreach ([
            ['A GROSSO', 'wholesale', 52],
            ['POR UNIDADE', 'unit', 308],
        ] as [$label, $channel, $x]) {
            $this->text($commands, $label, $x, 465, 8, true);
            $this->text($commands, $this->money($summary[$channel]['revenue_cents']), $x, 441, 15, true);
            $this->text($commands, $summary[$channel]['orders'].' vendas · '.$summary[$channel]['quantity'].' botijas', $x, 426, 8);
            if ($canViewSalesProfit) $this->text($commands, 'Lucro: '.$this->money($summary[$channel]['margin_cents']), $x, 413, 9, true);
        }
        $totalOrders = $summary['wholesale']['orders'] + $summary['unit']['orders'];
        $totalQuantity = $summary['wholesale']['quantity'] + $summary['unit']['quantity'];
        $totalCents = $summary['wholesale']['revenue_cents'] + $summary['unit']['revenue_cents'];
        $this->text($commands, 'TOTAL', 564, 465, 8, true);
        $this->text($commands, $this->money($totalCents), 564, 441, 15, true);
        $this->text($commands, $totalOrders.' vendas · '.$totalQuantity.' botijas', 564, 426, 8);
        if ($canViewSalesProfit) {
            $totalProfitCents = $summary['wholesale']['margin_cents'] + $summary['unit']['margin_cents'];
            $this->text($commands, 'Lucro: '.$this->money($totalProfitCents), 564, 413, 9, true);
        }
        $this->text($commands, 'Despesas operacionais aprovadas: '.$this->money($expenseSummary['amount_cents']), 564, 398, 8, true, 48);

        foreach ([['N.º', 48], ['TIPO', 145], ['CLIENTE', 215], ['DESTINO / POSTO', 400], ['PREÇO UN.', 558], ['QTD.', 646], ['TOTAL', 705]] as [$label, $x]) {
            $this->text($commands, $label, $x, 368, 8, true);
        }

        return $commands;
    }

    private function pointPages(array $daily, bool $canViewSalesProfit): array
    {
        $pages = [];
        $commands = $this->pointPageHeader($daily['label'], $canViewSalesProfit);
        $y = 460;
        foreach ($daily['rows'] as $row) {
            if ($y < 85) {
                $pages[] = $commands;
                $commands = $this->pointPageHeader($daily['label'], $canViewSalesProfit);
                $y = 460;
            }
            $this->text($commands, $row['location']->name, 48, $y, 9, true, 42);
            $this->text($commands, (string) $row['orders'], 385, $y, 9);
            $this->text($commands, (string) $row['quantity'], 465, $y, 9);
            $this->text($commands, $this->money($row['revenue_cents']), 560, $y, 9);
            if ($canViewSalesProfit) $this->text($commands, $this->money($row['margin_cents']), 700, $y, 9);
            $y -= 23;
        }
        if ($y < 75) {
            $pages[] = $commands;
            $commands = $this->pointPageHeader($daily['label'], $canViewSalesProfit);
            $y = 460;
        }
        $commands[] = sprintf('0.91 0.94 0.94 rg 40 %d 762 23 re f', $y - 6);
        $this->text($commands, 'TOTAL DAS BANCAS', 48, $y, 9, true);
        $this->text($commands, (string) $daily['totals']['orders'], 385, $y, 9, true);
        $this->text($commands, (string) $daily['totals']['quantity'], 465, $y, 9, true);
        $this->text($commands, $this->money($daily['totals']['revenue_cents']), 560, $y, 9, true);
        if ($canViewSalesProfit) $this->text($commands, $this->money($daily['totals']['margin_cents']), 700, $y, 9, true);
        $pages[] = $commands;

        return $pages;
    }

    private function pointPageHeader(string $date, bool $canViewSalesProfit): array
    {
        $commands = [
            '0.06 0.17 0.27 rg 0 505 842 90 re f',
            '0.04 0.56 0.65 rg 40 500 762 3 re f',
            '0.91 0.94 0.94 rg 40 478 762 22 re f',
        ];
        $this->text($commands, 'VENDAS POR POSTO', 40, 558, 20, true, 70, [1, 1, 1]);
        $this->text($commands, 'Vendas por unidade em '.$date.' · Vendas canceladas excluídas', 40, 529, 10, false, 110, [0.8, 0.91, 0.94]);
        foreach ([['POSTO', 48], ['VENDAS', 385], ['BOTIJAS', 465], ['TOTAL VENDIDO', 560]] as [$label, $x]) {
            $this->text($commands, $label, $x, 485, 8, true);
        }
        if ($canViewSalesProfit) $this->text($commands, 'MARGEM', 700, 485, 8, true);

        return $commands;
    }

    private function cents(float|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' MT';
    }

    private function text(array &$commands, string $value, int $x, int $y, int $size, bool $bold = false, int $maxWidth = 43, array $color = [0.09, 0.19, 0.28]): void
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', mb_strimwidth($value, 0, $maxWidth, '...'));
        $escaped = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded ?: '');
        $commands[] = sprintf('%.2F %.2F %.2F rg BT /%s %d Tf %d %d Td (%s) Tj ET', $color[0], $color[1], $color[2], $bold ? 'F2' : 'F1', $size, $x, $y, $escaped);
    }

    private function build(array $pages): string
    {
        $pageIds = [];
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        foreach ($pages as $stream) {
            $pageId = count($objects) + 1;
            $contentId = $pageId + 1;
            $pageIds[] = $pageId;
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($stream).' >>' . "\nstream\n" . $stream . 'endstream';
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn ($id) => $id.' 0 R', $pageIds)).'] /Count '.count($pages).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }
        $start = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$start."\n%%EOF\n";

        return $pdf;
    }
}
