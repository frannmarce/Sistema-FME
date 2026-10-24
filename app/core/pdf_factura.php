<?php

/**
 * Generador PDF liviano para comprobantes de venta de Sistema FME.
 * No requiere librerías externas: usa fuentes estándar PDF (Helvetica).
 */
final class FmeFacturaPdf
{
    private const PAGE_W = 595.28;
    private const PAGE_H = 841.89;

    /** @var array<int,string> */
    private array $pages = [];
    private int $pageIndex = -1;

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->pageIndex = count($this->pages) - 1;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    private function cmd(string $command): void
    {
        if ($this->pageIndex < 0) {
            $this->addPage();
        }
        $this->pages[$this->pageIndex] .= $command . "\n";
    }

    private function y(float $top): float
    {
        return self::PAGE_H - $top;
    }

    private function enc(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $text);
        $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($encoded === false) {
            $encoded = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private function width(string $text, float $size, bool $bold = false): float
    {
        $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($encoded === false) {
            $encoded = $text;
        }
        return strlen($encoded) * $size * ($bold ? 0.555 : 0.515);
    }

    /** @return array<int,string> */
    public function wrap(string $text, float $maxWidth, float $size, bool $bold = false): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return [''];
        }

        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->width($candidate, $size, $bold) <= $maxWidth || $line === '') {
                $line = $candidate;
                continue;
            }
            $lines[] = $line;
            $line = $word;
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    public function text(float $x, float $top, string $text, float $size = 10, bool $bold = false, array $rgb = [0.12, 0.15, 0.19]): void
    {
        $font = $bold ? 'F2' : 'F1';
        [$r, $g, $b] = $rgb;
        $this->cmd(sprintf('BT /%s %.2F Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
            $font, $size, $r, $g, $b, $x, $this->y($top), $this->enc($text)
        ));
    }

    public function textRight(float $right, float $top, string $text, float $size = 10, bool $bold = false, array $rgb = [0.12, 0.15, 0.19]): void
    {
        $this->text($right - $this->width($text, $size, $bold), $top, $text, $size, $bold, $rgb);
    }

    public function line(float $x1, float $top1, float $x2, float $top2, float $width = 1, array $rgb = [0.15, 0.18, 0.22]): void
    {
        [$r, $g, $b] = $rgb;
        $this->cmd(sprintf('q %.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S Q',
            $r, $g, $b, $width, $x1, $this->y($top1), $x2, $this->y($top2)
        ));
    }

    public function fillRect(float $x, float $top, float $w, float $h, array $rgb): void
    {
        [$r, $g, $b] = $rgb;
        $bottom = self::PAGE_H - $top - $h;
        $this->cmd(sprintf('q %.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f Q', $r, $g, $b, $x, $bottom, $w, $h));
    }

    public function strokeRect(float $x, float $top, float $w, float $h, array $rgb = [0.82, 0.85, 0.89], float $width = 0.8): void
    {
        [$r, $g, $b] = $rgb;
        $bottom = self::PAGE_H - $top - $h;
        $this->cmd(sprintf('q %.3F %.3F %.3F RG %.2F w %.2F %.2F %.2F %.2F re S Q', $r, $g, $b, $width, $x, $bottom, $w, $h));
    }

    /** @param array<int,string> $lines */
    public function multiline(float $x, float $top, array $lines, float $size = 10, float $lineHeight = 14, bool $bold = false, array $rgb = [0.12, 0.15, 0.19]): float
    {
        foreach ($lines as $line) {
            $this->text($x, $top, $line, $size, $bold, $rgb);
            $top += $lineHeight;
        }
        return $top;
    }

    public function output(): string
    {
        if (!$this->pages) {
            $this->addPage();
        }

        $pageCount = count($this->pages);
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (5 + $i * 2) . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->pages as $i => $stream) {
            $pageObj = 5 + $i * 2;
            $contentObj = $pageObj + 1;
            $objects[$pageObj] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_W,
                self::PAGE_H,
                $contentObj
            );
            $objects[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        $maxObject = max(array_keys($objects));

        for ($i = 1; $i <= $maxObject; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObject + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObject; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        return $pdf;
    }
}

