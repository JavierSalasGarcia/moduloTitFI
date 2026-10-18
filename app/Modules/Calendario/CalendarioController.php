<?php
declare(strict_types=1);

namespace App\Modules\Calendario;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Calendario personal dentro del sitio: muestra por mes los eventos
 * que el usuario agregó desde «Avisos y eventos».
 */
final class CalendarioController extends Controller
{
    public function index(): void
    {
        $mes = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['m'] ?? '')) ? $_GET['m'] : date('Y-m');
        [$anio, $numMes] = array_map('intval', explode('-', $mes));
        $primerDia = sprintf('%04d-%02d-01', $anio, $numMes);
        $diasEnMes = (int) date('t', strtotime($primerDia));

        // Obtener el enlace ICS vinculado del usuario
        $u = Database::una('SELECT outlook_ics_url FROM usuarios WHERE id = ?', [Auth::id()]);
        $outlookIcsUrl = $u['outlook_ics_url'] ?? '';

        // 1. Obtener eventos internos de suscripciones
        $eventos = Database::todas(
            'SELECT ev.*, d.nombre AS depto
               FROM evento_suscripciones s
               JOIN eventos ev ON ev.id = s.evento_id
               JOIN departamentos d ON d.id = ev.depto_id
              WHERE s.usuario_id = ? AND ev.inicio BETWEEN ? AND ?
              ORDER BY ev.inicio',
            [Auth::id(), "{$primerDia} 00:00:00", sprintf('%04d-%02d-%02d 23:59:59', $anio, $numMes, $diasEnMes)]
        );

        // 2. Obtener eventos cacheados de Outlook
        $eventosOutlook = Database::todas(
            'SELECT * FROM usuario_eventos_outlook 
              WHERE usuario_id = ? AND inicio BETWEEN ? AND ?
              ORDER BY inicio',
            [Auth::id(), "{$primerDia} 00:00:00", sprintf('%04d-%02d-%02d 23:59:59', $anio, $numMes, $diasEnMes)]
        );

        // 3. Obtener reuniones confirmadas de este usuario
        $reunionesConfirmadas = Database::todas(
            'SELECT r.id, r.asunto, r.descripcion, r.fecha_confirmada, r.hora_confirmada_inicio, r.hora_confirmada_fin
               FROM reuniones r
               JOIN reuniones_participantes rp ON rp.reunion_id = r.id AND rp.usuario_id = ?
              WHERE r.estado IN (\'fecha_confirmada\', \'finalizada\')
                AND r.fecha_confirmada BETWEEN ? AND ?
              ORDER BY r.fecha_confirmada, r.hora_confirmada_inicio',
            [Auth::id(), $primerDia, sprintf('%04d-%02d-%02d', $anio, $numMes, $diasEnMes)]
        );

        $porDia = [];
        $agendaMes = [];

        // Guardamos los títulos internos para este mes indexados por día para desduplicar
        $titulosInternosPorDia = [];

        foreach ($reunionesConfirmadas as $r) {
            $item = [
                'id' => $r['id'],
                'titulo' => '👥 [Reunión] ' . $r['asunto'],
                'inicio' => $r['fecha_confirmada'] . ' ' . $r['hora_confirmada_inicio'],
                'lugar' => 'Plataforma FI',
                'depto' => 'Reuniones',
                'tipo' => 'interno',
                'descripcion' => $r['descripcion']
            ];
            $diaNum = (int) date('j', strtotime($r['fecha_confirmada']));
            $porDia[$diaNum][] = $item;
            $agendaMes[] = $item;
            $titulosInternosPorDia[$diaNum][] = trim(mb_strtolower($r['asunto']));
        }

        foreach ($eventos as $ev) {
            $item = [
                'id' => $ev['id'],
                'titulo' => $ev['titulo'],
                'inicio' => $ev['inicio'],
                'lugar' => $ev['lugar'],
                'depto' => $ev['depto'],
                'tipo' => 'interno'
            ];
            $diaNum = (int) date('j', strtotime($ev['inicio']));
            $porDia[$diaNum][] = $item;
            $agendaMes[] = $item;

            $titulosInternosPorDia[$diaNum][] = trim(mb_strtolower($ev['titulo']));
        }

        foreach ($eventosOutlook as $ev) {
            $diaNum = (int) date('j', strtotime($ev['inicio']));
            $tituloOutlook = trim(mb_strtolower($ev['titulo']));
            
            // Si ya existe un evento interno en este mismo día con título coincidente, omitir duplicado
            if (isset($titulosInternosPorDia[$diaNum])) {
                $duplicado = false;
                foreach ($titulosInternosPorDia[$diaNum] as $tituloInterno) {
                    if ($tituloOutlook === $tituloInterno || 
                        str_contains($tituloOutlook, $tituloInterno) || 
                        str_contains($tituloInterno, $tituloOutlook)) {
                        $duplicado = true;
                        break;
                    }
                }
                if ($duplicado) {
                    continue; // Omitir duplicado importado
                }
            }

            $item = [
                'id' => null,
                'titulo' => '👥 [Teams] ' . $ev['titulo'],
                'inicio' => $ev['inicio'],
                'lugar' => $ev['lugar'] ?? 'Microsoft Teams',
                'depto' => 'Microsoft Outlook',
                'tipo' => 'outlook',
                'descripcion' => $ev['descripcion']
            ];
            $porDia[$diaNum][] = $item;
            $agendaMes[] = $item;
        }

        // Ordenar la agenda combinada por fecha/hora de inicio
        usort($agendaMes, function($a, $b) {
            return strcmp($a['inicio'], $b['inicio']);
        });

        $this->vista('index', [
            'titulo'        => 'Mi Calendario',
            'mes'           => $mes,
            'anio'          => $anio,
            'numMes'        => $numMes,
            'diasEnMes'     => $diasEnMes,
            'offset'        => ((int) date('N', strtotime($primerDia))) - 1,
            'porDia'        => $porDia,
            'eventos'       => $agendaMes,
            'outlookIcsUrl' => $outlookIcsUrl,
            'mesAnterior'   => date('Y-m', strtotime($primerDia . ' -1 month')),
            'mesSiguiente'  => date('Y-m', strtotime($primerDia . ' +1 month')),
        ]);
    }

    public function vincularIcs(): void
    {
        header('Content-Type: application/json');
        $url = trim($_POST['outlook_ics_url'] ?? '');

        if ($url === '') {
            Database::ejecutar('UPDATE usuarios SET outlook_ics_url = NULL WHERE id = ?', [Auth::id()]);
            Database::ejecutar('DELETE FROM usuario_eventos_outlook WHERE usuario_id = ?', [Auth::id()]);
            echo json_encode(['exito' => true, 'mensaje' => 'Calendario de Outlook desvinculado con éxito.']);
            exit;
        }

        // Validar formato básico de URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['exito' => false, 'error' => 'La URL ingresada no es válida.']);
            exit;
        }

        try {
            Database::ejecutar('UPDATE usuarios SET outlook_ics_url = ? WHERE id = ?', [$url, Auth::id()]);
            
            // Forzar primera sincronización en caliente
            $res = $this->ejecutarSincronizacion(Auth::id(), $url);
            
            if ($res['exito']) {
                echo json_encode(['exito' => true, 'mensaje' => '¡Calendario vinculado y sincronizado exitosamente!']);
            } else {
                echo json_encode(['exito' => true, 'mensaje' => 'Calendario guardado, pero falló la sincronización inicial: ' . $res['error']]);
            }
        } catch (\Exception $e) {
            echo json_encode(['exito' => false, 'error' => 'Error al guardar el enlace: ' . $e->getMessage()]);
        }
        exit;
    }

    public function sincronizarIcs(): void
    {
        header('Content-Type: application/json');
        $u = Database::una('SELECT outlook_ics_url FROM usuarios WHERE id = ?', [Auth::id()]);
        $url = $u['outlook_ics_url'] ?? '';

        if (!$url) {
            echo json_encode(['exito' => false, 'error' => 'No tienes ningún calendario vinculado.']);
            exit;
        }

        $res = $this->ejecutarSincronizacion(Auth::id(), $url);
        echo json_encode($res);
        exit;
    }

    private function ejecutarSincronizacion(int $usuarioId, string $url): array
    {
        // Descargar contenido del archivo ICS
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $icsContent = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200 || !$icsContent) {
            return ['exito' => false, 'error' => 'No se pudo descargar el archivo de calendario de Outlook. Valida que el enlace sea correcto y público.'];
        }

        $events = $this->parseIcs($icsContent);

        if (empty($events)) {
            return ['exito' => false, 'error' => 'No se encontraron eventos válidos en el archivo del calendario.'];
        }

        try {
            // Limpiar cache anterior
            Database::ejecutar('DELETE FROM usuario_eventos_outlook WHERE usuario_id = ?', [$usuarioId]);

            // Insertar nuevos eventos
            foreach ($events as $ev) {
                Database::ejecutar(
                    'INSERT INTO usuario_eventos_outlook (usuario_id, uid, titulo, inicio, fin, descripcion, lugar) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), inicio = VALUES(inicio), fin = VALUES(fin), descripcion = VALUES(descripcion), lugar = VALUES(lugar)',
                    [
                        $usuarioId,
                        $ev['uid'],
                        mb_strimwidth($ev['summary'], 0, 250, ''),
                        $ev['dtstart'],
                        $ev['dtend'] ?? $ev['dtstart'],
                        $ev['description'] ?? null,
                        $ev['location'] ?? null
                    ]
                );
            }

            return ['exito' => true, 'mensaje' => 'Sincronización completada. Se importaron ' . count($events) . ' eventos.'];
        } catch (\Exception $e) {
            return ['exito' => false, 'error' => 'Error al guardar los eventos en la base de datos: ' . $e->getMessage()];
        }
    }

    private function parseIcs(string $icsContent): array
    {
        $events = []; $lines = explode("\n", preg_replace("/\n[ \t]/", "", str_replace(["\r\n", "\r"], "\n", $icsContent))); $curr = null;
        foreach ($lines as $line) {
            $line = trim($line); if ($line === '') continue;
            if ($line === 'BEGIN:VEVENT') { $curr = []; continue; }
            if ($line === 'END:VEVENT') { if ($curr && isset($curr['uid'], $curr['dtstart'], $curr['summary'])) $events[] = $curr; $curr = null; continue; }
            if ($curr !== null && ($pos = strpos($line, ':')) !== false) {
                $k = explode(';', substr($line, 0, $pos))[0]; $v = substr($line, $pos + 1);
                if ($k === 'UID') $curr['uid'] = $v;
                elseif ($k === 'SUMMARY') $curr['summary'] = str_replace(['\\,', '\\;'], [',', ';'], $v);
                elseif ($k === 'DESCRIPTION') $curr['description'] = str_replace(['\\,', '\\;', '\\n'], [',', ';', "\n"], $v);
                elseif ($k === 'LOCATION') $curr['location'] = str_replace(['\\,', '\\;'], [',', ';'], $v);
                elseif ($k === 'DTSTART') $curr['dtstart'] = $this->parseIcsDateTime($v);
                elseif ($k === 'DTEND') $curr['dtend'] = $this->parseIcsDateTime($v);
            }
        }
        return $events;
    }

    private function parseIcsDateTime(string $val): string
    {
        $val = trim($val);
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})/', $val, $m)) {
            $dt = "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}";
            if (str_ends_with($val, 'Z')) {
                $date = new \DateTime($dt, new \DateTimeZone('UTC'));
                $date->setTimezone(new \DateTimeZone('America/Mexico_City'));
                return $date->format('Y-m-d H:i:s');
            }
            return $dt;
        }
        return preg_match('/^(\d{4})(\d{2})(\d{2})/', $val, $m) ? "{$m[1]}-{$m[2]}-{$m[3]} 00:00:00" : $val;
    }
}
