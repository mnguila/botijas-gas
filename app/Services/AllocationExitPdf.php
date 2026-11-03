<?php

namespace App\Services;

use App\Models\AllocationRequest;

class AllocationExitPdf
{
    public function render(AllocationRequest $request): string
    {
        $items = $request->items->sortBy('id')->values();
        $chunks = $items->chunk(14);
        $pages = [];
        foreach ($chunks as $pageIndex => $pageItems) {
            $lines = [];
            $lines[] = '0.06 0.17 0.27 rg 0 722 595 120 re f';
            $lines[] = '0.04 0.56 0.65 rg 44 699 507 3 re f';
            $this->text($lines, 'GUIA DE SAÍDA DE ESTOQUE', 44, 775, 21, true, [1, 1, 1]);
            $this->text($lines, 'TRANSPORTE DE BOTIJAS PARA BANCA', 44, 750, 10, false, [0.78, 0.9, 0.94]);
            $this->text($lines, 'N.º '.str_pad((string) $request->id, 6, '0', STR_PAD_LEFT), 450, 778, 12, true, [1, 1, 1]);
            $this->text($lines, 'DADOS DA SAÍDA', 44, 680, 10, true);
            $this->field($lines, 'Destino', $request->location?->name ?? '—', 44, 651);
            $this->field($lines, 'Data e hora da saída', $request->dispatched_at?->format('d/m/Y H:i') ?? '—', 300, 651);
            $this->field($lines, 'Matrícula do camião', $request->truck_plate ?? '—', 44, 603);
            $this->field($lines, 'Motorista', $request->driver_name ?? '—', 300, 603);
            $this->field($lines, 'Solicitado por', $request->requestedBy?->name ?? '—', 44, 555);
            $this->field($lines, 'Aprovado por', $request->approvedBy?->name ?? '—', 300, 555);
            $lines[] = '0.93 0.96 0.97 rg 44 470 507 43 re f';
            $this->text($lines, 'PRODUTO', 55, 485, 9, true);
            $this->text($lines, 'QUANTIDADE', 437, 485, 9, true);
            foreach ($pageItems->values() as $i => $item) {
                $y = 448 - $i * 20;
                $this->text($lines, $item->product?->label ?? 'Produto', 55, $y, 10, true);
                $this->text($lines, number_format($item->quantity, 0, ',', '.'), 447, $y, 10, true);
                $lines[] = '0.89 0.92 0.93 RG 0.5 w 44 '.($y - 8).' m 551 '.($y - 8).' l S';
            }
            if ($pageIndex === $chunks->count() - 1) {
                $this->text($lines, 'TOTAL DE BOTIJAS: '.number_format($items->sum('quantity'), 0, ',', '.'), 340, 133, 11, true);
                $this->text($lines, 'Entregue ao motorista', 44, 104, 9);
                $this->text($lines, 'Recebido na banca', 300, 104, 9);
                $lines[] = '0.65 0.71 0.74 RG 0.8 w 44 70 m 255 70 l S 300 70 m 551 70 l S';
                $this->text($lines, 'Assinatura', 44, 55, 8);
                $this->text($lines, 'Assinatura', 300, 55, 8);
            }
            $this->text($lines, 'Guia do pedido #'.$request->id, 44, 27, 8);
            $this->text($lines, 'Página '.($pageIndex + 1).' de '.$chunks->count(), 480, 27, 8);
            $pages[] = implode("\n", $lines)."\n";
        }
        return $this->build($pages);
    }

    private function field(array &$lines, string $label, string $value, int $x, int $y): void
    {
        $this->text($lines, $label, $x, $y, 9, false, [0.4, 0.48, 0.54]);
        $this->text($lines, $value, $x, $y - 18, 11, true);
    }

    private function text(array &$lines, string $value, int $x, int $y, int $size, bool $bold = false, array $color = [0.09, 0.19, 0.28]): void
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', mb_strimwidth($value, 0, 42, '...'));
        $escaped = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded ?: '');
        $lines[] = sprintf('%.2F %.2F %.2F rg BT /%s %d Tf %d %d Td (%s) Tj ET', ...[$color[0], $color[1], $color[2], $bold ? 'F2' : 'F1', $size, $x, $y, $escaped]);
    }

    private function build(array $pages): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $ids = [];
        foreach ($pages as $stream) {
            $pageId = count($objects) + 1;
            $ids[] = $pageId;
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.($pageId + 1).' 0 R >>';
            $objects[$pageId + 1] = '<< /Length '.strlen($stream).' >>' . "\nstream\n" . $stream . 'endstream';
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn ($id) => $id.' 0 R', $ids)).'] /Count '.count($pages).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $object) { $offsets[$number] = strlen($pdf); $pdf .= $number." 0 obj\n".$object."\nendobj\n"; }
        $start = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$start."\n%%EOF\n";
    }
}