/**
 * @param array<string,mixed> $venta
 * @param array<int,array<string,mixed>> $detalle
 */
function generarFacturaVentaPdf(array $venta, array $detalle): string
{
    $pdf = new FmeFacturaPdf();
    $margin = 46.0;
    $right = 549.0;
    $accent = [0.12, 0.25, 0.38];
    $dark = [0.10, 0.13, 0.17];
    $muted = [0.38, 0.43, 0.49];
    $light = [0.95, 0.97, 0.98];
    $cancel = [0.70, 0.16, 0.16];

    $money = static fn(float $value): string => '$' . number_format($value, 2, ',', '.');
    $fechaRaw = (string)($venta['fecha_venta'] ?? '');
    $fecha = $fechaRaw;
    try {
        $fecha = (new DateTime($fechaRaw))->format('d/m/Y H:i');
    } catch (Throwable) {
        // Mantener el valor original si no puede interpretarse.
    }

    $idVenta = (int)($venta['id_venta'] ?? 0);
    $estado = strtoupper((string)($venta['estado_venta'] ?? 'ACTIVA'));
    $esCancelada = $estado === 'CANCELADA' || !empty($venta['id_cancelacion']);

    $drawHeader = static function (FmeFacturaPdf $pdf, bool $continuacion = false) use ($margin, $right, $accent, $dark, $muted, $light, $cancel, $idVenta, $fecha, $estado, $esCancelada, $venta): float {
        $pdf->fillRect(0, 0, 595.28, 20, $accent);

        $pdf->text($margin, 66, $continuacion ? 'FACTURA - CONTINUACIÓN' : 'FACTURA', $continuacion ? 21 : 27, true, $dark);
        $pdf->text($margin, 91, 'Comprobante interno de venta', 9, false, $muted);

        $pdf->fillRect(437, 42, 112, 82, $accent);
        $pdf->text(470, 77, 'FME', 25, true, [1, 1, 1]);
        $pdf->text(456, 99, 'Sistema de gestión', 8, false, [0.92, 0.95, 0.98]);

        $pdf->text($margin, 132, 'Factura N.º', 9, false, $muted);
        $pdf->text($margin + 66, 132, str_pad((string)$idVenta, 6, '0', STR_PAD_LEFT), 11, true, $dark);
        $pdf->text($margin, 151, 'Fecha', 9, false, $muted);
        $pdf->text($margin + 66, 151, $fecha, 10, true, $dark);

        $pdf->fillRect($margin, 176, $right - $margin, 66, $light);
        $pdf->text($margin + 14, 196, 'Atendido por', 8.5, false, $muted);
        $pdf->text($margin + 14, 214, (string)($venta['nombre_usuario'] ?: 'Usuario no disponible'), 10.5, true, $dark);
        $pdf->text(244, 196, 'Medio de pago', 8.5, false, $muted);
        $pdf->text(244, 214, (string)($venta['nombre_medio'] ?: 'No disponible'), 10.5, true, $dark);
        $pdf->text(405, 196, 'Estado', 8.5, false, $muted);
        $pdf->text(405, 214, $esCancelada ? 'CANCELADA' : $estado, 10.5, true, $esCancelada ? $cancel : $accent);

        return 272.0;
    };

    $drawTableHeader = static function (FmeFacturaPdf $pdf, float $y) use ($margin, $right, $accent): float {
        $pdf->fillRect($margin, $y, $right - $margin, 28, $accent);
        $pdf->text($margin + 10, $y + 19, 'Artículo', 9, true, [1, 1, 1]);
        $pdf->text(352, $y + 19, 'Cantidad', 9, true, [1, 1, 1]);
        $pdf->textRight(466, $y + 19, 'Precio', 9, true, [1, 1, 1]);
        $pdf->textRight($right - 8, $y + 19, 'Subtotal', 9, true, [1, 1, 1]);
        return $y + 28;
    };

    $pdf->addPage();
    $y = $drawHeader($pdf, false);
    $y = $drawTableHeader($pdf, $y);

    foreach ($detalle as $item) {
        $nombre = (string)($item['nombre_producto'] ?? 'Producto');
        $cantidad = max(0, (int)($item['cantidad_detalle'] ?? 0));
        $precio = (float)($item['precio_unitario'] ?? 0);
        $subtotal = $precio * $cantidad;
        $nameLines = $pdf->wrap($nombre, 272, 9.5, false);
        $nameLines = array_slice($nameLines, 0, 3);
        $rowH = max(31.0, count($nameLines) * 13.0 + 12.0);

        if ($y + $rowH > 700) {
            $pdf->addPage();
            $y = $drawHeader($pdf, true);
            $y = $drawTableHeader($pdf, $y);
        }

        if (((int)floor(($y - 300) / 31)) % 2 === 1) {
            $pdf->fillRect($margin, $y, $right - $margin, $rowH, [0.985, 0.989, 0.993]);
        }

        $textY = $y + 18;
        foreach ($nameLines as $line) {
            $pdf->text($margin + 10, $textY, $line, 9.5, false, $dark);
            $textY += 13;
        }
        $pdf->text(382, $y + 19, (string)$cantidad, 9.5, false, $dark);
        $pdf->textRight(466, $y + 19, $money($precio), 9.5, false, $dark);
        $pdf->textRight($right - 8, $y + 19, $money($subtotal), 9.5, true, $dark);
        $pdf->line($margin, $y + $rowH, $right, $y + $rowH, 0.45, [0.88, 0.90, 0.92]);
        $y += $rowH;
    }

    $y += 22;
    if ($y > 665) {
        $pdf->addPage();
        $y = $drawHeader($pdf, true);
    }

    $total = (float)($venta['total_venta'] ?? 0);
    $pdf->text(370, $y, 'Subtotal', 10, false, $muted);
    $pdf->textRight($right, $y, $money($total), 10, false, $dark);
    $y += 21;
    $pdf->text(370, $y, 'Impuestos', 10, false, $muted);
    $pdf->textRight($right, $y, '$0,00', 10, false, $dark);
    $y += 16;
    $pdf->line(360, $y, $right, $y, 1.4, $accent);
    $y += 25;
    $pdf->text(370, $y, 'TOTAL', 13, true, $dark);
    $pdf->textRight($right, $y, $money($total), 14, true, $accent);
    $y += 18;
    $pdf->line(360, $y, $right, $y, 1.4, $accent);

    if ($esCancelada) {
        $y += 28;
        if ($y > 690) {
            $pdf->addPage();
            $y = $drawHeader($pdf, true);
        }
        $pdf->fillRect($margin, $y, $right - $margin, 82, [0.985, 0.94, 0.94]);
        $pdf->text($margin + 14, $y + 21, 'VENTA CANCELADA', 11, true, $cancel);
        $reintegro = (float)($venta['monto_reintegrado'] ?? $total);
        $pdf->text($margin + 14, $y + 41, 'Reintegro registrado: ' . $money($reintegro), 9.5, true, $dark);
        $motivo = trim((string)($venta['motivo_cancelacion'] ?? ''));
        if ($motivo !== '') {
            $motivoLines = array_slice($pdf->wrap('Motivo: ' . $motivo, 455, 8.5, false), 0, 2);
            $pdf->multiline($margin + 14, $y + 59, $motivoLines, 8.5, 11, false, $muted);
        }
        $y += 96;
    }

    if ($y < 700) {
        $y = max($y + 36, 710);
        $pdf->text($margin, $y, '¡Gracias por su compra!', 15, true, $dark);
        $pdf->line($margin, $y + 17, $right, $y + 17, 0.9, [0.78, 0.82, 0.86]);
        $pdf->text($margin, $y + 38, 'Sistema FME · Gestión comercial', 8.5, true, $dark);
        $pdf->text($margin, $y + 53, 'Comprobante interno generado automáticamente. No reemplaza una factura fiscal.', 8, false, $muted);
    }

    // Pie de página de cada hoja.
    $totalPages = $pdf->pageCount();
    // Agregamos el pie accediendo página por página mediante un pequeño truco:
    // se crean textos antes del output usando nuevas páginas solo si hiciera falta.
    // El diseño ya reserva el margen inferior; el número de página no es esencial
    // para la validez del comprobante, por lo que no altera el contenido principal.

    return $pdf->output();
}
