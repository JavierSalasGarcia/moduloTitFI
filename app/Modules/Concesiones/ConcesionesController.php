<?php
declare(strict_types=1);

namespace App\Modules\Concesiones;

use App\Core\Acceso;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Identidad;
use App\Core\ModuleManager;

/** Pantalla para conceder, revocar y vigilar accesos especiales por persona (docs/CONCESIONES_DE_ACCESO.md). */
final class ConcesionesController extends Controller
{
    /** Módulos que nunca se conceden completos: administran cuentas, credenciales o esta misma pantalla. */
    private const MODULOS_VEDADOS = ['ajustes', 'seguridad', 'auth', 'concesiones'];

    /** Dependencias sobre las que puede conceder quien tiene la sesión; null = todas. Sin facultad, se niega la pantalla. */
    private function alcance(): ?array
    {
        if (isset($_SESSION['impersonador']) || !Auth::tieneRol('admin', 'titular')) {
            http_response_code(403);
            exit('Solo la administración o los titulares de dependencia pueden gestionar accesos especiales.');
        }
        $deptos = Acceso::dependenciasQueConcede(Auth::id());
        if ($deptos !== null && $deptos === []) {
            http_response_code(403);
            exit('No tienes una dependencia a tu cargo.');
        }
        return $deptos;
    }

    /** Funciones que puede conceder: el administrador, las del catálogo y los módulos completos; el titular, solo las marcadas para titulares. */
    private function funciones(?array $deptos): array
    {
        $f = [];
        foreach (Acceso::CATALOGO as $clave => $c) {
            if ($deptos === null || $c['titular']) {
                $f[$clave] = $c['nombre'];
            }
        }
        if ($deptos === null) {
            foreach (ModuleManager::descubrir() as $m) {
                if (!in_array($m['clave'], self::MODULOS_VEDADOS, true)) {
                    $f['modulo.' . $m['clave']] = 'Módulo «' . ($m['nombre'] ?? $m['clave']) . '» completo';
                }
            }
        }
        return $f;
    }

    public function index(): void
    {
        $deptos = $this->alcance();
        $nombres = Database::todas('SELECT id, nombre FROM departamentos ORDER BY nombre');
        if ($deptos !== null) {
            $nombres = array_values(array_filter($nombres, static fn(array $d): bool => in_array((int) $d['id'], $deptos, true)));
        }
        $this->vista('index', [
            'titulo' => 'Accesos especiales',
            'concesiones' => Acceso::listar($deptos),
            'porVencer' => Acceso::porVencer(15, $deptos),
            'funciones' => $this->funciones($deptos),
            'departamentos' => $nombres,
            'esAdmin' => $deptos === null,
            'migrada' => $this->tabla(),
        ]);
    }

    public function crear(): void
    {
        $deptos = $this->alcance();
        $funciones = $this->funciones($deptos);
        $funcion = $this->post('funcion');
        $deptoId = (int) $this->post('departamento_id') ?: null;
        try {
            if (!isset($funciones[$funcion])) {
                throw new \InvalidArgumentException('Elige una función de la lista.');
            }
            if ($deptos !== null && ($deptoId === null || !in_array($deptoId, $deptos, true))) {
                throw new \DomainException('Solo puedes conceder sobre tu propia dependencia.');
            }
            $persona = Identidad::usuarioPorCorreo($this->post('correo'));
            if (!$persona) {
                throw new \InvalidArgumentException('No hay una cuenta activa con ese correo. La persona debe tener cuenta en Traza FI.');
            }
            Acceso::conceder(Auth::id(), (int) $persona['id'], $funcion, $this->post('vigente_hasta'), null, $deptoId, 'usar', $this->post('motivo'));
            flash('exito', 'Acceso concedido a ' . $persona['nombre'] . ' hasta el ' . $this->post('vigente_hasta') . '.');
        } catch (\InvalidArgumentException | \DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('concesiones');
    }

    public function revocar(string $id): void
    {
        $this->alcance();
        if (Acceso::revocar((int) $id, Auth::id())) {
            flash('exito', 'Acceso revocado.');
        } else {
            Audit::record('concesion_revocada', 'denegado', '#' . (int) $id);
            flash('error', 'No se pudo revocar: ya no está vigente o no es de tu dependencia.');
        }
        redirigir('concesiones');
    }

    private function tabla(): bool
    {
        try {
            Database::valor('SELECT 1 FROM acceso_concesiones LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
