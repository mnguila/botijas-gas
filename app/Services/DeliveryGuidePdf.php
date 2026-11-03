<?php

namespace App\Services;

use App\Models\SaleOrder;

class DeliveryGuidePdf
{
    public function render(SaleOrder $order): string
    {
        $items = $order->items->sortBy('id')->values();
        $pages = [];
        $chunks = $items->chunk(10);
        $guideNumber = str_pad((string) $items->min('id'), 6, '0', STR_PAD_LEFT);
        $totalCents = $items->sum(fn ($item) => $item->quantity * (int) round((float) $item->unit_price * 100));

        foreach ($chunks as $pageIndex => $pageItems) {
            $commands = [];
            $commands[] = '0.06 0.17 0.27 rg 0 722 595 120 re f';
            $commands[] = '0.04 0.56 0.65 rg 44 699 507 3 re f';
            $commands[] = '0.93 0.96 0.97 rg 44 500 507 43 re f';

            $this->text($commands, 'GUIA DE ENTREGA', 44, 778, 23, true, [1, 1, 1]);
            $this->text($commands, 'VENDA A GROSSO DE BOTIJAS DE GÁS', 44, 753, 10, false, [0.78, 0.9, 0.94]);
            $this->text($commands, 'N.º '.$guideNumber, 445, 779, 13, true, [1, 1, 1]);
            $this->text($commands, 'DADOS DA ENTREGA', 44, 690, 10, true, [0.04, 0.45, 0.53]);
            $this->text($commands, 'Data da venda', 44, 666, 9, false, [0.4, 0.48, 0.54]);
            $this->text($commands, $order->sold_at->format('d/m/Y H:i'), 44, 649, 12, true);
            $this->text($commands, 'Matrícula do camião', 300, 666, 9, false, [0.4, 0.48, 0.54]);
            $this->text($commands, $order->truck_plate, 300, 649, 12, true);
            $this->text($commands, 'Motorista', 44, 624, 9, false, [0.4, 0.48, 0.54]);
            $this->text($commands, $order->driver_name, 44, 607, 12, true);
            $this->text($commands, 'Registado por', 300, 624, 9, false, [0.4, 0.48, 0.54]);
            $this->text($commands, $order->createdBy?->name ?? '-', 300, 607, 12, true);
            $this->text($commands, 'Cliente', 44, 582, 9, false, [0.4, 0.48, 0.54]);
            $this->text($commands, $order->customer?->name ?? 'Cliente genérico', 44, 565, 12, true, [0.09, 0.19, 0.28], 37);
            $this->text($commands, 'NUIT', 300, 582, 9, false, [0.4, 0.48, 0.54]);
            $this->text($commands, $order->customer?->nuit ?? '-', 300, 565, 12, true);

            $this->text($commands, 'PRODUTO', 55, 518, 9, true, [0.4, 0.48, 0.54]);
            $this->text($commands, 'QTD.', 303, 518, 9, true, [0.4, 0.48, 0.54]);
            $this->text($commands, 'PREÇO UNIT.', 365, 518, 9, true, [0.4, 0.48, 0.54]);
            $this->text($commands, 'TOTAL', 475, 518, 9, true, [0.4, 0.48, 0.54]);

            foreach ($pageItems->values() as $index => $item) {
                $y = 475 - $index * 22;
                $lineCents = $item->quantity * (int) round((float) $item->unit_price * 100);
                $this->text($commands, $item->product->label, 55, $y, 10, true, [0.09, 0.19, 0.28], 35);
                $this->text($commands, number_format($item->quantity, 0, ',', '.'), 303, $y, 10);
                $this->text($commands, $this->money((float) $item->unit_price), 365, $y, 9);
                $this->text($commands, $this->money($lineCents / 100), 475, $y, 9, true);
                $commands[] = '0.89 0.92 0.93 RG 0.5 w 44 '.($y - 11).' m 551 '.($y - 11).' l S';
            }

            if ($pageIndex === $chunks->count() - 1) {
                $this->text($commands, 'TOTAL DA ENTREGA', 315, 226, 10, true, [0.4, 0.48, 0.54]);
                $this->text($commands, $this->money($totalCents / 100), 455, 226, 12, true, [0.04, 0.45, 0.53]);
                $this->text($commands, 'OBSERVAÇÕES', 44, 192, 9, true, [0.4, 0.48, 0.54]);
                foreach (mb_str_split($order->note ?: 'Sem observações.', 85) as $index => $line) {
                    $this->text($commands, $line, 44, 174 - $index * 15, 8, false, [0.09, 0.19, 0.28], 85);
                }
                $this->text($commands, 'Entregue por', 44, 112, 9, false, [0.4, 0.48, 0.54]);
                $this->text($commands, 'Recebido por', 300, 112, 9, false, [0.4, 0.48, 0.54]);
                $commands[] = '0.65 0.71 0.74 RG 0.8 w 44 77 m 255 77 l S 300 77 m 551 77 l S';
                $this->text($commands, 'Assinatura', 44, 63, 8, false, [0.48, 0.55, 0.59]);
                $this->text($commands, 'Assinatura', 300, 63, 8, false, [0.48, 0.55, 0.59]);
            } else {
                $this->text($commands, 'Continua na página seguinte', 44, 226, 9, false, [0.4, 0.48, 0.54]);
            }

            $this->text($commands, 'Guia gerada a partir da venda n.º '.$guideNumber, 44, 28, 8, false, [0.48, 0.55, 0.59]);
            $this->text($commands, 'Página '.($pageIndex + 1).' de '.$chunks->count(), 480, 28, 8, false, [0.48, 0.55, 0.59]);
            $pages[] = implode("\n", $commands)."\n";
        }

        return $this->build($pages);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' MT';
    }

    private function text(array &$commands, string $value, int $x, int $y, int $size, bool $bold = false, array $color = [0.09, 0.19, 0.28], int $maxWidth = 43): void
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
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
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
