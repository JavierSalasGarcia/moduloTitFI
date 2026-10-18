<?php
declare(strict_types=1);

namespace App\Modules\Organizacion;

use App\Core\Controller;
use App\Core\Database;
use DateTimeImmutable;
use PDO;
use Throwable;

final class OrganizacionController extends Controller
{
    private const CARGOS_PROGRAMA = ['coordinador', 'responsable', 'secretario_academico'];
    private const CARGOS_ORGANO = ['presidente', 'coordinador', 'secretario', 'vocal', 'miembro'];

    public function index(): void
    {
        $vistaSolicitada = (string) ($_GET['vista'] ?? 'programas');
        $vista = in_array($vistaSolicitada, ['programas','profesores','ca'], true) ? $vistaSolicitada : 'programas';
        $programaId = max(0, (int) ($_GET['programa_id'] ?? 0));
        $profesorId = max(0, (int) ($_GET['profesor_id'] ?? 0));
        $caId = max(0, (int) ($_GET['ca_id'] ?? 0));
        $programas = Database::todas(
            "SELECT p.id,p.siglas,p.nombre,p.nivel,
                    (SELECT GROUP_CONCAT(u.nombre ORDER BY u.nombre SEPARATOR ', ')
                       FROM programa_responsables pr JOIN usuarios u ON u.id=pr.usuario_id
                      WHERE pr.programa_id=p.id AND pr.cargo='coordinador' AND pr.activo=1
                        AND pr.fecha_inicio<=CURRENT_DATE AND (pr.fecha_fin IS NULL OR pr.fecha_fin>=CURRENT_DATE)) AS coordinadores,
                    (SELECT COUNT(*) FROM programa_organo_miembros pom JOIN programa_organos po ON po.id=pom.organo_id
                      WHERE po.programa_id=p.id AND po.tipo='NAB' AND po.activo=1 AND pom.activo=1
                        AND pom.fecha_inicio<=CURRENT_DATE AND (pom.fecha_fin IS NULL OR pom.fecha_fin>=CURRENT_DATE)) AS total_nab,
                    (SELECT COUNT(*) FROM programa_organo_miembros pom JOIN programa_organos po ON po.id=pom.organo_id
                      WHERE po.programa_id=p.id AND po.tipo='CAP' AND po.activo=1 AND pom.activo=1
                        AND pom.fecha_inicio<=CURRENT_DATE AND (pom.fecha_fin IS NULL OR pom.fecha_fin>=CURRENT_DATE)) AS total_cap,
                    (SELECT COUNT(*) FROM programa_profesores pp WHERE pp.programa_id=p.id AND pp.activo=1
                        AND pp.fecha_inicio<=CURRENT_DATE AND (pp.fecha_fin IS NULL OR pp.fecha_fin>=CURRENT_DATE)) AS total_profesores
               FROM programas p ORDER BY FIELD(p.nivel,'licenciatura','posgrado'),p.siglas"
        );
        $cuerpos = Database::todas("SELECT g.id,g.nombre,(SELECT COUNT(*) FROM firma_grupo_miembros gm WHERE gm.grupo_id=g.id) AS total FROM firma_grupos g WHERE g.tipo='cuerpo_academico' ORDER BY g.nombre");
        if ($programaId === 0 && $vista === 'programas' && $programas) $programaId = (int) $programas[0]['id'];
        if ($caId === 0 && $vista === 'ca' && $cuerpos) $caId = (int) $cuerpos[0]['id'];

        $this->vista('index', [
            'titulo' => 'Estructura académica',
            'vistaActual' => $vista,
            'programas' => $programas,
            'programa' => $programaId ? $this->programa($programaId) : null,
            'profesor' => $profesorId ? $this->profesor($profesorId) : null,
            'cuerpos' => $cuerpos,
            'cuerpo' => $caId ? $this->cuerpoAcademico($caId) : null,
        ]);
    }

