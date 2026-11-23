<?php
/** @var ?array $o @var array $dest */
use App\Core\Auth;
$accion = $o ? 'oficios/subdireccion/' . (int) $o['id'] : 'oficios/subdireccion';
?>
<style>
  .of-form label{display:block;font-weight:600;margin:0 0 4px}
  .of-lienzo{min-height:300px;padding:16px 20px;border:1px solid var(--gris-300,#cbd5e1);border-radius:8px;background:#fff;font-family:'DM Sans',Arial,sans-serif;font-size:15px;line-height:1.3;outline:none}
  .of-lienzo:focus{border-color:var(--verde-700,#3e7350);box-shadow:0 0 0 2px rgba(44,82,52,.18)}
  .of-lienzo p{margin:0 0 .06em}
  .of-lienzo table{border-collapse:collapse;margin:8px 0;width:100%}
  .of-lienzo td,.of-lienzo th{border:1px solid #888;padding:4px 6px;vertical-align:top}
  .of-lienzo:empty:before{content:attr(data-vacio);color:#7a8a7f}
  .of-barra{display:flex;gap:6px;margin:0 0 6px}
  .of-barra button{min-width:44px;min-height:40px;border:1px solid var(--gris-300,#cbd5e1);border-radius:8px;background:#fff;cursor:pointer;font-size:16px}
  .dest-chips{list-style:none;margin:0 0 8px;padding:0;display:flex;flex-wrap:wrap;gap:6px}
  .dest-chip{display:inline-flex;align-items:center;gap:6px;background:var(--verde-050,#eef4ef);border:1px solid var(--gris-100,#dfe6e0);border-radius:999px;padding:4px 6px 4px 12px;font-size:14px}
  .dest-chip button{border:0;background:none;cursor:pointer;font-size:18px;line-height:1;min-width:28px;min-height:28px}
  .dest-wrap{position:relative}
  .dest-lista{position:absolute;z-index:20;left:0;right:0;top:100%;background:#fff;border:1px solid var(--gris-300,#cbd5e1);border-radius:8px;box-shadow:0 8px 20px rgba(0,0,0,.12);max-height:260px;overflow:auto}
  .dest-op{display:block;width:100%;text-align:left;border:0;background:none;padding:10px 12px;cursor:pointer;font-size:14px}
  .dest-op:hover{background:var(--verde-050,#eef4ef)}
  .dest-nuevo{border:1px dashed var(--gris-300,#cbd5e1);border-radius:8px;padding:10px;margin-top:8px;display:grid;gap:8px}
</style>
<form id="form-oficio" class="of-form card" method="post" action="<?= e(url($accion)) ?>">
  <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
  <input type="hidden" name="cuerpo_html" id="cuerpo_html">
  <?php if ($o && $o['observacion']): ?><p class="texto-suave" style="background:#fffbeb;border:1px solid #f1e1a6;border-radius:8px;padding:10px 12px"><strong>El titular lo devolvió:</strong> <?= e($o['observacion']) ?></p><?php endif; ?>

  <div style="margin-bottom:14px">
    <label for="dest-buscar">Destinatarios</label>
    <ul class="dest-chips" id="dest-chips" aria-live="polite" data-iniciales="<?= e(json_encode($dest, JSON_UNESCAPED_UNICODE)) ?>"></ul>
    <div class="dest-wrap">
      <input id="dest-buscar" type="search" autocomplete="off" placeholder="Busca por nombre, cargo o dependencia…" style="width:100%">
      <div class="dest-lista" id="dest-lista" hidden></div>
    </div>
    <button type="button" class="btn btn-mini" id="dest-nuevo-abrir" style="margin-top:6px">+ Agregar un destinatario nuevo</button>
    <div class="dest-nuevo" id="dest-nuevo" hidden data-url="<?= e(url('oficios/subdireccion/destinatarios')) ?>">
      <input type="text" name="nombre" maxlength="160" placeholder="Nombre con su grado (Dra. Ana López Pérez)" aria-label="Nombre del destinatario">
      <input type="text" name="cargo" maxlength="255" placeholder="Cargo (Coordinadora de Docencia)" aria-label="Cargo">
      <input type="text" name="dependencia" maxlength="255" placeholder="Dependencia (opcional)" aria-label="Dependencia">
      <div><button type="button" class="btn btn-primario btn-mini" id="dest-nuevo-guardar">Guardar y agregar</button> <span id="dest-nuevo-msg" class="texto-suave" role="alert"></span></div>
    </div>
    <p class="texto-suave" style="margin:6px 0 0;font-size:13px">Los destinatarios se guardan para usarlos en otros oficios.</p>
  </div>

  <div style="margin-bottom:14px">
    <label for="asunto">Asunto</label>
    <input id="asunto" name="asunto" type="text" maxlength="250" required value="<?= e($o['asunto'] ?? '') ?>" style="width:100%">
  </div>

  <div style="margin-bottom:14px">
    <label id="lienzo-etq">Contenido del oficio</label>
    <div class="of-barra" role="toolbar" aria-label="Formato">
      <button type="button" data-cmd="bold" title="Negritas (Ctrl+B)"><b>N</b></button>
      <button type="button" data-cmd="italic" title="Cursivas (Ctrl+I)"><i>C</i></button>
    </div>
    <div id="lienzo" class="of-lienzo" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="lienzo-etq"
         data-vacio="Escribe aquí el oficio. Para una tabla, cópiala en Word o Excel y pégala con Ctrl+V."
         data-url-destinatarios="<?= e(url('oficios/subdireccion/destinatarios')) ?>" data-csrf="<?= e(Auth::csrfToken()) ?>"><?= $o ? $o['cuerpo_html'] : '' ?></div>
    <p class="texto-suave" style="margin:6px 0 0;font-size:13px">Solo hay negritas y cursivas. El interlineado es sencillo y el espacio entre párrafos es de 0.8 pt. La fuente de todos los oficios es DM Sans.</p>
  </div>

  <div class="const-acciones" style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn btn-primario" type="submit">Guardar borrador</button>
    <a class="btn" href="<?= e(url($o ? 'oficios/subdireccion/' . (int) $o['id'] : 'oficios/subdireccion')) ?>">Cancelar</a>
  </div>
</form>
<script src="<?= e(url('assets/js/oficios-lienzo.js?v=' . (string) filemtime(dirname(__DIR__, 4) . '/public/assets/js/oficios-lienzo.js'))) ?>"></script>
