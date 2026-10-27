<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Core\Auth;
use App\Modules\Titulacion\Services\Constancias;
use App\Modules\Titulacion\Services\Historico;
use App\Modules\Titulacion\Services\Oficios;
use Throwable;

/** «Constancias → Textos» (redacción editable de cada constancia) e «Constancias → Historial» (registros anteriores del libro de actas). */
final class ConstanciasController extends TitulacionBase
{
    private function soloTextos(): void
    {
        if (!Constancias::puedeEditarTextos()) {
            $this->prohibido('Textos de constancias');
        }
        $this->privado();
    }

    private function soloHistorial(): void
    {
        if (!Constancias::puedeBuscar()) {
            $this->prohibido('Historial de constancias');
        }
        $this->privado();
    }

    private function tipo(string $tipo): string
    {
        if (!isset(Constancias::TIPOS[$tipo])) {
            $this->noEncontrado();
        }
        return $tipo;
    }

    /** @return array{asunto:string,intro:string,cuerpo:string,cierre:string,tabla:int} */
    private function textoDelFormulario(): array
    {
        $t = [];
        foreach (['asunto' => 80, 'intro' => 2000, 'cuerpo' => 4000, 'cierre' => 2000] as $k => $max) {
            $t[$k] = mb_substr(str_replace("\r\n", "\n", (string) ($_POST[$k] ?? '')), 0, $max);
        }
        $t['tabla'] = !empty($_POST['tabla']) ? 1 : 0;
        return $t;
    }

    public function textos(): void
    {
        $this->soloTextos();
        $textos = [];
        foreach (array_keys(Constancias::TIPOS) as $tipo) {
            $textos[$tipo] = Constancias::texto($tipo);
        }
        $this->vista('constancias_textos', ['titulo' => 'Constancias · Textos', 'textos' => $textos, 'abierto' => (string) ($_GET['tipo'] ?? '')]);
    }

    public function guardarTexto(string $tipo): void
    {
        $this->soloTextos();
        $tipo = $this->tipo($tipo);
        $t = $this->textoDelFormulario();
        if (trim($t['cuerpo']) === '' || trim($t['intro']) === '') {
            flash('error', 'La introducción y el cuerpo no pueden quedar vacíos.');
            redirigir('titulacion/constancias/textos?tipo=' . $tipo . '#' . $tipo);
        }
        if (preg_match_all('/\{[a-z_]+\}/', $t['intro'] . $t['cuerpo'] . $t['cierre'] . $t['asunto'], $m) && ($raros = array_diff(array_unique($m[0]), array_keys(Constancias::MARCADORES)))) {
            flash('error', 'Marcadores desconocidos: ' . implode(', ', $raros) . '.');
            redirigir('titulacion/constancias/textos?tipo=' . $tipo . '#' . $tipo);
        }
        Constancias::guardarTexto($tipo, $t, Auth::id());
        Audit::record('titulacion::constancia_texto', 'correcto', $tipo);
        flash('exito', 'Texto guardado. Se usará en las constancias que se generen a partir de ahora.');
        redirigir('titulacion/constancias/textos?tipo=' . $tipo . '#' . $tipo);
    }

    public function restaurarTexto(string $tipo): void
    {
        $this->soloTextos();
        $tipo = $this->tipo($tipo);
        Constancias::restaurarTexto($tipo);
        Audit::record('titulacion::constancia_texto', 'correcto', 'restaurado ' . $tipo);
        flash('exito', 'Se restauró el texto original.');
        redirigir('titulacion/constancias/textos?tipo=' . $tipo . '#' . $tipo);
    }

    /** Vista previa con datos de ejemplo del texto del formulario (sin guardarlo). */
    public function muestra(string $tipo): void
    {
        $this->soloTextos();
        $tipo = $this->tipo($tipo);
        $t = [
            'id' => 0, 'folio' => 'TIT-' . date('Y') . '-00000', 'modalidad' => 'tesis', 'tipo' => 'con_trabajo', 'alumno_id' => 0, 'creado_en' => date('Y-m-d'),
            'titulo' => 'Diseño e implementación de un sistema para la validación en línea de trámites de titulación',
            'datos' => ['datos' => ['nombre' => 'María José Hernández Peña', 'cuenta' => '1912345', 'carrera' => 'computacion', 'genero' => 'femenino'],
                'protocolo' => ['asesor' => 'Juan Pérez Gómez', 'asesor_grado' => 'Dr.']],
        ];
        $profesor = ['nombre' => 'Ana López Ruiz', 'grado' => 'Dra.', 'genero' => 'femenino', 'email' => 'ejemplo@uaemex.mx'];
        try {
            [$bytes] = Oficios::constancia($t, $tipo, $profesor, ['desde' => date('Y-m-d', strtotime('-8 months')), 'hasta' => date('Y-m-d'), 'vueltas' => '1,2', 'funcion' => 'secretario', 'acta' => '1234'], $this->textoDelFormulario());
        } catch (Throwable $e) {
            error_log('Titulación: vista previa de constancia: ' . $e->getMessage());
            $this->noEncontrado();
        }
        $this->enviarBytes($bytes, 'muestra_' . $tipo . '.pdf', 'application/pdf');
    }

