<?php

namespace App\Support;

/**
 * Reads the old-style Excel (.xls) file the attendance machine's software exports. That file is a plain BIFF
 * stream where every cell is stored as text (LABEL records), so the cells can be read directly without an
 * Excel library. Numbers stored as NUMBER/RK records are read too, in case another export uses them.
 */
class XlsReader
{
    /**
     * Whether the file looks like an .xls the reader understands (a bare BIFF stream).
     */
    public static function understands(string $path): bool
    {
        $head = (string) file_get_contents($path, false, null, 0, 2);

        return strlen($head) === 2 && $head[0] === "\x09" && in_array($head[1], ["\x00", "\x02", "\x04", "\x08"], true);
    }

    /**
     * The first sheet as rows of cell text, in row then column order.
     *
     * @return list<list<string>>
     */
    public static function rows(string $path): array
    {
        $data = (string) file_get_contents($path);
        $length = strlen($data);
        $cells = [];
        $pos = 0;

        while ($pos + 4 <= $length) {
            ['id' => $id, 'size' => $size] = unpack('vid/vsize', $data, $pos);
            $body = substr($data, $pos + 4, $size);
            $pos += 4 + $size;

            if (strlen($body) < 6) {
                continue;
            }
            ['row' => $row, 'col' => $col] = unpack('vrow/vcol', $body);

            $value = match ($id) {
                0x0004 => substr($body, 8, ord($body[7] ?? "\0")),                       // BIFF2 LABEL
                0x0204 => substr($body, 8, unpack('v', $body, 6)[1]),                     // BIFF3–5 LABEL
                0x0003 => (string) (unpack('e', $body, 7)[1] ?? ''),                      // BIFF2 NUMBER
                0x0203 => (string) (unpack('e', $body, 6)[1] ?? ''),                      // NUMBER
                default => null,
            };

            if ($value !== null) {
                $cells[$row][$col] = trim(mb_convert_encoding($value, 'UTF-8', 'Windows-1252'));
            }
        }

        ksort($cells);

        return array_values(array_map(function (array $row): array {
            $out = [];
            for ($c = 0, $max = max(array_keys($row)); $c <= $max; $c++) {
                $out[] = $row[$c] ?? '';
            }

            return $out;
        }, $cells));
    }
}
