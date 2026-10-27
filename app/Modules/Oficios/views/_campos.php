<?php
/** Campos comunes del formulario de alta y edición. $o es null al crear. */
$o ??= null;
$v = fn(string $campo, string $defecto = '') => e((string) ($o[$campo] ?? $defecto));
?>
<?php if (!$o && !empty($esAdmin)): ?>
  <label>Coordinación o departamento que envía <span class="req">*</span>
    <select name="departamento_id" required>
      <option value="">Selecciona</option>
      <?php foreach ($departamentos as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e($d['nombre']) ?></option><?php endforeach; ?>
    </select>
  </label>
<?php endif; ?>
<label>Tipo de documento <span class="req">*</span>
  <select name="tipo" required>
    <?php foreach ($tipos as $clave => $nombre): ?><option value="<?= e($clave) ?>" <?= ($o['tipo'] ?? 'memorandum') === $clave ? 'selected' : '' ?>><?= e($nombre) ?></option><?php endforeach; ?>
  </select>
</label>
<label>Folio <span class="req">*</span>
  <input name="folio" required maxlength="60" value="<?= $v('folio') ?>" placeholder="Ej. SA/002/FI/2026">
</label>
<label>Fecha del documento <span class="req">*</span>
  <input type="date" name="fecha" required value="<?= $v('fecha', date('Y-m-d')) ?>">
</label>
<label>Asunto <span class="req">*</span>
  <input name="asunto" required maxlength="250" value="<?= $v('asunto') ?>" placeholder="Ej. Suspensión temporal en servicio de salas de cómputo">
</label>
<label>Dirigido a <span class="req">*</span>
  <input name="destinatarios" required maxlength="500" value="<?= $v('destinatarios') ?>" placeholder="Ej. Coordinadores de Docencia de la Facultad de Ingeniería">
</label>
<label>Firmante
  <input name="firmante" maxlength="160" value="<?= $v('firmante') ?>" placeholder="Nombre y cargo de quien firma">
</label>
<label>Notas internas
  <textarea name="notas" rows="3" maxlength="4000" placeholder="Contexto, seguimiento o documentos relacionados (opcional)"><?= $v('notas') ?></textarea>
</label>
