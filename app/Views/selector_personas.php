<?php
/**
 * SelectorPersonas: cuadro para elegir personas como en Teams (docs/SELECTOR_DE_PERSONAS.md).
 * Variables: $campo (nombre del campo; con «[]» si admite varias), $url (buscador JSON), $seleccion (lista de ['id','nombre','email'] o de textos),
 * $id (opcional), $max (0 = sin límite), $libre (permite un texto que no está en la lista), $valor ('id' | 'nombre' | 'email'),
 * $placeholder y $etiqueta (opcionales), $ir (si existe, al elegir a alguien se navega a esa dirección con ?email=…; $irParam cambia el parámetro, p. ej. «id») y $min (mínimo de letras, 2 por omisión).
 */
$seleccion = $seleccion ?? [];
$max = (int) ($max ?? 0);
?>
<div class="sp" data-selector-personas<?= !empty($id) ? ' id="' . e($id) . '"' : '' ?>
     data-campo="<?= e($campo) ?>" data-url="<?= e($url) ?>" data-valor="<?= e($valor ?? 'id') ?>" data-max="<?= $max ?>" data-libre="<?= !empty($libre) ? '1' : '0' ?>"
     data-placeholder="<?= e($placeholder ?? '') ?>"<?= !empty($ir) ? ' data-ir="' . e($ir) . '" data-ir-param="' . e($irParam ?? 'email') . '"' : '' ?><?= !empty($min) ? ' data-min="' . (int) $min . '"' : '' ?> data-etiqueta="<?= e($etiqueta ?? '') ?>"
     data-seleccion="<?= e(json_encode(array_values($seleccion), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>"></div>
