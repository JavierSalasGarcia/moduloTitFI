<?php
declare(strict_types=1);

const FILA_COOKIE_CORREO='fila_correo_recordado';

function fila_ruta_cookie(): string
{
    $ruta=(string)(parse_url((string)(config()['base_url']??'/'),PHP_URL_PATH)??'/');
    return rtrim($ruta,'/').'/';
}

function fila_correo_recordado(): string
{
    $email=u_email($_COOKIE[FILA_COOKIE_CORREO]??'');
    return filter_var($email,FILTER_VALIDATE_EMAIL) ? $email : '';
}

function fila_recordar_correo(string $email): void
{
    $email=u_email($email);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) return;
    if (!headers_sent()) setcookie(FILA_COOKIE_CORREO,$email,[
        'expires'=>time()+180*86400,'path'=>fila_ruta_cookie(),
        'secure'=>true,'httponly'=>true,'samesite'=>'Lax',
    ]);
    $_COOKIE[FILA_COOKIE_CORREO]=$email;
}

function fila_olvidar_correo(): void
{
    if (!headers_sent()) setcookie(FILA_COOKIE_CORREO,'',[
        'expires'=>time()-3600,'path'=>fila_ruta_cookie(),
        'secure'=>true,'httponly'=>true,'samesite'=>'Lax',
    ]);
    unset($_COOKIE[FILA_COOKIE_CORREO]);
}

/** El registro principal es la única fuente de nombres oficiales. */
function fila_identidad(array $dep, string $email, string $alias=''): array
{
    $email=u_email($email);
    $alias=u_limpio($alias,120);
    $real=null;
    if (!empty(config()['integrado']) && u_email_valido($email,(string)$dep['dominio_correo'])) {
        $usuario=\App\Core\Database::una('SELECT nombre FROM usuarios WHERE email=?',[$email]);
        if ($usuario) $real=(string)$usuario['nombre'];
    }
    return ['registrado'=>$real!==null,'nombre_real'=>$real,'alias'=>$real===null ? $alias : '',
        'nombre'=>$real ?? ($alias!=='' ? $alias : $email)];
}

function fila_guardar_alias(array $dep, string $email, string $alias): void
{
    $identidad=fila_identidad($dep,$email,$alias);
    if ($identidad['registrado'] || $identidad['alias']==='') return;
    $previo=fila_una('SELECT email FROM fila_aliases WHERE dependencia_id=? AND email=?',[$dep['id'],u_email($email)]);
    if ($previo) consulta('UPDATE fila_aliases SET alias=?,actualizado=? WHERE dependencia_id=? AND email=?',
        [$identidad['alias'],ahora_texto(),$dep['id'],u_email($email)]);
    else consulta('INSERT INTO fila_aliases (dependencia_id,email,alias,actualizado) VALUES (?,?,?,?)',
        [$dep['id'],u_email($email),$identidad['alias'],ahora_texto()]);
}

function fila_consultar_identidad(array $dep): never
{
    if (!csrf_valido()) { u_json(['error'=>'Formulario vencido'],419); exit; }
    $inicio=(int)($_SESSION['fila_identidad_inicio']??0);
    if ($inicio<time()-60) { $_SESSION['fila_identidad_inicio']=time(); $_SESSION['fila_identidad_consultas']=0; }
    $_SESSION['fila_identidad_consultas']=(int)($_SESSION['fila_identidad_consultas']??0)+1;
    if ($_SESSION['fila_identidad_consultas']>20) { u_json(['error'=>'Espera un minuto antes de consultar otro correo.'],429); exit; }
    $email=u_email($_POST['email']??'');
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { u_json(['error'=>'Escribe un correo válido.'],422); exit; }
    $identidad=fila_identidad($dep,$email);
    u_json(['registrado'=>$identidad['registrado'],'nombre_real'=>$identidad['nombre_real']]);
    exit;
}
