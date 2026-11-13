<?php
declare(strict_types=1);
namespace App\Modules\Ajustes;
use App\Core\{AccessControl,AccessSchema,Audit,Auth,Controller,Database};
final class AccesosController extends Controller
{
    private function admin(): void { if(!Auth::tieneRol('admin') || isset($_SESSION['impersonador'])) { http_response_code(403); exit('Solo la administración puede acceder.'); } }
    public function index(): void
    {
        $this->admin(); $q=mb_substr(trim((string)($_GET['q']??'')),0,120); $page=max(1,(int)($_GET['pagina']??1)); $offset=($page-1)*30;
        $filter='nombre LIKE ? OR email LIKE ?'; $params=['%'.$q.'%','%'.$q.'%'];
        $cols=['nombre'=>'nombre','email'=>'email','rol'=>'rol','estado'=>'activo']; $orden=array_key_exists((string)($_GET['orden']??''),$cols)?(string)$_GET['orden']:'nombre'; $dir=(($_GET['dir']??'asc')==='desc')?'desc':'asc';
        $this->vista('accesos',['titulo'=>'Cuentas y acceso','listo'=>AccessControl::ready(),'q'=>$q,'pagina'=>$page,'orden'=>$orden,'dir'=>$dir,'est'=>$this->estadisticas(),
            'total'=>(int)Database::valor('SELECT COUNT(*) FROM usuarios WHERE '.$filter,$params),
            'cuentas'=>Database::todas('SELECT id,nombre,email,rol,activo FROM usuarios WHERE '.$filter.' ORDER BY '.$cols[$orden].' '.strtoupper($dir).', nombre LIMIT 30 OFFSET '.$offset,$params)]);
    }
    /** Radiografía de las cuentas: totales, verificadas y desglose por programa, rol y vínculo con el directorio de profesores. */
    private function estadisticas(): array
    {
        $uno = static function (string $sql): int {
            try { return (int) Database::valor($sql); } catch (\Throwable) { return 0; }
        };
        $todas = static function (string $sql): array {
            try { return Database::todas($sql); } catch (\Throwable) { return []; }
        };
        $verif = 'EXISTS (SELECT 1 FROM cuenta_verificacion v WHERE v.usuario_id = u.id AND v.verificado_en IS NOT NULL)';
        return [
            'total' => $uno('SELECT COUNT(*) FROM usuarios'),
            'activas' => $uno('SELECT COUNT(*) FROM usuarios WHERE activo = 1'),
            'verificadas' => $uno("SELECT COUNT(*) FROM usuarios u WHERE $verif"),
            'sin_verificar' => $uno("SELECT COUNT(*) FROM usuarios u WHERE u.activo = 1 AND NOT $verif"),
            'nuevas_30' => $uno('SELECT COUNT(*) FROM usuarios WHERE creado_en >= DATE_SUB(NOW(), INTERVAL 30 DAY)'),
            'con_2fa' => $uno('SELECT COUNT(*) FROM usuarios WHERE totp_secret IS NOT NULL'),
            'profesores_enlazados' => $uno('SELECT COUNT(*) FROM profesores WHERE usuario_id IS NOT NULL'),
            'profesores_directorio' => $uno('SELECT COUNT(*) FROM profesores'),
            'declaraciones' => $uno("SELECT COUNT(*) FROM profesor_declaraciones WHERE estado = 'pendiente'"),
            'sin_programa' => $uno('SELECT COUNT(*) FROM usuarios WHERE programa_id IS NULL'),
            'por_programa' => $todas("SELECT p.siglas, p.nombre, p.nivel, COUNT(u.id) AS total, SUM(u.activo = 1) AS activas,
                    SUM($verif) AS verificadas
                  FROM programas p LEFT JOIN usuarios u ON u.programa_id = p.id GROUP BY p.id, p.siglas, p.nombre, p.nivel
                  ORDER BY FIELD(p.nivel, 'licenciatura', 'posgrado'), p.siglas"),
            'por_rol' => $todas("SELECT u.rol, COUNT(*) AS total, SUM(u.activo = 1) AS activas, SUM($verif) AS verificadas FROM usuarios u GROUP BY u.rol ORDER BY total DESC"),
            'por_dominio' => $todas("SELECT SUBSTRING_INDEX(email, '@', -1) AS dominio, COUNT(*) AS total FROM usuarios GROUP BY dominio ORDER BY total DESC LIMIT 8"),
        ];
    }
    public function activar(): void
    {
        $this->admin(); AccessSchema::install(); Audit::record('activar_autorregistro_auditoria'); flash('exito','Autorregistro, verificación y auditoría disponibles. Umbral inicial: 100 apoyos.'); redirigir('ajustes/accesos');
    }
    public function impersonar(): void
    {
        $this->admin(); if (!AccessControl::ready()) redirigir('ajustes/accesos');
        if (AccessControl::limited('impersonar:'.Auth::id(),10,900)) { flash('error','Demasiados intentos. Intenta más tarde.'); redirigir('ajustes/accesos'); }
        $hash=(string)Database::valor('SELECT password_hash FROM usuarios WHERE id=?',[Auth::id()]);
        $target=AccessControl::user((int)$this->post('usuario_id'));
        if (!$target || $target['rol']==='admin' || (int)$target['id']===Auth::id() || !password_verify((string)($_POST['clave_admin']??''),$hash)) {
            Audit::record('impersonacion_inicio','denegado'); flash('error','No se pudo iniciar. Revisa tu contraseña y elige una cuenta activa no administrativa.'); redirigir('ajustes/accesos');
        }
        $_SESSION['impersonador']=Auth::usuario(); $_SESSION['usuario']=$target; $_SESSION['impersonacion_expira']=time()+1800;
        session_regenerate_id(true); unset($_SESSION['_csrf']); AccessControl::reset();
        Audit::record('impersonacion_inicio','correcto','Duración máxima: 30 minutos'); redirigir('');
    }
    public function volver(): void
    {
        if (!isset($_SESSION['impersonador'])) redirigir('');
        $admin=AccessControl::user((int)$_SESSION['impersonador']['id']);
        if (!$admin || $admin['rol']!=='admin') { Auth::salir(); redirigir('login'); }
        Audit::record('impersonacion_fin'); $_SESSION['usuario']=$admin; unset($_SESSION['impersonador'],$_SESSION['impersonacion_expira'],$_SESSION['_csrf']); session_regenerate_id(true); AccessControl::reset(); redirigir('ajustes/accesos');
    }
    public function historial(): void
    {
        $this->admin(); if (!AccessControl::ready()) redirigir('ajustes/accesos');
        $q=mb_substr(trim((string)($_GET['q']??'')),0,120); $page=max(1,(int)($_GET['pagina']??1)); $offset=($page-1)*50;
        $where='actor_email LIKE ? OR usuario_email LIKE ? OR accion LIKE ?'; $p=['%'.$q.'%','%'.$q.'%','%'.$q.'%'];
        $this->vista('auditoria',['titulo'=>'Registro de acciones','q'=>$q,'pagina'=>$page,
            'total'=>(int)Database::valor('SELECT COUNT(*) FROM auditoria_acciones WHERE '.$where,$p),
            'eventos'=>Database::todas('SELECT * FROM auditoria_acciones WHERE '.$where.' ORDER BY id DESC LIMIT 50 OFFSET '.$offset,$p)]);
    }
}
