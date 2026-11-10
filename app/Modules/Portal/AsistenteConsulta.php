<?php
declare(strict_types=1);
namespace App\Modules\Portal;
use App\Modules\Busqueda\Buscador;
use App\Core\Audit;
use App\Core\ModuleManager;

/**
 * Flujo de la consulta de inicio (docs/ASISTENTE_JEV_GEMINI.md):
 *   pregunta → árbitro (Jev o local) → [fuera de ámbito: se rechaza] → consulta segura de registros propios
 *            → orientación de trámite (Gemini o local) → enlaces a las secciones que la persona puede abrir.
 * Ningún modelo recibe registros, esquema ni permisos; solo eligen entre opciones que el servidor ya filtró.
 */
final class AsistenteConsulta
{
    public const AVISO_FUERA = 'Esta consulta no corresponde a lo que se puede consultar en Traza FI. Pregunta por un trámite, servicio, solicitud, reunión, aviso o documento de la Facultad.';
    public const AVISO_BLOQUEO = 'No puedo atender esa petición. El asistente solo orienta sobre trámites y muestra información dentro de tu acceso; no revela configuración ni datos de otras personas.';

    /**
     * @param bool $externo  La persona aceptó usar los servicios de IA externos en esta consulta.
     * @return array{resultado:?array,enlaces:array,aviso:?string,arbitro:string,limitado:bool}
     */
    public function resolver(string $consulta, bool $externo): array
    {
        $consulta = Arbitro::sanear($consulta);
        $catalogo = ModuleManager::consulta();
        // Una sola reserva de cuota por consulta; sin cuota todo sigue funcionando con los motores locales.
        $hayExterno = $externo && (JevClient::activo() || GeminiClient::disponible());
        $conCuota = $hayExterno && GeminiClient::quota();
        $limitado = $hayExterno && !$conCuota;

        $veredicto = Arbitro::evaluar($consulta, $catalogo, $conCuota);
        $base = ['resultado' => null, 'enlaces' => [], 'aviso' => null, 'arbitro' => $veredicto['metodo'], 'limitado' => $limitado];
        if ($veredicto['sospechosa']) {
            return ['aviso' => self::AVISO_BLOQUEO] + $base;
        }
        if (!$veredicto['en_ambito']) {
            return ['aviso' => self::AVISO_FUERA] + $base;
        }
        $base['enlaces'] = $veredicto['enlaces'];

        // Registros propios: consultas fijas, con el alcance resuelto en SQL por la sesión (no por un modelo).
        $seguro = ConsultasSeguras::resolver($consulta);
        if ($seguro !== null) {
            return ['resultado' => $seguro] + $base;
        }
        // Orientación de trámite sobre el catálogo público de funciones oficiales. Never use remote embeddings here.
        $candidates = (new Buscador())->buscar($consulta, 6, true);
        if (!$candidates) {
            return $base;
        }
        $selected = $conCuota ? GeminiClient::select($consulta, $candidates) : null;
        $candidate = $candidates[$selected ?? 0];
        Audit::record('ia_orientacion', $selected === null ? 'local' : 'gemini', 'Catálogo de funciones oficiales; sin expedientes');
        return ['resultado' => ['tipo'=>'formulario','titulo'=>'Canalizar tu solicitud','explicacion'=>'La función oficial más relacionada corresponde a '.$candidate['depto'].'. Revisa el área y el formulario antes de enviar tu solicitud.',
            'depto_id'=>(int)$candidate['depto_id'],'depto'=>$candidate['depto'],'funcion'=>$candidate['descripcion'],
            'coincidencia'=>(int)round($candidate['score']*100),'asunto'=>mb_strimwidth(ucfirst($consulta),0,150,'…'),
            'metodo'=>$selected===null?'local':'gemini']] + $base;
    }
}
