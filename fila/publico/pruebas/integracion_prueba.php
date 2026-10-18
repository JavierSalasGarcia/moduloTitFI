<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3).'/app/bootstrap.php';
$integrationOkBefore=Pruebas::$ok;
$integrationFailuresBefore=count(Pruebas::$fallas);
$filaManifest = require dirname(__DIR__,3).'/app/Modules/Fila/module.php';
$filaManifest['dir'] = dirname(__DIR__,3).'/app/Modules/Fila';
// Aislar los manifiestos de otros módulos con menús dinámicos conectados a MySQL.
$moduleProperty = new ReflectionProperty(\App\Core\ModuleManager::class, 'modulos');
$moduleProperty->setAccessible(true);
$previousModules = $moduleProperty->getValue();
$moduleProperty->setValue(null, [$filaManifest]);

Pruebas::caso('Fila está disponible en el menú de todos los roles', function () {
    foreach (['alumno','profesor','operativo','titular','admin'] as $rol) {
        $items = [];
        foreach (\App\Core\ModuleManager::menu($rol) as $section) $items = array_merge($items, $section['items']);
        Pruebas::cierto(count(array_filter($items, fn($item)=>$item['titulo']==='Fila' && $item['ruta']==='fila'))===1);
        $visibleMenu=\App\Core\InterfaceUi::menu(\App\Core\ModuleManager::menu($rol));
        Pruebas::cierto(isset($visibleMenu['Fila']));
        Pruebas::igual('fila',$visibleMenu['Fila']['items'][0]['ruta']);
    }
});
Pruebas::caso('la sesión compartida no exige usuario para emitir y validar CSRF público', function () {
    require_once __DIR__.'/../lib/plantilla.php';
    unset($_SESSION['usuario']);
    $_POST = ['csrf'=>csrf()];
    Pruebas::cierto(csrf_valido());
    $_POST = ['csrf'=>'incorrecto'];
    Pruebas::igual(false, csrf_valido());
    $_POST = [];
});
Pruebas::caso('una confirmación repetida recupera el resultado de la misma sesión', function () {
    require_once __DIR__.'/../lib/plantilla.php';
    unset($_SESSION['fila_confirmaciones']);
    confirmacion_recordar(17, '/public/fila/publico/t/turno-creado');
    Pruebas::igual('/public/fila/publico/t/turno-creado', confirmacion_destino(17));
    Pruebas::igual(null, confirmacion_destino(18));
    $html=parcial('codigo',['dep'=>['clave'=>'sa'],'email'=>'ana@uaemex.mx','tipo'=>'turno',
        'error'=>'','verificacion_id'=>17]);
    Pruebas::cierto(str_contains($html,'data-confirmar-codigo'));
    Pruebas::cierto(str_contains($html,"boton.disabled=true"));
    unset($_SESSION['fila_confirmaciones']);
});
Pruebas::caso('Mis turnos presenta la posición, el estado y su enlace privado', function () {
    config_usar(['base_url'=>'/public/fila/publico']);
    $html = \App\Core\View::capturar('fila:index', ['pendiente'=>false, 'turnos'=>[
        ['oficina'=>'Oficina <prueba>', 'oficina_clave'=>'op', 'folio'=>7, 'estado'=>'espera', 'adelante'=>2,
         'estimado'=>new DateTimeImmutable('2026-09-17 13:30'), 'token'=>'token-propio'],
    ]]);
    Pruebas::cierto(str_contains($html,'2 persona(s)'));
    Pruebas::cierto(str_contains($html,'13:30'));
    Pruebas::cierto(str_contains($html,'/public/fila/publico/t/token-propio'));
    Pruebas::cierto(str_contains($html,'Oficina &lt;prueba&gt;'));
    $html = \App\Core\View::capturar('fila:index', ['pendiente'=>true, 'turnos'=>[]]);
    Pruebas::cierto(str_contains($html,'Verifica tu correo'));
});
Pruebas::caso('horarios valida días, horas y superposiciones', function () {
    $validate=[\App\Modules\Fila\HorariosController::class,'validar'];
    Pruebas::igual([['dia'=>1,'inicio'=>'09:00','fin'=>'12:00','activo'=>1]], $validate([
        ['activo'=>1,'dia'=>1,'inicio'=>'09:00','fin'=>'12:00'], ['dia'=>2,'inicio'=>'','fin'=>''],
    ]));
    foreach ([
        [['activo'=>1,'dia'=>8,'inicio'=>'09:00','fin'=>'12:00']],
        [['activo'=>1,'dia'=>1,'inicio'=>'25:00','fin'=>'26:00']],
        [['activo'=>1,'dia'=>1,'inicio'=>'12:00','fin'=>'09:00']],
        [['activo'=>1,'dia'=>1,'inicio'=>'09:00','fin'=>'12:00'],['activo'=>1,'dia'=>1,'inicio'=>'11:00','fin'=>'14:00']],
    ] as $rows) {
        $rejected=false;
        try { $validate($rows); } catch (InvalidArgumentException $e) { $rejected=true; }
        Pruebas::cierto($rejected);
    }
});
Pruebas::caso('un responsable solo configura su oficina; los visitantes no pueden', function () {
    $previous=$_SESSION['usuario']??null;
    $dep=base_de_prueba();
    db()->exec('CREATE TABLE fila_responsables (usuario_id INTEGER, dependencia_id INTEGER, cargo TEXT)');
    consulta('INSERT INTO fila_responsables VALUES (?,?,?)',[4,$dep['id'],'Subdirector Académico']);
    $_SESSION['usuario']=['id'=>4,'rol'=>'titular'];
    Pruebas::cierto(\App\Modules\Fila\FilaAccess::oficina('sa')!==null);
    Pruebas::igual(null,\App\Modules\Fila\FilaAccess::oficina('dir'));
    $_SESSION['usuario']=['id'=>5,'rol'=>'titular'];
    Pruebas::igual([],\App\Modules\Fila\FilaAccess::oficinas());
    $_SESSION['usuario']=['id'=>4,'rol'=>'alumno'];
    Pruebas::igual([],\App\Modules\Fila\FilaAccess::oficinas());
    unset($_SESSION['usuario']);
    Pruebas::igual([],\App\Modules\Fila\FilaAccess::oficinas());
    if ($previous) $_SESSION['usuario']=$previous;
});
Pruebas::caso('las dos pestañas separan atención propia de otros departamentos', function () {
    $dep=base_de_prueba();
    consulta('INSERT INTO dependencias (clave,nombre,titular,activa,creado) VALUES (?,?,?,1,?)',
        ['dir','Dirección','Otra persona',ahora_texto()]);
    $other=dependencia('dir');
    crear_turno($dep,'Ana <Prueba> Ruiz','ana@uaemex.mx','Consulta <privada>',5);
    crear_turno($other,'Persona de otra oficina','otro@uaemex.mx','Asunto de otra oficina',5);
    $own=\App\Modules\Fila\FilaController::paneles([$dep],'atender',false);
    Pruebas::igual(1,count($own['oficinasAtencion']));
    Pruebas::igual('Ana Ruiz',$own['oficinasAtencion'][0]['estado']['espera'][0]['nombre']);
    Pruebas::igual([],$own['otrasOficinas']);
    $html=\App\Core\View::capturar('fila:index', ['pestana'=>'atender','pendiente'=>false,
        'turnos'=>[], 'oficinasGestion'=>[$dep], ...$own]);
    Pruebas::igual(0,preg_match_all('/<h1\b/',$html));
    Pruebas::igual(2,preg_match_all('/class="pestana(?: activa)?"/',$html));
    Pruebas::cierto(str_contains($html,'Ana Ruiz'));
    Pruebas::cierto(str_contains($html,'Consulta &lt;privada&gt;'));
    Pruebas::cierto(str_contains($html,'aria-label="Copiar enlace del kiosco"'));
    Pruebas::cierto(str_contains($html,'Atender Ana Ruiz (sa1)'));
    Pruebas::cierto(str_contains($html,'Configurar mis horarios — Define los días e intervalos'));
    Pruebas::igual(1,substr_count($html,'class="fila-actions"'));
    Pruebas::igual(1,substr_count($html,'class="fila-main-actions"'));
    Pruebas::igual(0,substr_count($html,'aria-label="Pantalla de turnos"'));
    Pruebas::igual(1,substr_count($html,'aria-label="Copiar enlace público para el QR"'));
    Pruebas::cierto(str_contains($html,'>No disponible</button>'));
    Pruebas::igual(1,substr_count($html,'data-operacion-atencion>'));
    Pruebas::cierto(str_contains($html,'btn btn-primario fila-text-btn'));
    Pruebas::igual(false,str_contains($html,'Persona de otra oficina'));
    $others=\App\Modules\Fila\FilaController::paneles([$dep],'otros',false);
    Pruebas::igual([],$others['oficinasAtencion']);
    Pruebas::igual(1,count($others['otrasOficinas']));
    Pruebas::igual('dir',$others['otrasOficinas'][0]['oficina']['clave']);
    Pruebas::igual(1,$others['otrasOficinas'][0]['espera']);
    $html=\App\Core\View::capturar('fila:index', ['pestana'=>'otros','pendiente'=>false,
        'turnos'=>[], 'oficinasGestion'=>[$dep], ...$others]);
    Pruebas::igual(false,str_contains($html,'Persona de otra oficina'));
    Pruebas::igual(false,str_contains($html,'Consulta &lt;privada&gt;'));
    $restricted=\App\Modules\Fila\FilaController::paneles([$dep],'atender',true);
    Pruebas::igual([],$restricted['oficinasAtencion']);
    Pruebas::igual([],\App\Modules\Fila\FilaController::paneles([],'atender',false)['oficinasAtencion']);
});
Pruebas::caso('el responsable alterna No disponible y el kiosco lo refleja', function () {
    $dep=base_de_prueba(['inicio'=>'08:00','fin'=>'23:00']);
    abrir_jornada($dep,'23:00',null,null,'08:00');
    crear_turno($dep,'Persona en espera','espera@uaemex.mx','Consulta',5);
    $anterior=$_SESSION['usuario']??null;
    $_SESSION['usuario']=['id'=>4,'rol'=>'titular','email'=>'titular@uaemex.mx'];
    $class=\App\Modules\Fila\FilaController::class;
    Pruebas::cierto($class::cambiarDisponibilidad($dep,false,momento_hoy('10:00')));
    $pausado=fila_estado($dep,momento_hoy('10:01'));
    Pruebas::cierto($pausado['pausa_manual']);
    Pruebas::igual(false,$pausado['abierta']);
    Pruebas::cierto($pausado['proxima_atencion']>momento_hoy('23:00'));
    Pruebas::cierto(str_starts_with(leyenda_publica_atencion($pausado),'No disponible. Próxima atención: '));
    $kiosco=parcial('pantalla',['dep'=>$dep,'estado'=>$pausado]);
    Pruebas::cierto(str_contains($kiosco,'class="hall-empty hall-empty-unavailable"'));
    Pruebas::cierto(str_contains($kiosco,'id="hall-empty-title">No disponible.'));
    Pruebas::cierto(str_contains($kiosco,'Próxima atención:'));
    Pruebas::cierto(str_contains($kiosco,'id="hall-now-eyebrow">Para atenderte mejor'));
    Pruebas::cierto(str_contains($kiosco,'id="hall-now-icon"'));
    Pruebas::cierto(str_contains($kiosco,'id="hall-current-label">Escanea el código y aparta tu lugar'));
    Pruebas::igual(false,str_contains($kiosco,'id="hall-current-label">No hay ninguna persona siendo atendida'));
    Pruebas::cierto(str_contains($kiosco,'id="hall-waiting-list" class="hall-list" hidden'));
    Pruebas::igual(false,$class::cambiarDisponibilidad($dep,true,momento_hoy('10:02')));
    $reactivado=fila_estado($dep,momento_hoy('10:03'));
    Pruebas::igual(false,$reactivado['pausa_manual']);
    Pruebas::igual(true,$reactivado['abierta']);
    if ($anterior) $_SESSION['usuario']=$anterior; else unset($_SESSION['usuario']);
});
Pruebas::caso('No disponible pausa solo el intervalo actual y permite formarse para el siguiente', function () {
    $dep=base_de_prueba();
    consulta('DELETE FROM horarios WHERE dependencia_id=?',[$dep['id']]);
    $dia=(int)(new DateTimeImmutable('now'))->format('N');
    consulta('INSERT INTO horarios (dependencia_id,dia,inicio,fin) VALUES (?,?,?,?),(?,?,?,?)',
        [$dep['id'],$dia,'09:00','11:00',$dep['id'],$dia,'15:00','17:00']);
    abrir_jornada($dep,'17:00',null,null,'09:00');
    $anterior=$_SESSION['usuario']??null;
    $_SESSION['usuario']=['id'=>4,'rol'=>'titular','email'=>'titular@uaemex.mx'];
    $class=\App\Modules\Fila\FilaController::class;
    Pruebas::cierto($class::cambiarDisponibilidad($dep,false,momento_hoy('09:34')));
    $bloqueo=fila_una('SELECT fin FROM bloqueos WHERE dependencia_id=? AND origen=?',[$dep['id'],'pausa']);
    Pruebas::igual(hoy('11:00').':00',$bloqueo['fin']);
    $pausado=fila_estado($dep,momento_hoy('09:35'));
    Pruebas::igual(true,$pausado['abierta']);
    Pruebas::igual(hoy('15:00'),$pausado['proximo_hueco']->format('Y-m-d H:i'));
    Pruebas::igual(hoy('15:00'),$pausado['proxima_atencion']->format('Y-m-d H:i'));
    $turno=turno_rapido($dep,'tarde@uaemex.mx')['turno'];
    $conTurno=fila_estado($dep,momento_hoy('09:35'));
    $enFila=array_values(array_filter($conTurno['espera'],fn($t)=>(int)$t['id']===(int)$turno['id']))[0];
    Pruebas::igual(hoy('15:00'),$enFila['estimado']->format('Y-m-d H:i'));
    if ($anterior) $_SESSION['usuario']=$anterior; else unset($_SESSION['usuario']);
});
Pruebas::caso('la primera visita pasa directo y la fila comienza al llegar otra persona', function () {
    $dep=base_de_prueba(['inicio'=>'00:00','fin'=>'23:59']);
    abrir_jornada($dep,'23:59');
    $ahora=new \DateTimeImmutable('now');
    $anterior=$_SESSION['usuario']??null;
    $_SESSION['usuario']=['id'=>4,'rol'=>'titular','email'=>'titular@uaemex.mx'];
    $libre=fila_estado($dep,$ahora);
    Pruebas::cierto(str_contains(parcial('pantalla',['dep'=>$dep,'estado'=>$libre]),'Disponible, pasa'));
    $panelLibre=\App\Core\View::capturar('fila:index',['pestana'=>'atender','pendiente'=>false,'turnos'=>[],
        'oficinasGestion'=>[$dep],'oficinasAtencion'=>[['oficina'=>$dep,'estado'=>$libre]],'otrasOficinas'=>[]]);
    Pruebas::cierto(str_contains($panelLibre,'>Ya entró una persona sin turno</button>'));
    Pruebas::cierto(str_contains($panelLibre,'>No disponible</button>'));
    $directa=\App\Modules\Fila\FilaController::iniciarDirecta($dep,$ahora);
    Pruebas::cierto(es_atencion_directa($directa));
    $ocupada=fila_estado($dep,$ahora->modify('+1 second'));
    Pruebas::cierto(es_atencion_directa($ocupada['actual']));
    $kiosco=parcial('pantalla',['dep'=>$dep,'estado'=>$ocupada]);
    Pruebas::cierto(str_contains($kiosco,'Hay una persona siendo atendida'));
    Pruebas::cierto(str_contains($kiosco,'class="hall-empty hall-empty-options"'));
    Pruebas::cierto(str_contains($kiosco,'id="hall-empty-title">Puedes esperar a que termine la atención actual'));
    Pruebas::cierto(str_contains($kiosco,'id="hall-empty-help">o escanea el código para apartar tu lugar'));
    $panelOcupado=\App\Core\View::capturar('fila:index',['pestana'=>'atender','pendiente'=>false,'turnos'=>[],
        'oficinasGestion'=>[$dep],'oficinasAtencion'=>[['oficina'=>$dep,'estado'=>$ocupada]],'otrasOficinas'=>[]]);
    Pruebas::cierto(str_contains($panelOcupado,'>Terminé esta atención</button>'));
    $turno=crear_turno($dep,'Persona en espera','espera@uaemex.mx','Consulta',5);
    $conFila=fila_estado($dep,$ahora->modify('+2 seconds'));
    Pruebas::igual(1,count($conFila['espera']));
    $llamado=\App\Modules\Fila\FilaController::avanzar($dep,(int)$directa['id'],(int)$turno['id']);
    Pruebas::igual($turno['id'],$llamado['id']);
    Pruebas::igual('atendido',fila_una('SELECT estado FROM turnos WHERE id=?',[$directa['id']])['estado']);
    Pruebas::igual(null,\App\Modules\Fila\FilaController::avanzar($dep,(int)$turno['id'],0));
    $otraVezLibre=fila_estado($dep,$ahora->modify('+3 seconds'));
    Pruebas::cierto(str_contains(parcial('pantalla',['dep'=>$dep,'estado'=>$otraVezLibre]),'Disponible, pasa'));
    if ($anterior) $_SESSION['usuario']=$anterior; else unset($_SESSION['usuario']);
});
Pruebas::caso('el kiosco tiene QR al formulario y respeta la privacidad pública',function () {
    $dep=base_de_prueba();
    crear_turno($dep,'Ana Ruiz López','ana@uaemex.mx','Asunto reservado',5);
    $html=parcial('pantalla',['dep'=>$dep,'estado'=>fila_estado($dep)]);
    Pruebas::cierto(str_contains($html,'href="https://fingenieria.mx/citas/sa/turno"'));
    Pruebas::igual(false,str_contains($html,'Sin cuenta · Sin iniciar sesión'));
    Pruebas::cierto(str_contains($html,'Hora aprox.'));
    Pruebas::cierto(str_contains($html,'Pantalla completa'));
    Pruebas::cierto(str_contains($html,'class="hall-scan-icon"'));
    Pruebas::cierto(str_contains($html,'id="hall-empty-icon"'));
    Pruebas::cierto(str_contains($html,'aria-hidden="true">🎟️'));
    Pruebas::igual(false,str_contains($html,'ana@uaemex.mx'));
    Pruebas::igual(false,str_contains($html,'Asunto reservado'));
    Pruebas::igual(false,str_contains($html,'Ana Ruiz'));
    Pruebas::igual(false,str_contains($html,'>Persona<'));
    $dep['mostrar_nombres']=0;
    $html=parcial('pantalla',['dep'=>$dep,'estado'=>fila_estado($dep)]);
    Pruebas::igual(false,str_contains($html,'Ana Ruiz'));
    Pruebas::igual(false,str_contains($html,'.innerHTML'));
});
Pruebas::caso('el kiosco destaca el siguiente intervalo de atención del mismo día',function () {
    $dep=base_de_prueba();
    consulta('DELETE FROM horarios WHERE dependencia_id=?',[$dep['id']]);
    $dia=(int)(new DateTimeImmutable('now'))->format('N');
    consulta('INSERT INTO horarios (dependencia_id,dia,inicio,fin) VALUES (?,?,?,?),(?,?,?,?)',
        [$dep['id'],$dia,'09:00','11:00',$dep['id'],$dia,'15:00','17:00']);
    $html=parcial('pantalla',['dep'=>$dep,'estado'=>fila_estado($dep,momento_hoy('14:25'))]);
    Pruebas::cierto(str_contains($html,'class="hall-empty hall-empty-next"'));
    Pruebas::cierto(str_contains($html,'Próximo horario de atención'));
    Pruebas::cierto(str_contains($html,'Hoy a las 3:00 p. m.'));
    Pruebas::cierto(str_contains($html,'Próxima atención: hoy a las 3:00 p. m.'));
    Pruebas::igual(false,str_contains($html,'¿La oficina está ocupada?'));
    Pruebas::cierto(str_contains($html,'Evita esperar<br>y dar vueltas'));
    Pruebas::cierto(str_contains($html,'Escanea para saber cuándo pasar'));
    $disponible=parcial('pantalla',['dep'=>$dep,'estado'=>fila_estado($dep,momento_hoy('15:12'))]);
    Pruebas::cierto(str_contains($disponible,'Disponible, pasa'));
    Pruebas::cierto(str_contains($disponible,'class="hall-empty hall-empty-available"'));
    Pruebas::cierto(str_contains($disponible,'id="hall-waiting-title" class="hall-waiting-title" hidden'));
    Pruebas::cierto(str_contains($disponible,'id="hall-columns" class="hall-columns" hidden'));
});
Pruebas::caso('el turno móvil pide solo correo y separa la reunión completa', function () {
    $dep=base_de_prueba();
    $estado=fila_estado($dep);
    $rapido=parcial('turno',['dep'=>$dep,'estado'=>$estado,'huecos'=>[],
        'minutos_cita'=>[30,60],'datos'=>[],'error'=>'']);
    Pruebas::cierto(str_contains($rapido,'action="/citas/sa/turno"'));
    Pruebas::igual(1,substr_count($rapido,'name="email"'));
    Pruebas::igual(false,str_contains($rapido,'name="nombre"'));
    Pruebas::igual(false,str_contains($rapido,'name="asunto"'));
    Pruebas::cierto(str_contains($rapido,'Obtener turno'));
    Pruebas::cierto(str_contains($rapido,'Agendar reunión'));
    Pruebas::cierto(str_contains($rapido,'sin @uaemex.mx'));
    Pruebas::cierto(str_contains($rapido,'placeholder="usuario"'));
    Pruebas::cierto(str_contains($rapido,'data-correo-local'));
    $recordado=parcial('turno',['dep'=>$dep,'estado'=>$estado,'huecos'=>[],
        'minutos_cita'=>[30,60],'datos'=>['email'=>'ana@uaemex.mx'],'error'=>'',
        'correo_recordado'=>'ana@uaemex.mx','identidad_recordada'=>[
            'registrado'=>true,'nombre_real'=>'Ana Ruiz López','nombre'=>'Ana Ruiz López']]);
    Pruebas::cierto(str_contains($recordado,'Hola, Ana Ruiz'));
    Pruebas::cierto(str_contains($recordado,'type="hidden" value="ana@uaemex.mx"'));
    Pruebas::cierto(str_contains($recordado,'No soy yo'));
    Pruebas::igual(false,str_contains($recordado,'placeholder="usuario@'));
    $cita=parcial('turno',['dep'=>$dep,'estado'=>$estado,'huecos'=>[],
        'minutos_cita'=>[30,60],'datos'=>['tipo'=>'cita'],'error'=>'']);
    foreach (['name="email"','name="nombre"','name="asunto"','name="descripcion"','name="hora"'] as $campo) {
        Pruebas::cierto(str_contains($cita,$campo),$campo);
    }
    Pruebas::cierto(str_contains($cita,'Confirmarás por correo'));
});
Pruebas::caso('una cita con hora vencida conserva la captura y exige otra hora', function () {
    $dep=base_de_prueba();
    $estado=fila_estado($dep);
    $estado['abierta']=true;
    $html=parcial('turno', ['dep'=>$dep,'estado'=>$estado,'huecos'=>[],
        'minutos_cita'=>[30,60], 'error'=>'Elige otra hora.',
        'datos'=>['tipo'=>'cita','nombre'=>'Persona Prueba','email'=>'prueba@uaemex.mx',
            'asunto'=>'Consulta','minutos_cita'=>60,'hora'=>'2000-01-01 09:00',
            'descripcion'=>'Texto capturado <que debe conservarse>']]);
    Pruebas::cierto(str_contains($html,'value="Persona Prueba"'));
    Pruebas::cierto(str_contains($html,'value="prueba@uaemex.mx"'));
    Pruebas::cierto(str_contains($html,'Texto capturado &lt;que debe conservarse&gt;'));
    Pruebas::cierto(str_contains($html,'value="60" selected'));
    Pruebas::cierto(str_contains($html,'id="tipo" value="cita"'));
    Pruebas::cierto(str_contains($html,'value="" selected disabled>Elige otra hora'));
});
Pruebas::caso('el expediente registra atención directa e historial privado por oficina', function () {
    $dep=base_de_prueba();
    $anterior=$_SESSION['usuario']??null;
    $_SESSION['usuario']=['id'=>4,'rol'=>'titular'];
    $class=\App\Modules\Fila\ExpedienteController::class;
    $t=$class::crearDirecta($dep,['nombre'=>'Persona Prueba','email'=>'persona@gmail.com',
        'asunto'=>'Atención sin fila','fecha_atencion'=>'2020-01-01T10:30']);
    Pruebas::igual('atendido',$t['estado']);
    Pruebas::igual('2020-01-01',$t['fecha']);
    Pruebas::igual('2020-01-01 10:30:00',$t['llamado']);
    Pruebas::igual(0,count(fila_estado($dep)['espera']));
    $class::registrar($dep,(int)$t['id'],'Primer resumen <privado>');
    $class::registrar($dep,(int)$t['id'],'Seguimiento sin sobrescribir');
    $historial=$class::historial($dep,'PERSONA@gmail.com');
    Pruebas::igual(1,count($historial));
    Pruebas::igual(2,(int)$historial[0]['minutas']);
    Pruebas::igual([],$class::historial($dep,'otro@gmail.com'));
    $otra=[...$dep,'id'=>999];
    Pruebas::igual(null,$class::turno($otra,(int)$t['id']));
    Pruebas::igual([],$class::historial($otra));
    try { $class::registrar($otra,(int)$t['id'],'Intrusión'); Pruebas::cierto(false); }
    catch (InvalidArgumentException $e) { Pruebas::cierto(true); }
    $espera=crear_turno($dep,'Otra Persona','otra@uaemex.mx','Consulta',5);
    $class::registrar($dep,(int)$espera['id'],'Atendido',finalizar:true);
    Pruebas::igual('atendido',$class::turno($dep,(int)$espera['id'])['estado']);
    Pruebas::igual(2,(int)fila_una('SELECT COUNT(*) AS n FROM fila_expedientes WHERE turno_id=?',[$t['id']])['n']);
    $html=\App\Core\View::capturar('fila:expediente',['dep'=>$dep,'turno'=>$t,
        'historial'=>$historial,'registros'=>filas('SELECT * FROM fila_expedientes WHERE turno_id=?',[$t['id']]),
        'guardadas'=>[],'email'=>'','error'=>'','captura'=>[]]);
    Pruebas::cierto(str_contains($html,'Primer resumen &lt;privado&gt;'));
    Pruebas::cierto(str_contains($html,'Seguimiento sin sobrescribir'));
    if ($anterior) $_SESSION['usuario']=$anterior; else unset($_SESSION['usuario']);
});
Pruebas::caso('carga de minutas limita formatos y rechaza archivos ejecutables', function () {
    $class=\App\Modules\Fila\ExpedienteController::class;
    Pruebas::igual('txt',$class::archivoValido('20260917_1000_minuta.txt','Acuerdos de reunión'));
    Pruebas::igual('md',$class::archivoValido('minuta.md','# Resumen'));
    Pruebas::igual('pdf',$class::archivoValido('minuta.pdf','%PDF-1.4 contenido'));
    foreach ([['minuta.php','<?php echo 1;'],['minuta.pdf','No soy PDF'],['minuta.txt',"texto\0binario"],['minuta.txt',''],['minuta.txt',str_repeat('x',5*1024*1024+1)]] as [$nombre,$contenido]) {
        try { $class::archivoValido($nombre,$contenido); Pruebas::cierto(false); }
        catch (InvalidArgumentException $e) { Pruebas::cierto(true); }
    }
    foreach ([['nombre'=>'Mal','email'=>'no-valido','asunto'=>''],
        ['nombre'=>'Persona Prueba','email'=>'p@gmail.com','asunto'=>'Prueba','fecha_atencion'=>'2020-02-31T10:00'],
        ['nombre'=>'Persona Prueba','email'=>'p@gmail.com','asunto'=>'Prueba','fecha_atencion'=>'2099-01-01T10:00']] as $d) {
        try { $class::validarDirecta($d); Pruebas::cierto(false); }
        catch (InvalidArgumentException $e) { Pruebas::cierto(true); }
    }
});
Pruebas::caso('el nombre oficial solo procede del registro y el texto manual es un alias', function () {
    $dep=base_de_prueba();
    $cfg=config();
    $property=new ReflectionProperty(\App\Core\Database::class,'pdo');
    $previo=$property->getValue();
    $main=new PDO('sqlite::memory:');
    $main->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    $main->exec('CREATE TABLE usuarios (email TEXT, nombre TEXT, activo INTEGER)');
    $main->exec("INSERT INTO usuarios VALUES ('oficial@uaemex.mx','Nombre Oficial Registrado',1)");
    $property->setValue(null,$main);
    config_usar([...$cfg,'integrado'=>true]);
    try {
        $i=fila_identidad($dep,'OFICIAL@uaemex.mx','Nombre inventado');
        Pruebas::igual('Nombre Oficial Registrado',$i['nombre_real']);
        Pruebas::igual('',$i['alias']);
        $t=crear_turno($dep,'Inventado','oficial@uaemex.mx','Prueba',5);
        Pruebas::igual('Nombre Oficial Registrado',$t['nombre']);
        Pruebas::igual(null,fila_una('SELECT * FROM fila_aliases WHERE email=?',['oficial@uaemex.mx']));
        $i=fila_identidad($dep,'nuevo@uaemex.mx','Alias Opcional');
        Pruebas::igual(null,$i['nombre_real']);
        Pruebas::igual('Alias Opcional',$i['alias']);
        crear_cita($dep,'Alias Opcional','nuevo@uaemex.mx','Prueba','',20,'2030-01-01 10:00:00');
        Pruebas::igual('Alias Opcional',fila_una('SELECT alias FROM fila_aliases WHERE email=?',['nuevo@uaemex.mx'])['alias']);
        $directa=\App\Modules\Fila\ExpedienteController::crearDirecta($dep,['email'=>'sin-nombre@uaemex.mx','asunto'=>'Sin nombre']);
        Pruebas::igual('sin-nombre@uaemex.mx',$directa['nombre']);
        Pruebas::igual('', $directa['alias']);
        Pruebas::igual(null,$directa['nombre_real']);
        $main->exec("INSERT INTO usuarios VALUES ('nuevo@uaemex.mx','Nombre Real Posterior',1)");
        Pruebas::igual('Nombre Real Posterior',fila_identidad($dep,'nuevo@uaemex.mx','Alias Opcional')['nombre']);
    } finally { $property->setValue(null,$previo); config_usar($cfg); }
});
Pruebas::caso('pasar al siguiente recorre la cola una sola vez y solo en su oficina', function () {
    $dep=base_de_prueba();
    consulta('INSERT INTO dependencias (clave,nombre,titular,activa,creado) VALUES (?,?,?,1,?)',
        ['otra','Otra oficina','Otro titular',ahora_texto()]);
    $otra=dependencia('otra');
    $externo=crear_turno($otra,'Persona Externa','externo@uaemex.mx','Otra consulta',5);
    $uno=crear_turno($dep,'Persona Uno','uno@uaemex.mx','Primera consulta',5);
    $dos=crear_turno($dep,'Persona Dos','dos@uaemex.mx','Segunda consulta',5);
    $class=\App\Modules\Fila\FilaController::class;
    $actual=$class::avanzar($dep,0,(int)$uno['id']);
    Pruebas::igual('llamado',$actual['estado']);
    Pruebas::igual($uno['id'],fila_estado($dep)['actual']['id']);
    Pruebas::igual($dos['id'],fila_estado($dep)['espera'][0]['id']);
    try { $class::avanzar($dep,0,(int)$uno['id']); Pruebas::cierto(false); }
    catch (DomainException $e) { Pruebas::cierto(true); }
    Pruebas::igual('espera',turno_por_token($dos['token'])['estado']);
    $actual=$class::avanzar($dep,(int)$uno['id'],(int)$dos['id']);
    Pruebas::igual('atendido',turno_por_token($uno['token'])['estado']);
    Pruebas::cierto(turno_por_token($uno['token'])['cerrado']!==null);
    Pruebas::igual($dos['id'],$actual['id']);
    Pruebas::igual('llamado',$actual['estado']);
    Pruebas::igual('espera',turno_por_token($externo['token'])['estado']);
    Pruebas::igual(null,$class::avanzar($dep,(int)$dos['id'],0));
    Pruebas::igual(null,fila_estado($dep)['actual']);
    Pruebas::igual([],fila_estado($dep)['espera']);
    Pruebas::igual(null,dependencia_id((int)$dep['id'])['atendiendo_turno']);
});
Pruebas::caso('una cita por persona y día; cancelar libera el horario y recorre los turnos', function () {
    $dep=base_de_prueba();
    $c=crear_cita($dep,'Persona Prueba','persona@uaemex.mx','Reunión','',30,hoy('09:00:00'));
    try { crear_cita($dep,'Alias distinto','PERSONA@uaemex.mx','Duplicada','',30,hoy('15:00:00')); Pruebas::cierto(false); }
    catch (DomainException $e) { Pruebas::cierto(true); }
    $c=aprobar_cita($c);
    $t=crear_turno($dep,'Visitante Prueba','visitante@uaemex.mx','Consulta',5);
    $antes=fila_estado($dep,momento_hoy('09:00'))['espera'][0]['estimado'];
    cancelar_cita($c);
    Pruebas::igual('cancelada',cita_por_token($c['token'])['estado']);
    Pruebas::igual(0,(int)fila_una('SELECT COUNT(*) AS n FROM bloqueos WHERE cita_id=?',[$c['id']])['n']);
    $despues=fila_estado($dep,momento_hoy('09:00'))['espera'][0]['estimado'];
    Pruebas::cierto($despues<$antes);
    $nueva=crear_cita($dep,'Persona Prueba','persona@uaemex.mx','Nueva','',30,hoy('11:00:00'));
    Pruebas::igual('solicitada',$nueva['estado']);
    $manana=(new DateTimeImmutable('tomorrow'))->format('Y-m-d').' 09:00:00';
    $otra=crear_cita($dep,'Persona Prueba','persona@uaemex.mx','Otro día','',30,$manana);
    try { aprobar_cita($otra,hoy('13:00:00')); Pruebas::cierto(false); }
    catch (DomainException $e) { Pruebas::cierto(true); }
    Pruebas::igual('solicitada',cita_por_token($otra['token'])['estado']);
});
Pruebas::caso('permite distintas oficinas y advierte solo diferencias menores a 15 minutos',function () {
    $dep=base_de_prueba();
    consulta('INSERT INTO dependencias (clave,nombre,titular,dominio_correo,activa,creado) VALUES (?,?,?,?,1,?)',
        ['otra','Otra oficina','Otro titular','uaemex.mx',ahora_texto()]);
    $otra=dependencia('otra');
    $c=crear_cita($dep,'Persona Prueba','persona@uaemex.mx','Primera oficina','',30,'2030-01-01 10:10:00');
    $otraCita=crear_cita($otra,'Persona Prueba','persona@uaemex.mx','Segunda oficina','',30,'2030-01-01 10:00:00');
    Pruebas::igual('solicitada',$otraCita['estado']);
    Pruebas::cierto(advertencia_otras_filas((int)$dep['id'],'persona@uaemex.mx',a_momento('2030-01-01 10:14:59'))!=='');
    Pruebas::cierto(advertencia_otras_filas((int)$dep['id'],'persona@uaemex.mx',a_momento('2030-01-01 09:46:00'))!=='');
    Pruebas::igual('',advertencia_otras_filas((int)$dep['id'],'persona@uaemex.mx',a_momento('2030-01-01 10:15:00')));
    Pruebas::igual('',advertencia_otras_filas((int)$dep['id'],'persona@uaemex.mx',a_momento('2030-01-01 09:45:00')));
    Pruebas::igual('',advertencia_otras_filas((int)$dep['id'],'otro@uaemex.mx',a_momento('2030-01-01 10:10:00')));
    cancelar_cita($otraCita);
    Pruebas::igual('',advertencia_otras_filas((int)$dep['id'],'persona@uaemex.mx',a_momento('2030-01-01 10:10:00')));
    Pruebas::igual($c['id'],cita_activa_del_dia((int)$dep['id'],'persona@uaemex.mx','2030-01-01 12:00:00')['id']);
});
Pruebas::caso('horarios conserva varios días e intervalos y acepta los segundos de MySQL',function () {
    $v=\App\Modules\Fila\HorariosController::validar([
        ['activo'=>1,'dia'=>1,'inicio'=>'09:00:00','fin'=>'11:00:00'],
        ['activo'=>1,'dia'=>1,'inicio'=>'13:00','fin'=>'15:00'],
        ['activo'=>1,'dia'=>2,'inicio'=>'08:00','fin'=>'10:00'],
        ['activo'=>1,'dia'=>3,'inicio'=>'','fin'=>''],
    ]);
    Pruebas::igual(3,count($v));
    Pruebas::igual(['dia'=>1,'inicio'=>'09:00','fin'=>'11:00','activo'=>1],$v[0]);
    Pruebas::igual(1,$v[1]['dia']);
    Pruebas::igual(2,$v[2]['dia']);
    $dep=base_de_prueba();
    Pruebas::igual([15,20],minutos_de_cita($dep));
    $html=\App\Core\View::capturar('fila:horarios',['dep'=>$dep,'horarios'=>$v,'error'=>'']);
    Pruebas::cierto(str_contains($html,'data-agregar-intervalo'));
    Pruebas::cierto(str_contains($html,'Agregar día / intervalo'));
    Pruebas::igual(3,preg_match_all('/<input type="checkbox" name="horarios\[\d+\]\[inhabil\]"/',$html));
    Pruebas::igual(3,preg_match_all('/name="horarios\[\d+\]\[dia\]"/',$html));
    $vacio=\App\Core\View::capturar('fila:horarios',['dep'=>$dep,'horarios'=>[],'error'=>'']);
    Pruebas::igual(0,preg_match_all('/name="horarios\[\d+\]\[dia\]"/',$vacio));
});
Pruebas::caso('horarios valida y muestra dias inhabiles',function () {
    $filas=\App\Modules\Fila\HorariosController::validarInhabiles([
        ['activo'=>1,'fecha'=>'2030-09-16','motivo'=>'Día festivo'],
        ['activo'=>1,'fecha'=>'2030-09-17','motivo'=>''],
    ],'2030-01-01');
    Pruebas::igual(2,count($filas));
    Pruebas::igual('Día inhábil',$filas[1]['motivo']);
    $dep=base_de_prueba();
    $html=\App\Core\View::capturar('fila:horarios',[
        'dep'=>$dep,'horarios'=>[],'inhabiles'=>$filas,'error'=>'']);
    Pruebas::cierto(str_contains($html,'Agregar día inhábil'));
    Pruebas::igual(2,preg_match_all('/name="inhabiles\[\d+\]\[fecha\]"/',$html));
});
Pruebas::caso('inhabilitar un intervalo conserva y cierra todo el dia semanal',function () {
    $filas=\App\Modules\Fila\HorariosController::validar([
        ['activo'=>1,'dia'=>1,'inicio'=>'09:00','fin'=>'11:00','inhabil'=>1],
        ['activo'=>1,'dia'=>1,'inicio'=>'12:00','fin'=>'14:00','inhabil'=>0],
        ['activo'=>1,'dia'=>2,'inicio'=>'09:00','fin'=>'14:00','inhabil'=>0],
    ]);
    Pruebas::igual(0,$filas[0]['activo']);
    Pruebas::igual(0,$filas[1]['activo']);
    Pruebas::igual(1,$filas[2]['activo']);
    Pruebas::cierto(dia_semana_inhabil($filas,1));
    Pruebas::igual(false,dia_semana_inhabil($filas,2));
});
Pruebas::caso('el enlace privado del turno omite modalidad y minutas sin exponer otros turnos',function () {
    $dep=base_de_prueba();
    $t=crear_turno($dep,'Persona Prueba','persona@uaemex.mx','Consulta',5);
    $otro=crear_turno($dep,'Otra Persona','otra@uaemex.mx','Consulta',5);
    consulta('INSERT INTO fila_expedientes (dependencia_id,turno_id,resumen,archivo,nombre_archivo,usuario_id,creado) VALUES (?,?,?,?,?,?,?)',
        [$dep['id'],$t['id'],'Resumen <seguro>','subidas/expedientes/1/prueba.pdf','Minuta.pdf',4,ahora_texto()]);
    $id=(int)db()->lastInsertId();
    Pruebas::igual(1,count(minutas_del_turno($t)));
    Pruebas::igual([],minutas_del_turno($otro));
    Pruebas::igual('Minuta.pdf',minuta_del_enlace($t['token'],$id)['nombre_archivo']);
    Pruebas::igual(null,minuta_del_enlace($otro['token'],$id));
    Pruebas::igual(null,minuta_del_enlace('token-no-valido',$id));
    $html=parcial('mi_turno',['dep'=>$dep,'turno'=>[...$t,'estado'=>'atendido'],
        'estado'=>fila_estado($dep),'adelante'=>0,'minutas'=>minutas_del_turno($t)]);
    Pruebas::igual(false,str_contains($html,'Atención presencial'));
    Pruebas::igual(false,str_contains($html,'Minutas de tu atención'));
    Pruebas::igual(false,str_contains($html,'Resumen &lt;seguro&gt;'));
    Pruebas::igual(false,str_contains($html,'/t/'.$t['token'].'/minuta/'.$id));
    Pruebas::cierto(str_contains($html,'class="boton boton-bloque"'));
    Pruebas::cierto(str_contains($html,'Ver la fila completa'));
    ob_start();
    render('mi_turno',['dep'=>$dep,'turno'=>[...$t,'estado'=>'atendido'],
        'estado'=>fila_estado($dep),'adelante'=>0],['titulo'=>'Tu turno','ocultar_plan'=>true]);
    $documento=(string)ob_get_clean();
    Pruebas::igual(false,str_contains($documento,'Plan de Desarrollo FI'));
    $publico=parcial('pantalla',['dep'=>$dep,'estado'=>fila_estado($dep)]);
    Pruebas::igual(false,str_contains($publico,'Resumen'));
});
Pruebas::caso('la minuta notifica al correo del turno y el reenvío no crea otra minuta',function () {
    $dep=base_de_prueba();
    $t=crear_turno($dep,'Persona Prueba','persona@uaemex.mx','Consulta',5);
    $id=\App\Modules\Fila\ExpedienteController::registrar($dep,(int)$t['id'],'Resumen de prueba');
    Pruebas::cierto(\App\Modules\Fila\ExpedienteController::notificar($dep,$id));
    $mail=end($GLOBALS['_correos_enviados']);
    Pruebas::igual('persona@uaemex.mx',$mail['para']);
    Pruebas::cierto(str_contains($mail['cuerpo'],'/t/'.$t['token']));
    Pruebas::cierto(\App\Modules\Fila\ExpedienteController::notificar($dep,$id));
    Pruebas::igual(1,count(minutas_del_turno($t)));
    Pruebas::igual(false,\App\Modules\Fila\ExpedienteController::notificar([...$dep,'id'=>999],$id));
});
Pruebas::caso('filtra los turnos propios por fecha y departamento con recientes primero',function () {
    $dep=base_de_prueba();
    consulta('INSERT INTO dependencias (clave,nombre,titular,dominio_correo,activa,creado) VALUES (?,?,?,?,1,?)',
        ['otra','Otra oficina','Otro titular','uaemex.mx',ahora_texto()]);
    $otra=dependencia('otra');
    $viejo=crear_turno($dep,'Persona Prueba','persona@uaemex.mx','Consulta',5);
    $nuevo=crear_turno($otra,'Persona Prueba','persona@uaemex.mx','Consulta',5);
    crear_turno($dep,'Otra Persona','otra@uaemex.mx','Consulta',5);
    consulta('UPDATE turnos SET fecha=?,creado=? WHERE id=?',['2026-01-01','2026-01-01 09:00:00',$viejo['id']]);
    consulta('UPDATE turnos SET fecha=?,creado=? WHERE id=?',['2026-02-01','2026-02-01 09:00:00',$nuevo['id']]);
    \App\Modules\Fila\ExpedienteController::registrar($dep,(int)$viejo['id'],'Resumen');
    $listar=fn($f)=>\App\Modules\Fila\FilaController::turnosPersonales('PERSONA@uaemex.mx',$f);
    Pruebas::igual([(int)$nuevo['id'],(int)$viejo['id']],array_map(fn($t)=>(int)$t['id'],$listar([])));
    Pruebas::igual((int)$viejo['id'],(int)$listar(['orden'=>'antiguos'])[0]['id']);
    Pruebas::igual(1,count($listar(['desde'=>'2026-02-01','hasta'=>'2026-02-01'])));
    $propio=$listar(['departamento'=>(string)$dep['id']]);
    Pruebas::igual(1,count($propio));
    Pruebas::igual(1,(int)$propio[0]['cantidad_minutas']);
    Pruebas::igual([], $listar(['departamento'=>'999']));
    Pruebas::igual($listar([]),$listar(['orden'=>'SQL no permitido']));
    foreach ([['desde'=>'2026-02-31'],['desde'=>'2026-02-01','hasta'=>'2026-01-01'],['departamento'=>'1 OR 1=1']] as $invalido) {
        try { $listar($invalido); Pruebas::cierto(false); }
        catch (InvalidArgumentException $e) { Pruebas::cierto(true); }
    }
});
Pruebas::caso('modalidad presencial por defecto y Teams opcional conserva enlace privado',function () {
    $dep=base_de_prueba();
    $presencial=crear_turno($dep,'Persona','presencial@uaemex.mx','Consulta',5);
    Pruebas::igual(0,(int)$presencial['videoconferencia']);
    $video=crear_turno($dep,'Persona','video@uaemex.mx','Consulta',5,['videoconferencia'=>1]);
    Pruebas::igual(1,(int)$video['videoconferencia']);
    Pruebas::igual('',$video['enlace_teams']);
    $enlace='https://teams.microsoft.com/l/meetup-join/prueba?context=abc';
    $cita=crear_cita($dep,'Persona','cita@uaemex.mx','Consulta','',20,'2030-01-01 10:00:00',['videoconferencia'=>1,'enlace_teams'=>$enlace]);
    Pruebas::igual($enlace,$cita['enlace_teams']);
    Pruebas::igual(['videoconferencia'=>0,'enlace_teams'=>''],modalidad_de_atencion(['enlace_teams'=>'javascript:alert(1)']));
    foreach (['javascript:alert(1)','https://teams.microsoft.com.evil.test/','http://teams.microsoft.com/','https://evil.test/','https://a:b@teams.microsoft.com/'] as $invalido) {
        try { modalidad_de_atencion(['videoconferencia'=>1,'enlace_teams'=>$invalido]); Pruebas::cierto(false); }
        catch (InvalidArgumentException $e) { Pruebas::cierto(true); }
    }
    Pruebas::cierto(str_contains(texto_modalidad($video),'te llamará por Teams'));
    $publico=parcial('pantalla',['dep'=>$dep,'estado'=>fila_estado($dep)]);
    Pruebas::igual(false,str_contains($publico,$enlace));
    $directa=\App\Modules\Fila\ExpedienteController::crearDirecta($dep,['email'=>'directa@uaemex.mx','asunto'=>'Reunión','videoconferencia'=>1,'enlace_teams'=>$enlace]);
    Pruebas::igual($enlace,$directa['enlace_teams']);
});
$moduleProperty->setValue(null, $previousModules);
echo 'Integración Fila: '.(Pruebas::$ok-$integrationOkBefore).' comprobaciones correctas, '
    .(count(Pruebas::$fallas)-$integrationFailuresBefore)." fallas.\n";
