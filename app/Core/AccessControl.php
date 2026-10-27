<?php
declare(strict_types=1);
namespace App\Core;

final class AccessControl
{
    private static int $internal = 0;
    private static ?bool $ready = null;
    private static array $pending = [];
    public static function internal(callable $fn): mixed { self::$internal++; try { return $fn(); } finally { self::$internal--; } }
    public static function isInternal(): bool { return self::$internal > 0; }
    public static function reset(): void { self::$ready=null; self::$pending=[]; }
    public static function isUaemexEmail(string $email): bool
    {
        return Auth::normalizarCorreoInstitucional($email) !== null;
    }
    public static function ready(): bool
    {
        return self::$ready ??= (int) Database::valor("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('cuenta_verificacion','auditoria_acciones','acceso_limites')") === 3;
    }
    public static function pending(): bool
    {
        $id=Auth::id(); if (!$id || !self::ready()) return false;
        return self::$pending[$id] ??= (bool) Database::valor('SELECT COUNT(*) FROM cuenta_verificacion WHERE usuario_id=? AND verificado_en IS NULL',[$id]);
    }
    /**
     * Exige correo verificado para participar (subir obras, votar, crear encuestas). Hoy el Router ya impide todo POST a una cuenta
     * pendiente; esta guardia lo hace explícito en cada acción y explica a la persona qué debe hacer.
     */
    public static function exigirCorreoVerificado(string $accion): void
    {
        if (!self::pending()) return;
        Audit::record('participacion_bloqueada','denegado',$accion);
        flash('alerta','Verifica tu correo para '.$accion.'. Te enviamos un código de 6 dígitos; ingrésalo aquí.');
        redirigir('cuenta/verificar');
    }
    public static function assertSql(string $sql): void
    {
        if (self::isInternal()) return;
        if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i',$sql) && !preg_match('/\b(INTO\s+(OUTFILE|DUMPFILE)|FOR\s+UPDATE)\b/i',$sql)) return;
        if (!self::pending()) return;
        // Deny by default: includes INSERT, UPDATE, DELETE, REPLACE, CALL and CTEs.
        if (!preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i',$sql) || preg_match('/\b(INTO\s+(OUTFILE|DUMPFILE)|FOR\s+UPDATE)\b/i',$sql)) self::deny();
    }
    public static function deny(): never
    {
        Audit::record('escritura_bloqueada','denegado');
        http_response_code(403);
        if (str_contains($_SERVER['HTTP_ACCEPT']??'','application/json') || isset($_SERVER['HTTP_X_FI_UPLOAD'])) {
            header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'Verifica tu correo para habilitar la escritura.','verificar'=>url('cuenta/verificar')]);
        } else { View::render('errores/solo_lectura',['titulo'=>'Verifica tu correo'],'app'); }
        exit;
    }
    public static function user(int $id): ?array
    {
        $u=Database::una('SELECT u.*, d.nombre AS departamento,d.clave AS depto_clave,a.nombre AS area,p.siglas AS programa_siglas,p.nombre AS programa,p.nivel AS programa_nivel FROM usuarios u LEFT JOIN departamentos d ON d.id=u.departamento_id LEFT JOIN areas a ON a.id=d.area_id LEFT JOIN programas p ON p.id=u.programa_id WHERE u.id=? AND u.activo=1',[$id]);
        if ($u) {
            $u['tiene_totp']=!empty($u['totp_secret']); unset($u['password_hash'],$u['totp_secret']);
            [$u['roles_extra'],$u['programas_profesor']]=self::rolesAdicionales($id);
        }
        return $u;
    }
    /** Roles adicionales (usuario_roles) y programas de profesorado; si las tablas aún no existen, la cuenta solo tiene su rol principal. */
    private static function rolesAdicionales(int $id): array
    {
        try {
            $extra=Database::todas('SELECT r.rol,r.departamento_id,r.programa_id,r.cargo,d.clave AS depto_clave,d.nombre AS departamento FROM usuario_roles r LEFT JOIN departamentos d ON d.id=r.departamento_id WHERE r.usuario_id=? ORDER BY r.id',[$id]);
            $prog=array_map('intval',array_column(Database::todas("SELECT programa_id FROM programa_profesores WHERE usuario_id=? AND activo=1 AND (fecha_fin IS NULL OR fecha_fin>=CURDATE())",[$id]),'programa_id'));
            return [$extra,$prog];
        } catch (\Throwable) { return [[],[]]; }
    }
    public static function refresh(): void
    {
        if (!Auth::id()) return;
        if (isset($_SESSION['impersonador'])) {
            $actor=self::user((int)$_SESSION['impersonador']['id']);
            if (!$actor || $actor['rol']!=='admin') { Auth::salir(); redirigir('login'); }
            $_SESSION['impersonador']=$actor;
            if (time() >= (int)($_SESSION['impersonacion_expira']??0)) {
                Audit::record('impersonacion_fin','correcto','Tiempo agotado');
                $_SESSION['usuario']=$actor; unset($_SESSION['impersonador'],$_SESSION['impersonacion_expira'],$_SESSION['_csrf']); session_regenerate_id(true); redirigir('ajustes/accesos');
            }
        }
        $u=self::user(Auth::id());
        if (!$u) { Auth::salir(); redirigir('login'); }
        $_SESSION['usuario']=$u;
    }
    public static function limited(string $purpose,int $limit,int $seconds): bool
    {
        $key=hash('sha256',$purpose); $window=(int)floor(time()/$seconds);
        return self::internal(function() use($key,$window,$limit): bool {
            Database::ejecutar('INSERT INTO acceso_limites (clave,ventana,cantidad) VALUES (?,?,1) ON DUPLICATE KEY UPDATE cantidad=IF(ventana=VALUES(ventana),cantidad+1,1),ventana=VALUES(ventana)',[$key,$window]);
            return (int)Database::valor('SELECT cantidad FROM acceso_limites WHERE clave=?',[$key]) > $limit;
        });
    }
}
