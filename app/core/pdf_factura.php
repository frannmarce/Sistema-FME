<?php

/**
 * Generador PDF liviano para facturas/comprobantes de venta de Sistema FME.
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
function generarFacturaVentaPdf(array $venta, array $detalle, array $pagos = []): string
{
    $pdf = new FmeFacturaPdf();
    $margin = 44.0;
    $right = 551.0;
    $dark = [0.09, 0.11, 0.14];
    $muted = [0.38, 0.41, 0.45];
    $line = [0.72, 0.74, 0.77];
    $light = [0.965, 0.970, 0.975];
    $accent = [0.10, 0.22, 0.34];
    $refund = [0.68, 0.12, 0.12];
    $refundLight = [0.985, 0.935, 0.935];

    $money = static fn(float $value): string => '$' . number_format($value, 2, ',', '.');

    $fechaRaw = (string)($venta['fecha_venta'] ?? '');
    $fechaDia = $fechaRaw;
    $fechaHora = '';
    try {
        $fechaObj = new DateTime($fechaRaw);
        $fechaDia = $fechaObj->format('d/m/Y');
        $fechaHora = $fechaObj->format('H:i');
    } catch (Throwable) {
        // Mantener el valor original si no puede interpretarse.
    }

    $idVenta = (int)($venta['id_venta'] ?? 0);
    $estado = strtoupper((string)($venta['estado_venta'] ?? 'ACTIVA'));
    $esReintegrada = $estado === 'CANCELADA' || !empty($venta['id_cancelacion']);

    $apellido = trim((string)($venta['apellido_persona'] ?? ''));
    $nombre = trim((string)($venta['nombre_persona'] ?? ''));
    $vendedor = trim($apellido . ' ' . $nombre);
    if ($vendedor === '') {
        $vendedor = trim((string)($venta['nombre_usuario'] ?? ''));
    }
    if ($vendedor === '') {
        $vendedor = 'Usuario no disponible';
    }

    $pagosNormalizados = [];
    $totalEntregado = 0.0;
    $totalAplicado = 0.0;
    $efectivoEntregado = 0.0;
    foreach ($pagos as $pago) {
        $medio = trim((string)($pago['nombre_medio'] ?? ''));
        $monto = round((float)($pago['monto'] ?? 0), 2);
        $montoEntregado = round((float)($pago['monto_entregado'] ?? $monto), 2);
        if ($medio !== '' && ($monto > 0 || $montoEntregado > 0)) {
            $pagosNormalizados[] = [
                'nombre_medio' => $medio,
                'monto' => $monto,
                'monto_entregado' => $montoEntregado,
            ];
            $totalAplicado = round($totalAplicado + $monto, 2);
            $totalEntregado = round($totalEntregado + $montoEntregado, 2);
            if (strtolower($medio) === 'efectivo') {
                $efectivoEntregado = round($efectivoEntregado + $montoEntregado, 2);
            }
        }
    }
    $vuelto = round(max(0, $totalEntregado - $totalAplicado), 2);
    $medioResumen = count($pagosNormalizados) > 1
        ? 'Pago mixto (' . count($pagosNormalizados) . ' medios)'
        : (string)($pagosNormalizados[0]['nombre_medio'] ?? 'No disponible');

    $numeroComprobante = str_pad((string)$idVenta, 7, '0', STR_PAD_LEFT);
    $serieFactura = '0005-' . $numeroComprobante;

    $drawHeader = static function (FmeFacturaPdf $pdf, bool $continuacion = false) use (
        $margin, $right, $dark, $muted, $line, $light, $accent, $refund, $refundLight,
        $serieFactura, $fechaDia, $fechaHora, $vendedor, $venta, $esReintegrada, $medioResumen
    ): float {
        // Identidad institucional simple, inspirada en comprobantes comerciales reales.
        $pdf->text($margin, 54, 'FME', 23, true, $dark);
        $pdf->text($margin, 74, 'Sistema de Gestión y Ventas', 9, true, $accent);
        $pdf->text($margin, 89, 'Productos electrónicos', 8.5, false, $muted);
        $pdf->text($margin, 105, 'Domicilio: España 317', 8.4, false, $muted);
        $pdf->text($margin, 119, 'Localidad: FORMOSA', 8.4, false, $muted);
        $pdf->text($margin, 133, 'IVA: Responsable Inscripto', 8.4, false, $muted);
        $pdf->text($margin, 147, 'Cliente: CONSUMIDOR FINAL', 8.4, false, $muted);

        $titulo = $continuacion ? 'FACTURA B - CONT.' : 'FACTURA B';
        $pdf->textRight($right, 54, $titulo, $continuacion ? 15 : 17, true, $dark);
        $pdf->textRight($right, 77, 'Nro. ' . $serieFactura, 10, true, $dark);
        $pdf->textRight($right, 94, 'Fecha: ' . $fechaDia . ($fechaHora !== '' ? '  ' . $fechaHora : ''), 8.8, false, $muted);

        $pdf->line($margin, 166, $right, 166, 1.2, $dark);

        if ($esReintegrada) {
            $pdf->fillRect($margin, 178, $right - $margin, 29, $refundLight);
            $pdf->text($margin + 12, 197, 'FACTURA REINTEGRADA', 10.5, true, $refund);
            $top = 224.0;
        } else {
            $top = 186.0;
        }

        // Datos de la operación. No se muestra un estado genérico para ventas normales.
        $pdf->text($margin, $top, 'DATOS DE LA OPERACIÓN', 9.5, true, $dark);
        $pdf->line($margin, $top + 8, $right, $top + 8, 0.6, $line);

        $boxTop = $top + 20;
        $pdf->fillRect($margin, $boxTop, $right - $margin, 58, $light);
        $pdf->text($margin + 12, $boxTop + 19, 'Vendedor', 8, false, $muted);
        $pdf->text($margin + 12, $boxTop + 39, $vendedor, 10, true, $dark);

        $pdf->text(285, $boxTop + 19, 'Medio de pago', 8, false, $muted);
        $pdf->text(285, $boxTop + 39, $medioResumen, 9.2, true, $dark);

        $pdf->text(446, $boxTop + 19, 'Venta Nro.', 8, false, $muted);
        $pdf->text(446, $boxTop + 39, (string)((int)($venta['id_venta'] ?? 0)), 10, true, $dark);

        return $boxTop + 82;
    };

    $drawTableHeader = static function (FmeFacturaPdf $pdf, float $y) use ($margin, $right, $dark, $line): float {
        $pdf->line($margin, $y, $right, $y, 1.1, $dark);
        $pdf->text($margin + 2, $y + 18, 'Código', 8.5, true, $dark);
        $pdf->text(135, $y + 18, 'Descripción', 8.5, true, $dark);
        $pdf->textRight(405, $y + 18, 'Cantidad', 8.5, true, $dark);
        $pdf->textRight(478, $y + 18, 'P. unitario', 8.5, true, $dark);
        $pdf->textRight($right - 2, $y + 18, 'Importe', 8.5, true, $dark);
        $pdf->line($margin, $y + 27, $right, $y + 27, 0.8, $dark);
        return $y + 28;
    };

    $pdf->addPage();
    $y = $drawHeader($pdf, false);
    $y = $drawTableHeader($pdf, $y);

    foreach ($detalle as $item) {
        $codigo = trim((string)($item['codigo_barras'] ?? ''));
        if ($codigo === '') {
            $codigo = '-';
        }
        $nombreProducto = (string)($item['nombre_producto'] ?? 'Producto');
        $cantidad = max(0, (int)($item['cantidad_detalle'] ?? 0));
        $precio = (float)($item['precio_unitario'] ?? 0);
        $subtotal = $precio * $cantidad;

        $descLines = array_slice($pdf->wrap($nombreProducto, 190, 8.7, false), 0, 3);
        $rowH = max(30.0, count($descLines) * 12.0 + 12.0);

        if ($y + $rowH > 690) {
            $pdf->addPage();
            $y = $drawHeader($pdf, true);
            $y = $drawTableHeader($pdf, $y);
        }

        $pdf->text($margin + 2, $y + 18, $codigo, 8.2, false, $muted);
        $textY = $y + 18;
        foreach ($descLines as $descLine) {
            $pdf->text(135, $textY, $descLine, 8.7, false, $dark);
            $textY += 12;
        }
        $pdf->textRight(405, $y + 18, (string)$cantidad, 8.7, false, $dark);
        $pdf->textRight(478, $y + 18, $money($precio), 8.7, false, $dark);
        $pdf->textRight($right - 2, $y + 18, $money($subtotal), 8.7, true, $dark);
        $pdf->line($margin, $y + $rowH, $right, $y + $rowH, 0.35, $line);
        $y += $rowH;
    }

    $y += 20;
    if ($y > 655) {
        $pdf->addPage();
        $y = $drawHeader($pdf, true);
    }

    $total = (float)($venta['total_venta'] ?? 0);

    // Resumen económico simple: FME no simula impuestos fiscales que no registra.
    $summaryX = 353.0;
    $pdf->line($summaryX, $y, $right, $y, 0.8, $dark);
    $y += 19;
    $pdf->text($summaryX, $y, 'Subtotal', 9.5, false, $muted);
    $pdf->textRight($right, $y, $money($total), 9.5, false, $dark);
    $y += 23;
    $pdf->text($summaryX, $y, 'TOTAL', 12.5, true, $dark);
    $pdf->textRight($right, $y, $money($total), 13.5, true, $accent);
    $y += 11;
    $pdf->line($summaryX, $y, $right, $y, 1.4, $dark);

    if ($pagosNormalizados) {
        $y += 27;
        $lineasExtra = $vuelto > 0.009 ? 2 : 0;
        $altoPagos = 34.0 + ((count($pagosNormalizados) + $lineasExtra) * 20.0);
        if ($y + $altoPagos > 700) {
            $pdf->addPage();
            $y = $drawHeader($pdf, true);
        }
        $pdf->fillRect($margin, $y, $right - $margin, $altoPagos, $light);
        $pdf->text($margin + 12, $y + 20, 'DETALLE DE PAGO', 9.5, true, $dark);
        $pagoY = $y + 42;
        foreach ($pagosNormalizados as $pago) {
            $nombrePago = (string)$pago['nombre_medio'];
            $montoMostrar = (float)$pago['monto'];
            if (strtolower($nombrePago) === 'efectivo' && $vuelto > 0.009) {
                $nombrePago = 'Efectivo aplicado';
            }
            $pdf->text($margin + 12, $pagoY, $nombrePago, 9, false, $dark);
            $pdf->textRight($right - 12, $pagoY, $money($montoMostrar), 9, true, $dark);
            $pagoY += 20;
        }
        if ($vuelto > 0.009) {
            $pdf->text($margin + 12, $pagoY, 'Efectivo entregado', 9, false, $dark);
            $pdf->textRight($right - 12, $pagoY, $money($efectivoEntregado), 9, true, $dark);
            $pagoY += 20;
            $pdf->text($margin + 12, $pagoY, 'VUELTO', 9.5, true, $accent);
            $pdf->textRight($right - 12, $pagoY, $money($vuelto), 10, true, $accent);
        }
        $y += $altoPagos;
    }

    if ($esReintegrada) {
        $y += 26;
        if ($y > 675) {
            $pdf->addPage();
            $y = $drawHeader($pdf, true);
        }

        $reintegro = (float)($venta['monto_reintegrado'] ?? $total);
        $pdf->fillRect($margin, $y, $right - $margin, 88, $refundLight);
        $pdf->text($margin + 12, $y + 21, 'DETALLE DEL REINTEGRO', 9.5, true, $refund);
        $pdf->text($margin + 12, $y + 42, 'Monto reintegrado: ' . $money($reintegro), 9.2, true, $dark);

        $fechaCancelacionRaw = trim((string)($venta['fecha_cancelacion'] ?? ''));
        if ($fechaCancelacionRaw !== '') {
            try {
                $fechaCancelacionRaw = (new DateTime($fechaCancelacionRaw))->format('d/m/Y H:i');
            } catch (Throwable) {
                // Mantener valor original.
            }
            $pdf->text(300, $y + 42, 'Fecha: ' . $fechaCancelacionRaw, 8.8, false, $dark);
        }

        $motivo = trim((string)($venta['motivo_cancelacion'] ?? ''));
        if ($motivo !== '') {
            $motivoLines = array_slice($pdf->wrap('Motivo: ' . $motivo, 470, 8.3, false), 0, 2);
            $pdf->multiline($margin + 12, $y + 62, $motivoLines, 8.3, 11, false, $muted);
        }
        $y += 102;
    }

    // Pie institucional no fiscal.
    if ($y > 700) {
        $pdf->addPage();
        $y = $drawHeader($pdf, true);
    }
    $footerY = max($y + 42, 716.0);
    $pdf->line($margin, $footerY, $right, $footerY, 0.8, $line);
    $pdf->text($margin, $footerY + 24, 'Gracias por su compra.', 12.5, true, $dark);
    $pdf->text($margin, $footerY + 43, 'FME - Sistema de Gestión y Ventas de Productos Electrónicos', 8.2, true, $dark);
    $pdf->text($margin, $footerY + 58, 'Factura B de uso interno. No constituye comprobante fiscal.', 8, false, $muted);

    return $pdf->output();
}
