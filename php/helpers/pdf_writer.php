<?php

/**
 * Minimal dependency-free PDF generator (Helvetica core fonts, text + lines,
 * multi-page). Coordinates use a top-left origin (y measured from the top of
 * the page) for convenience. Letter size, portrait.
 */
class SimplePDF
{
    private float $w = 612.0;   // Letter width (pt)
    private float $h = 792.0;   // Letter height (pt)
    private array $pages = [];
    private string $cur = "";

    // Helvetica glyph widths (1/1000 em) for the characters we use, for
    // right-alignment. Anything missing falls back to 0.5 em.
    private array $widths = [
        " " => 278, "!" => 278, "\"" => 355, "#" => 556, "$" => 556, "%" => 889,
        "&" => 667, "'" => 191, "(" => 333, ")" => 333, "*" => 389, "+" => 584,
        "," => 278, "-" => 333, "." => 278, "/" => 278,
        "0" => 556, "1" => 556, "2" => 556, "3" => 556, "4" => 556, "5" => 556,
        "6" => 556, "7" => 556, "8" => 556, "9" => 556, ":" => 278, ";" => 278,
        "<" => 584, "=" => 584, ">" => 584, "?" => 556, "@" => 1015,
        "A" => 667, "B" => 667, "C" => 722, "D" => 722, "E" => 667, "F" => 611,
        "G" => 778, "H" => 722, "I" => 278, "J" => 500, "K" => 667, "L" => 556,
        "M" => 833, "N" => 722, "O" => 778, "P" => 667, "Q" => 778, "R" => 722,
        "S" => 667, "T" => 611, "U" => 722, "V" => 667, "W" => 944, "X" => 667,
        "Y" => 667, "Z" => 611, "[" => 278, "\\" => 278, "]" => 278, "^" => 469,
        "_" => 556, "`" => 333,
        "a" => 556, "b" => 556, "c" => 500, "d" => 556, "e" => 556, "f" => 278,
        "g" => 556, "h" => 556, "i" => 222, "j" => 222, "k" => 500, "l" => 222,
        "m" => 833, "n" => 556, "o" => 556, "p" => 556, "q" => 556, "r" => 333,
        "s" => 500, "t" => 278, "u" => 556, "v" => 500, "w" => 722, "x" => 500,
        "y" => 500, "z" => 500, "{" => 334, "|" => 260, "}" => 334, "~" => 584,
    ];

    public function pageWidth(): float { return $this->w; }
    public function pageHeight(): float { return $this->h; }

    public function addPage(): void
    {
        if ($this->cur !== "") {
            $this->pages[] = $this->cur;
        }
        $this->cur = "";
    }

    public function textWidth(string $s, float $size): float
    {
        $total = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $total += $this->widths[$s[$i]] ?? 500;
        }
        return $total / 1000 * $size;
    }

    private function esc(string $s): string
    {
        return str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", "", ""], $s);
    }

    public function text(float $x, float $yTop, string $s, float $size = 9, bool $bold = false): void
    {
        $y = $this->h - $yTop;
        $f = $bold ? "F2" : "F1";
        $this->cur .= "BT /$f $size Tf $x $y Td (" . $this->esc($s) . ") Tj ET\n";
    }

    public function textRight(float $xRight, float $yTop, string $s, float $size = 9, bool $bold = false): void
    {
        $this->text($xRight - $this->textWidth($s, $size), $yTop, $s, $size, $bold);
    }

    public function textCenter(float $xCenter, float $yTop, string $s, float $size = 9, bool $bold = false): void
    {
        $this->text($xCenter - $this->textWidth($s, $size) / 2, $yTop, $s, $size, $bold);
    }

    public function line(float $x1, float $y1Top, float $x2, float $y2Top, float $width = 0.5): void
    {
        $y1 = $this->h - $y1Top;
        $y2 = $this->h - $y2Top;
        $this->cur .= sprintf("%.2f w %.2f %.2f m %.2f %.2f l S\n", $width, $x1, $y1, $x2, $y2);
    }

    public function rect(float $x, float $yTop, float $w, float $h, float $width = 0.5): void
    {
        $y = $this->h - $yTop - $h;
        $this->cur .= sprintf("%.2f w %.2f %.2f %.2f %.2f re S\n", $width, $x, $y, $w, $h);
    }

    public function output(): string
    {
        if ($this->cur !== "") {
            $this->pages[] = $this->cur;
            $this->cur = "";
        }
        if (empty($this->pages)) {
            $this->pages[] = "";
        }

        $objects = [];
        // 1 catalog, 2 pages, 3 F1, 4 F2
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $n = 4;
        $pageIds = [];
        foreach ($this->pages as $content) {
            $contentId = ++$n;
            $pageId = ++$n;
            $pageIds[] = $pageId;
            $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 "
                . rtrim(rtrim(number_format($this->w, 2, ".", ""), "0"), ".") . " "
                . rtrim(rtrim(number_format($this->h, 2, ".", ""), "0"), ".")
                . "] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents " . $contentId . " 0 R >>";
        }

        $kids = implode(" ", array_map(fn($id) => "$id 0 R", $pageIds));
        $objects[2] = "<< /Type /Pages /Kids [ $kids ] /Count " . count($pageIds) . " >>";

        ksort($objects);
        $maxId = max(array_keys($objects));

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        for ($i = 1; $i <= $maxId; $i++) {
            if (!isset($objects[$i])) {
                continue;
            }
            $offsets[$i] = strlen($pdf);
            $pdf .= "$i 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; $i++) {
            if (isset($offsets[$i])) {
                $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
            } else {
                $pdf .= "0000000000 65535 f \n";
            }
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n$xrefPos\n%%EOF";

        return $pdf;
    }
}
