<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Auth;
use App\Modules\Titulacion\Services\Verificacion;

/** Página pública (sin sesión) a la que lleva el QR de cada documento generado por la plataforma. */
final class VerificacionController extends TitulacionBase
{
    public function inicio(): void
    {
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['codigo'] ?? '')) ?? '');
        if ($codigo !== '') {
            redirigir('verificar/' . $codigo);
        }
        $this->mostrar(null, '');
    }

    public function ver(string $codigo): void
    {
        $this->mostrar(Verificacion::consultar($codigo), $codigo);
    }

    private function mostrar(?array $r, string $codigo): void
    {
        $this->privado();
        if ($codigo !== '' && $r === null) {
            http_response_code(404);
        }
        $this->vista('verificacion', ['titulo' => 'Verificación de documentos', 'r' => $r, 'codigo' => $codigo], Auth::usuario() ? 'app' : 'publico');
    }
}
