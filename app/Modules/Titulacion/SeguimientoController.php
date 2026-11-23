<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Auth;
use App\Modules\Titulacion\Services\SubdireccionFirma;

/** Vista única de seguimiento: cada actor ve, en curso o en histórico, solo los casos que le competen. */
final class SeguimientoController extends TitulacionBase
{
    public function index(): void
    {
        $this->privado();
        // El alumnado ve su trámite en «Estado de mi trámite» y lo que le toca en «Mis pendientes»; el seguimiento de casos es del personal.
        if (Auth::tieneRol('alumno') && !Auth::tieneRol('admin', 'titular', 'operativo', 'coordinador', 'profesor')) {
            redirigir('titulacion/pendientes');
        }
        $hist = ($_GET['ver'] ?? '') === 'historico';
        // Un trámite en pausa (abandonado) no está en curso: se consulta en el histórico.
        $estados = $hist ? ['concluido', 'cancelado', 'abandonado'] : ['abierto'];

        $propios = array_values(array_filter(TitulacionRepository::delAlumno(Auth::id()), static fn(array $t): bool => in_array($t['estado'], $estados, true)));

        $gestion = [];
        if (TitulacionAccess::esPersonal()) {
            $gestion = array_values(array_filter(
                TitulacionRepository::bandeja(['estado' => '']),
                static fn(array $t): bool => in_array($t['estado'], $estados, true)
            ));
        }

        $revisiones = [];
        $email = TitulacionAccess::correoSesion();
        if (TitulacionAccess::esEvaluador() && $email !== '') {
            $revisiones = array_values(array_filter(
                RevisionRepository::delEvaluador($email),
                static fn(array $a): bool => $hist ? $a['estado'] === 'firmada' : in_array($a['estado'], ['pendiente', 'borrador'], true) && $a['tramite_estado'] !== 'abandonado'
            ));
        }

        $comision = [];
        if (TitulacionAccess::esEncargado()) {
            $comision = RevisionRepository::rondas([
                'carreras' => TitulacionAccess::carrerasEncargado(),
                'estados' => $hist ? ['dictaminada', 'cancelada'] : ['recibida', 'en_comision', 'en_evaluacion', 'por_dictaminar'],
                'sin_pausa' => !$hist,
            ]);
        }

        $firmas = SubdireccionFirma::puedeFirmar() ? count(SubdireccionFirma::pendientes()) : null;

        $this->vista('seguimiento', [
            'titulo' => 'Titulación · Seguimiento',
            'historico' => $hist,
            'propios' => $propios,
            'gestion' => $gestion,
            'revisiones' => $revisiones,
            'comision' => $comision,
            'firmas' => $firmas,
            'modalidades' => Catalogo::modalidades(),
            'etapas' => null,
        ]);
    }
}
