<?php
/** @var array $r @var array $a @var array $profesores Caso cerrado (acta firmada subida): solo lectura. */
use App\Modules\Consejos\Services\Texto;
include __DIR__ . '/_estilos.php';
$clave = Texto::clave($a['grupo'], (int) $a['numero']);
?>
<div class="cns-pila">
  <section class="tarjeta">
    <p class="cns-sub" style="margin:0"><a href="<?= e(url('consejos/reunion/' . $r['id'])) ?>">← <?= e($r['etiqueta']) ?></a></p>
    <h1>Caso <?= e($clave) ?> <span class="cns-etq ok">Cerrado</span></h1>
    <p class="cns-sub">El acta firmada y sellada de esta reunión ya está subida, así que el caso solo se consulta. Para corregirlo, retira primero el acta escaneada en la reunión.</p>
  </section>
  <section class="tarjeta cns-doc">
    <h2>Solicitud</h2>
    <?= $a['solicitud_html'] /* HTML ya limpiado al guardar */ ?>
    <?php if (trim((string) $a['antecedentes_html']) !== ''): ?><h2>Documentos y/o antecedentes</h2><?= $a['antecedentes_html'] ?><?php endif; ?>
    <?php if (trim((string) ($a['acuerdo_academico'] ?? '')) !== ''): ?><h2>Acuerdo del H. Consejo Académico</h2><p><?= e((string) $a['acuerdo_academico']) ?></p><?php endif; ?>
    <?php if (trim((string) ($a['acuerdo_gobierno'] ?? '')) !== ''): ?><h2>Acuerdo del H. Consejo de Gobierno</h2><p><?= e((string) $a['acuerdo_gobierno']) ?></p><?php endif; ?>
    <?php if (!empty($adjuntos)): ?><h2>Documentos del caso</h2><?php $lista = $adjuntos; $quitar = false; include __DIR__ . '/_adjuntos_lista.php'; ?><?php endif; ?>
    <?php if ($profesores): ?><h2>Profesores mencionados</h2><p><?= e(implode(', ', array_column($profesores, 'nombre'))) ?></p><?php endif; ?>
  </section>
</div>