    // ------------------------------------------------------------------ historial

    public function historico(): void
    {
        $this->soloHistorial();
        $f = [
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80), 'carrera' => (string) ($_GET['carrera'] ?? ''),
            'faltantes' => (string) ($_GET['faltantes'] ?? ''), 'estado' => (string) ($_GET['estado'] ?? ''),
        ];
        $pagina = max(1, (int) ($_GET['p'] ?? 1));
        $r = Historico::listar($f, 100, ($pagina - 1) * 100);
        $this->vista('constancias_historico', ['titulo' => 'Constancias · Historial', 'f' => $f, 'filas' => $r['filas'], 'total' => $r['total'], 'pagina' => $pagina]);
    }

    /** Alta manual de un registro histórico que no venía en el libro. */
    public function nuevo(): void
    {
        $this->soloHistorial();
        $acta = trim($this->post('acta'));
        try {
            $id = Historico::crear($this->post('carrera'), $this->post('alumno'), ctype_digit($acta) ? (int) $acta : null, Auth::id());
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir('titulacion/constancias/historial');
        }
        Audit::record('titulacion::historial', 'correcto', 'Registro histórico agregado ' . $id);
        flash('exito', 'Registro agregado. Completa sus datos con doble clic en cada celda.');
        redirigir('titulacion/constancias/historial?q=' . rawurlencode($this->post('alumno')));
    }

    private function json(array $d, int $codigo = 200): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($d, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Edición en línea de un campo del visor (JSON). */
    public function campo(string $id): void
    {
        $this->soloHistorial();
        try {
            $fila = Historico::guardarCampo((int) $id, (string) ($_POST['campo'] ?? ''), (string) ($_POST['valor'] ?? ''), Auth::id());
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        $this->json(['ok' => true, 'fila' => $fila]);
    }

    /** Elimina un registro sin constancias generadas (JSON). */
    public function eliminar(string $id): void
    {
        $this->soloHistorial();
        try {
            $h = Historico::eliminar((int) $id);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        Audit::record('titulacion::historial', 'correcto', 'Registro histórico eliminado ' . (int) $id . ': ' . $h['alumno'] . ' (acta ' . ($h['acta'] ?? '—') . ', ' . $h['carrera'] . ')');
        $this->json(['ok' => true]);
    }

    /** Genera las constancias de un registro completo (JSON). */
    public function generar(string $id): void
    {
        $this->soloHistorial();
        try {
            $n = Historico::generar((int) $id, Auth::id());
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            error_log('Titulación: constancias históricas: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'No se pudieron generar las constancias.'], 500);
        }
        Audit::record('titulacion::historial', 'correcto', 'Constancias históricas del registro ' . (int) $id . ': ' . $n);
        $this->json(['ok' => true, 'generadas' => $n]);
    }

    /** Busca en los trámites de la plataforma el título de los registros que no lo tienen (el definitivo antes que el del protocolo). */
    public function titulos(): void
    {
        $this->soloHistorial();
        $r = Historico::buscarTitulos();
        Audit::record('titulacion::historial', 'correcto', 'Títulos del historial desde trámites: ' . json_encode($r));
        $n = $r['revision'] + $r['protocolo'];
        flash($n > 0 ? 'exito' : 'alerta', $n > 0
            ? $n . ' títulos completados: ' . $r['revision'] . ' del trabajo definitivo (solicitud de revisión) y ' . $r['protocolo'] . ' del protocolo (márcalos con la vista en ámbar punteado y confirma que sean los definitivos). ' . $r['sin_tramite'] . ' registros no tienen trámite en la plataforma.'
            : 'No se encontró ningún título nuevo: ' . $r['sin_tramite'] . ' registros sin título no tienen un trámite en la plataforma (los de antes de la plataforma se capturan a mano o vienen del sistema anterior).');
        redirigir('titulacion/constancias/historial');
    }

    /** Genera las constancias de todos los registros completos que aún no las tienen. */
    public function generarTodas(): void
    {
        $this->soloHistorial();
        @set_time_limit(600);
        $registros = 0;
        $constancias = 0;
        foreach (\App\Core\Database::todas("SELECT * FROM tit_historico WHERE generado_en IS NULL") as $h) {
            if (!Historico::conTrabajo($h['modalidad']) || Historico::faltantes($h)) {
                continue;
            }
            try {
                $constancias += Historico::generar((int) $h['id'], Auth::id());
                $registros++;
            } catch (Throwable $e) {
                error_log('Titulación: constancias históricas del registro ' . $h['id'] . ': ' . $e->getMessage());
            }
        }
        Audit::record('titulacion::historial', 'correcto', 'Constancias históricas en lote: ' . $registros . ' registros, ' . $constancias . ' constancias');
        flash('exito', $constancias . ' constancias generadas de ' . $registros . ' registros completos; quedan en la cola de firmas.');
        redirigir('titulacion/constancias/historial');
    }
}
