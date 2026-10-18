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
        $hist = ($_GET['ver'] ?? '') === 'historico';
        $estados = $hist ? ['concluido', 'cancelado'] : ['abierto'];

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
                static fn(array $a): bool => $hist ? $a['estado'] === 'firmada' : in_array($a['estado'], ['pendiente', 'borrador'], true)
            ));
        }

        $comision = [];
        if (TitulacionAccess::esEncargado()) {
            $comision = RevisionRepository::rondas([
                'carreras' => TitulacionAccess::carrerasEncargado(),
                'estados' => $hist ? ['dictaminada', 'cancelada'] : ['recibida', 'en_comision', 'en_evaluacion', 'por_dictaminar'],
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