    public function buscarProfesores(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 80) {
            echo json_encode(['resultados' => []]);
            return;
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $rows = Database::todas(
            "SELECT u.id,u.nombre,u.email,u.rol,d.nombre AS departamento
               FROM usuarios u LEFT JOIN departamentos d ON d.id=u.departamento_id
              WHERE u.activo=1 AND (u.rol IN ('profesor','coordinador','titular','admin') OR u.tipo_contratacion IS NOT NULL)
                AND (u.nombre LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\')
              ORDER BY CASE WHEN u.nombre LIKE ? ESCAPE '\\\\' THEN 0 ELSE 1 END,u.nombre LIMIT 15",
            [$like, $like, $q . '%']
        );
        echo json_encode(['resultados' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function asignarResponsable(string $id): void
    {
        $programaId = (int) $id;
        $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
        $cargo = $this->post('cargo');
        $fecha = $this->fecha($this->post('fecha_inicio', date('Y-m-d')));
        if (!$this->programaExiste($programaId) || !$this->personaExiste($usuarioId) || !in_array($cargo, self::CARGOS_PROGRAMA, true) || !$fecha) {
            flash('error', 'Selecciona un programa, una persona, un cargo y una fecha válidos.');
            $this->volverPrograma($programaId);
        }
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            if ($cargo === 'coordinador') {
                $stmt = $pdo->prepare("UPDATE programa_responsables SET activo=0,fecha_fin=CASE WHEN fecha_inicio>? THEN fecha_inicio ELSE ? END WHERE programa_id=? AND cargo='coordinador' AND activo=1");
                $stmt->execute([$fecha, $fecha, $programaId]);
            }
            $actual = Database::una('SELECT id FROM programa_responsables WHERE programa_id=? AND usuario_id=? AND cargo=? AND activo=1 LIMIT 1', [$programaId,$usuarioId,$cargo]);
            if ($actual) {
                Database::ejecutar('UPDATE programa_responsables SET fecha_inicio=?,fecha_fin=NULL,observaciones=NULL WHERE id=?', [$fecha,$actual['id']]);
            } else {
                Database::ejecutar('INSERT INTO programa_responsables(programa_id,usuario_id,cargo,fecha_inicio) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE activo=1,fecha_fin=NULL,observaciones=NULL', [$programaId,$usuarioId,$cargo,$fecha]);
            }
            if ($cargo === 'coordinador') $this->sincronizarCoordinadorReuniones($programaId, $usuarioId);
            $pdo->commit();
            flash('exito', 'Responsabilidad del programa guardada.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Organización responsable: ' . $e->getMessage());
            flash('error', 'No fue posible guardar la responsabilidad.');
        }
        $this->volverPrograma($programaId);
    }

    public function asignarProfesor(string $id): void
    {
        $programaId=(int)$id;$usuarioId=(int)($_POST['usuario_id']??0);$tipo=$this->post('tipo_vinculo','docente');$fecha=$this->fecha($this->post('fecha_inicio',date('Y-m-d')));
        if(!$this->programaExiste($programaId)||!$this->personaExiste($usuarioId)||!in_array($tipo,['docente','responsable_academico','colaborador'],true)||!$fecha){flash('error','Selecciona un programa, profesor, vínculo y fecha válidos.');$this->volverPrograma($programaId);}
        $actual=Database::una('SELECT id FROM programa_profesores WHERE programa_id=? AND usuario_id=? AND activo=1 LIMIT 1',[$programaId,$usuarioId]);
        if($actual) Database::ejecutar('UPDATE programa_profesores SET tipo_vinculo=?,fecha_inicio=?,fecha_fin=NULL WHERE id=?',[$tipo,$fecha,$actual['id']]);
        else Database::ejecutar('INSERT INTO programa_profesores(programa_id,usuario_id,tipo_vinculo,fecha_inicio) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE activo=1,fecha_fin=NULL,tipo_vinculo=VALUES(tipo_vinculo)',[$programaId,$usuarioId,$tipo,$fecha]);
        flash('exito','Profesor agregado al programa.');$this->volverPrograma($programaId);
    }

    public function asignarMiembro(string $id): void
    {
        $programaId = (int) $id;
        $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
        $tipo = strtoupper($this->post('tipo'));
        $cargo = $this->post('cargo', 'miembro');
        $fecha = $this->fecha($this->post('fecha_inicio', date('Y-m-d')));
        if (!$this->posgradoExiste($programaId) || !$this->personaExiste($usuarioId) || !in_array($tipo, ['NAB','CAP'], true) || !in_array($cargo, self::CARGOS_ORGANO, true) || !$fecha) {
            flash('error', 'Selecciona una persona, un órgano, un cargo y una fecha válidos.');
            $this->volverPrograma($programaId);
        }
        $programa = Database::una("SELECT siglas FROM programas WHERE id=? AND nivel='posgrado'", [$programaId]);
        try {
            Database::ejecutar(
                'INSERT INTO programa_organos(programa_id,tipo,nombre,activo) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE activo=1,nombre=VALUES(nombre)',
                [$programaId,$tipo,$tipo . ' de ' . $programa['siglas']]
            );
            $organoId = (int) Database::valor('SELECT id FROM programa_organos WHERE programa_id=? AND tipo=?', [$programaId,$tipo]);
            $actual = Database::una('SELECT id FROM programa_organo_miembros WHERE organo_id=? AND usuario_id=? AND activo=1 LIMIT 1', [$organoId,$usuarioId]);
            if ($actual) {
                Database::ejecutar('UPDATE programa_organo_miembros SET cargo=?,fecha_inicio=?,fecha_fin=NULL WHERE id=?', [$cargo,$fecha,$actual['id']]);
            } else {
                Database::ejecutar('INSERT INTO programa_organo_miembros(organo_id,usuario_id,cargo,fecha_inicio) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE activo=1,fecha_fin=NULL,observaciones=NULL', [$organoId,$usuarioId,$cargo,$fecha]);
            }
            flash('exito', "Integrante agregado al {$tipo}.");
        } catch (Throwable $e) {
            error_log('Organización miembro: ' . $e->getMessage());
            flash('error', 'No fue posible guardar la membresía.');
        }
        $this->volverPrograma($programaId);
    }

    public function retirarResponsable(string $id): void
    {
        $asignacion = Database::una('SELECT id,programa_id,cargo FROM programa_responsables WHERE id=? AND activo=1', [(int)$id]);
        if (!$asignacion) { flash('error','La asignación ya no está vigente.'); redirigir('ajustes/organizacion'); }
        Database::ejecutar('UPDATE programa_responsables SET activo=0,fecha_fin=CURRENT_DATE WHERE id=?', [$asignacion['id']]);
        if ($asignacion['cargo'] === 'coordinador') {
            $programa = Database::una('SELECT siglas FROM programas WHERE id=?', [$asignacion['programa_id']]);
            Database::ejecutar('DELETE rc FROM reuniones_coordinadores rc JOIN reuniones_posgrados rp ON rp.id=rc.posgrado_id WHERE rp.codigo=? AND rc.activo=0', [$programa['siglas']]);
            Database::ejecutar('UPDATE reuniones_coordinadores rc JOIN reuniones_posgrados rp ON rp.id=rc.posgrado_id SET rc.activo=0 WHERE rp.codigo=? AND rc.activo=1', [$programa['siglas']]);
        }
        flash('exito','Responsabilidad cerrada; se conserva su historial.');
        $this->volverPrograma((int)$asignacion['programa_id']);
    }

    public function retirarMiembro(string $id): void
    {
        $m = Database::una('SELECT pom.id,po.programa_id FROM programa_organo_miembros pom JOIN programa_organos po ON po.id=pom.organo_id WHERE pom.id=? AND pom.activo=1', [(int)$id]);
        if (!$m) { flash('error','La membresía ya no está vigente.'); redirigir('ajustes/organizacion'); }
        Database::ejecutar('UPDATE programa_organo_miembros SET activo=0,fecha_fin=CURRENT_DATE WHERE id=?', [$m['id']]);
        flash('exito','Membresía cerrada; se conserva su historial.');
        $this->volverPrograma((int)$m['programa_id']);
    }

    public function retirarProfesor(string $id): void
    {
        $r=Database::una('SELECT id,programa_id FROM programa_profesores WHERE id=? AND activo=1',[(int)$id]);
        if(!$r){flash('error','La adscripción ya no está vigente.');redirigir('ajustes/organizacion');}
        Database::ejecutar('UPDATE programa_profesores SET activo=0,fecha_fin=CURRENT_DATE WHERE id=?',[$r['id']]);
        flash('exito','Profesor retirado del programa; se conserva el historial.');$this->volverPrograma((int)$r['programa_id']);
    }

    public function crearCa(): void
    {
        $nombre=mb_strimwidth($this->post('nombre'),0,160);
        if($nombre===''){flash('error','Escribe el nombre del Cuerpo Académico.');redirigir('ajustes/organizacion?vista=ca');}
        try{$id=Database::ejecutar("INSERT INTO firma_grupos(nombre,tipo,creador_id) VALUES(?,'cuerpo_academico',NULL)",[$nombre]);flash('exito','Cuerpo Académico creado.');redirigir('ajustes/organizacion?vista=ca&ca_id='.$id);}
        catch(Throwable $e){error_log('Crear CA: '.$e->getMessage());flash('error','No fue posible crear el Cuerpo Académico.');redirigir('ajustes/organizacion?vista=ca');}
    }

    public function agregarMiembroCa(string $id): void
    {
        $caId=(int)$id;$usuarioId=(int)($_POST['usuario_id']??0);
        if(!$this->caExiste($caId)||!$this->personaExiste($usuarioId)){flash('error','Selecciona un Cuerpo Académico y un profesor válidos.');redirigir('ajustes/organizacion?vista=ca&ca_id='.$caId);}
        Database::ejecutar('INSERT IGNORE INTO firma_grupo_miembros(grupo_id,usuario_id) VALUES(?,?)',[$caId,$usuarioId]);
        flash('exito','Integrante agregado al Cuerpo Académico.');redirigir('ajustes/organizacion?vista=ca&ca_id='.$caId);
    }

    public function retirarMiembroCa(string $id): void
    {
        $caId=(int)$id;$usuarioId=(int)($_POST['usuario_id']??0);
        if(!$this->caExiste($caId)){flash('error','El Cuerpo Académico no existe.');redirigir('ajustes/organizacion?vista=ca');}
        Database::ejecutar('DELETE FROM firma_grupo_miembros WHERE grupo_id=? AND usuario_id=?',[$caId,$usuarioId]);
        flash('exito','Integrante retirado del Cuerpo Académico.');redirigir('ajustes/organizacion?vista=ca&ca_id='.$caId);
    }

    private function programa(int $id): ?array
    {
        $p = Database::una("SELECT id,siglas,nombre,nivel FROM programas WHERE id=?", [$id]);
        if (!$p) return null;
        $p['responsables'] = Database::todas("SELECT pr.*,u.nombre,u.email FROM programa_responsables pr JOIN usuarios u ON u.id=pr.usuario_id WHERE pr.programa_id=? AND pr.activo=1 AND (pr.fecha_fin IS NULL OR pr.fecha_fin>=CURRENT_DATE) ORDER BY FIELD(pr.cargo,'coordinador','responsable','secretario_academico'),u.nombre", [$id]);
        $p['miembros'] = Database::todas("SELECT pom.id,pom.cargo,pom.fecha_inicio,po.tipo,u.id AS usuario_id,u.nombre,u.email FROM programa_organo_miembros pom JOIN programa_organos po ON po.id=pom.organo_id JOIN usuarios u ON u.id=pom.usuario_id WHERE po.programa_id=? AND po.activo=1 AND pom.activo=1 AND (pom.fecha_fin IS NULL OR pom.fecha_fin>=CURRENT_DATE) ORDER BY po.tipo,FIELD(pom.cargo,'presidente','coordinador','secretario','vocal','miembro'),u.nombre", [$id]);
        $p['profesores'] = Database::todas("SELECT pp.id,pp.tipo_vinculo,pp.fecha_inicio,u.id AS usuario_id,u.nombre,u.email FROM programa_profesores pp JOIN usuarios u ON u.id=pp.usuario_id WHERE pp.programa_id=? AND pp.activo=1 AND (pp.fecha_fin IS NULL OR pp.fecha_fin>=CURRENT_DATE) ORDER BY u.nombre",[$id]);
        $p['cuerpos'] = Database::todas("SELECT DISTINCT g.id,g.nombre FROM firma_grupos g JOIN firma_grupo_miembros gm ON gm.grupo_id=g.id JOIN programa_profesores pp ON pp.usuario_id=gm.usuario_id AND pp.programa_id=? AND pp.activo=1 WHERE g.tipo='cuerpo_academico' ORDER BY g.nombre",[$id]);
        return $p;
    }

    private function profesor(int $id): ?array
    {
        $u = Database::una('SELECT u.id,u.nombre,u.email,u.rol,u.tipo_contratacion,d.nombre AS departamento FROM usuarios u LEFT JOIN departamentos d ON d.id=u.departamento_id WHERE u.id=?', [$id]);
        if (!$u) return null;
        $u['programas'] = Database::todas('SELECT pr.*,p.siglas,p.nombre AS programa FROM programa_responsables pr JOIN programas p ON p.id=pr.programa_id WHERE pr.usuario_id=? ORDER BY pr.activo DESC,pr.fecha_inicio DESC', [$id]);
        $u['adscripciones'] = Database::todas('SELECT pp.*,p.id AS programa_id,p.siglas,p.nombre AS programa,p.nivel FROM programa_profesores pp JOIN programas p ON p.id=pp.programa_id WHERE pp.usuario_id=? ORDER BY pp.activo DESC,p.nivel,p.siglas',[$id]);
        $u['organos'] = Database::todas('SELECT pom.*,po.tipo,p.id AS programa_id,p.siglas,p.nombre AS programa FROM programa_organo_miembros pom JOIN programa_organos po ON po.id=pom.organo_id JOIN programas p ON p.id=po.programa_id WHERE pom.usuario_id=? ORDER BY pom.activo DESC,p.siglas,po.tipo', [$id]);
        $u['cuerpos'] = Database::todas("SELECT g.id,g.nombre FROM firma_grupo_miembros gm JOIN firma_grupos g ON g.id=gm.grupo_id WHERE gm.usuario_id=? AND g.tipo='cuerpo_academico' ORDER BY g.nombre",[$id]);
        $u['dependencias'] = Database::todas('SELECT dr.*,d.nombre AS dependencia FROM departamento_responsables dr JOIN departamentos d ON d.id=dr.departamento_id WHERE dr.usuario_id=? ORDER BY dr.activo DESC,dr.fecha_inicio DESC', [$id]);
        return $u;
    }

    private function cuerpoAcademico(int $id): ?array
    {
        $g=Database::una("SELECT id,nombre FROM firma_grupos WHERE id=? AND tipo='cuerpo_academico'",[$id]);if(!$g)return null;
        $g['miembros']=Database::todas('SELECT u.id,u.nombre,u.email,u.rol FROM firma_grupo_miembros gm JOIN usuarios u ON u.id=gm.usuario_id WHERE gm.grupo_id=? ORDER BY u.nombre',[$id]);return $g;
    }

    private function sincronizarCoordinadorReuniones(int $programaId, int $usuarioId): void
    {
        $p = Database::una("SELECT siglas FROM programas WHERE id=? AND nivel='posgrado'", [$programaId]);
        $legacy = $p ? Database::una('SELECT id FROM reuniones_posgrados WHERE codigo=? AND activo=1', [$p['siglas']]) : null;
        if (!$legacy) return;
        Database::ejecutar('DELETE FROM reuniones_coordinadores WHERE posgrado_id=? AND activo=0', [$legacy['id']]);
        Database::ejecutar('UPDATE reuniones_coordinadores SET activo=0 WHERE posgrado_id=? AND activo=1', [$legacy['id']]);
        $old = Database::una('SELECT id FROM reuniones_coordinadores WHERE posgrado_id=? AND usuario_id=? ORDER BY id DESC LIMIT 1', [$legacy['id'],$usuarioId]);
        if ($old) Database::ejecutar('UPDATE reuniones_coordinadores SET activo=1 WHERE id=?', [$old['id']]);
        else Database::ejecutar('INSERT INTO reuniones_coordinadores(posgrado_id,usuario_id,activo) VALUES(?,?,1)', [$legacy['id'],$usuarioId]);
    }

    private function programaExiste(int $id): bool { return (bool) Database::valor("SELECT 1 FROM programas WHERE id=?", [$id]); }
    private function posgradoExiste(int $id): bool { return (bool) Database::valor("SELECT 1 FROM programas WHERE id=? AND nivel='posgrado'", [$id]); }
    private function caExiste(int $id): bool { return (bool)Database::valor("SELECT 1 FROM firma_grupos WHERE id=? AND tipo='cuerpo_academico'",[$id]); }
    private function personaExiste(int $id): bool { return (bool) Database::valor("SELECT 1 FROM usuarios WHERE id=? AND activo=1 AND (rol IN ('profesor','coordinador','titular','admin') OR tipo_contratacion IS NOT NULL)", [$id]); }
    private function fecha(string $fecha): ?string { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha); return $d && $d->format('Y-m-d')===$fecha ? $fecha : null; }
    private function volverPrograma(int $id): never { redirigir('ajustes/organizacion?programa_id='.$id); }
}
