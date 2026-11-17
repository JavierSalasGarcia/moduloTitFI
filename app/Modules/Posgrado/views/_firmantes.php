<?php
/**
 * Estado por firmante de un documento firmable, con «Reenviar enlace» y «Corregir correo» para quien no ha firmado.
 * Espera: $docFirmas (Firmas::estado()[doc] o null), $t, $gestiona (bool: egresado con trámite abierto o personal que
 * opera), $volver (ancla a la que regresar), $fechaExamen (para la regla de vigencia del visto bueno).
 */
use App\Core\Auth;
use App\Modules\Perfil\FirmaUaemex;
use App\Modules\Posgrado\Catalogo;
use App\Modules\Posgrado\Services\Firmas;

if (empty($docFirmas['firmas'])) {
    return;
}
$fechaEx = (string) ($fechaExamen ?? '');
?>
<ul class="pos-firmantes">
  <?php foreach ($docFirmas['firmas'] as $f):
      $firmada = $f['estado'] === 'firmada';
      $porEnviar = !$firmada && in_array($f['rol'], ['director', 'sinodal'], true) && $f['enviada_en'] === null;
      $r = is_array($f['respuestas']) ? $f['respuestas'] : [];
      $vig = (string) ($r['vigencia'] ?? '');
      $corto = $f['documento'] === 'visto_bueno' && $firmada && $fechaEx !== '' && !Firmas::vigenciaAlcanza($vig !== '' ? $vig : null, $fechaEx);
      $no = array_filter(array_intersect_key($r, Catalogo::preguntasVistoBueno()), static fn($v): bool => $v === 'no'); ?>
    <li class="<?= $corto || $no ? 'pos-firmante-alerta' : '' ?>">
      <div>
        <strong><?= e((string) $f['nombre']) ?></strong> <small class="texto-secundario"><?= e(Firmas::etiquetaRol($f)) ?></small>
        <?php if ($firmada): ?>
          <span class="pos-estado pos-e-ok"><?= $f['documento'] === 'acuse' && $f['rol'] === 'sinodal' ? 'Acusó de recibido' : 'Firmó' ?> <?= e(date('d/m/Y H:i', strtotime((string) $f['firmada_en']))) ?></span>
        <?php elseif ($f['rol'] === 'coordinacion'): ?>
          <span class="pos-estado pos-e-rev">En firma de la Coordinación</span>
        <?php elseif ($porEnviar): ?>
          <span class="pos-estado pos-e-pend">Se enviará cuando firme la Coordinación</span>
        <?php else: ?>
          <span class="pos-estado pos-e-rev">Pendiente · enviada el <?= e(date('d/m/Y', strtotime((string) $f['enviada_en']))) ?></span>
        <?php endif; ?>
        <?php if ($f['email']): ?><br><small class="texto-secundario"><?= e((string) $f['email']) ?></small><?php endif; ?>
        <?php if ($f['documento'] === 'visto_bueno' && $firmada): ?>
          <br><small><?php foreach (Catalogo::preguntasVistoBueno() as $k => $p): ?><?= e($p) ?>: <strong class="<?= ($r[$k] ?? '') === 'no' ? 'pos-no' : '' ?>"><?= ($r[$k] ?? '') === 'si' ? 'Sí' : (($r[$k] ?? '') === 'no' ? 'No' : '—') ?></strong> · <?php endforeach; ?>
          Firma UAEMéx vigente al <strong><?= $vig !== '' ? e(FirmaUaemex::fechaLarga($vig)) : '—' ?></strong></small>
          <?php if ($corto): ?><p class="pos-alerta-linea">Firma UAEMéx de <?= e((string) $f['nombre']) ?> vence el <?= e($vig !== '' ? FirmaUaemex::fechaLarga($vig) : '—') ?>: debe renovarla (vigente al menos hasta el <?= e(FirmaUaemex::fechaLarga(FirmaUaemex::masUnMes($fechaEx))) ?>).</p><?php endif; ?>
        <?php endif; ?>
      </div>
      <?php if ($gestiona && in_array($f['rol'], ['director', 'sinodal'], true) && !$porEnviar && (!$firmada || $corto)): ?>
        <div class="pos-doc-acciones" style="grid-column:auto;margin-top:4px">
          <form method="post" action="<?= url('posgrado/tramite/' . (int) $t['id'] . '/firma/' . (int) $f['id'] . '/reenviar') ?>">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="volver" value="<?= e((string) $volver) ?>">
            <button class="btn btn-mini" type="submit"><?= $corto ? 'Reenviar enlace para actualizar su vigencia' : 'Reenviar enlace' ?></button>
          </form>
          <?php if (!$firmada): ?>
          <details class="pos-reemplazar"><summary>Corregir su correo</summary>
            <form method="post" action="<?= url('posgrado/tramite/' . (int) $t['id'] . '/firma/' . (int) $f['id'] . '/correo') ?>">
              <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="volver" value="<?= e((string) $volver) ?>">
              <label class="sr-only" for="correo-<?= (int) $f['id'] ?>">Correo correcto de <?= e((string) $f['nombre']) ?></label>
              <input id="correo-<?= (int) $f['id'] ?>" type="email" name="email" maxlength="120" required value="<?= e((string) $f['email']) ?>">
              <button class="btn btn-mini btn-primario" type="submit">Guardar y enviar</button>
            </form>
          </details>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
