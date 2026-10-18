<?php
/** Semáforo de una asignación. Variable: $a (con 'semaforo', 'restan'). */
$s = $a['semaforo'];
$txt = match ($s) {
    'listo' => 'Firmada ' . ($a['firmada_en'] ? date('d/m', strtotime((string) $a['firmada_en'])) : ''),
    'verde' => 'A tiempo · ' . (int) $a['restan'] . ' d. hábiles',
    'ambar' => 'Por vencer · ' . (int) $a['restan'] . ' d. hábiles',
    'rojo' => 'Vencida · ' . abs((int) $a['restan']) . ' d. hábiles',
    default => 'Cancelada',
};
?>
<span class="sem <?= e($s) ?>" aria-hidden="true"></span><span><?= e(trim($txt)) ?></span>
