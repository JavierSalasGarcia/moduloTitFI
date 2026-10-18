<?php
declare(strict_types=1);

namespace App\Modules\Reuniones\Services;

final class IcsReunion
{
    public function contenido(array $r): string
    {
        $inicio = gmdate('Ymd\THis\Z', strtotime($r['fecha_confirmada'] . ' ' . $r['hora_confirmada_inicio']));
        $fin = gmdate('Ymd\THis\Z', strtotime($r['fecha_confirmada'] . ' ' . $r['hora_confirmada_fin']));
        $esc = static fn (string $v): string => str_replace(["\\", ';', ',', "\n"], ["\\\\", '\\;', '\\,', '\\n'], $v);
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//PlataformaFI//Reuniones//ES\r\nBEGIN:VEVENT\r\nUID:reunion-{$r['id']}@plataforma-fi\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\nDTSTART:{$inicio}\r\nDTEND:{$fin}\r\nSUMMARY:" . $esc($r['asunto']) . "\r\nDESCRIPTION:" . $esc((string) ($r['descripcion'] ?? '')) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    }
}
