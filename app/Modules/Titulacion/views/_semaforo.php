<?php
/** Semáforo de una asignación. Variable: $a (con 'semaforo', 'restan'). */
$s = $a['semaforo'];
$txt = match ($s) {
    'listo' => 'Firmada ' . ($a['firmada_en'] ? date('d/m', strtotime((string) $a['firmada_en'])) : ''),
    'verde' => 'Día hábil ' . max(0, (int) ($a['transcurridos'] ?? 0)) . ' de ' . \App\Modules\Titulacion\Services\Calendario::diasPlazo(),
    'rojo' => 'Día hábil ' . (int) ($a['transcurridos'] ?? 0) . ' · plazo vencido',
    default => 'Cancelada',
};
?>
<span class="sem <?= e($s) ?>" aria-hidden="true"></span><span><?= e(trim($txt)) ?></span>
