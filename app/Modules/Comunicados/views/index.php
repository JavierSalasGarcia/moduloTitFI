<?php use App\Core\Auth; ?>
<div class="barra-acciones">
  <p class="texto-suave">Avisos oficiales dirigidos a la comunidad estudiantil, filtrados según tu programa.</p>
  <?php if ($puedeEmitir): ?>
    <a class="btn btn-primario" href="<?= url('comunicados/nuevo') ?>">Nuevo comunicado</a>
  <?php endif; ?>
</div>

<div style="margin-bottom: 20px;">
  <div class="buscador" style="max-width: 400px; margin: 0;">
    <?= icono('buscar', 16) ?>
    <input type="text" id="buscador-comunicados" placeholder="Buscar comunicado por título, contenido o emisor..." autocomplete="off">
  </div>
</div>

<?php if (!$comunicados): ?>
  <div class="card" id="sin-comunicados-banner"><p class="texto-suave">No hay comunicados publicados.</p></div>
<?php else: ?>
  <div class="card" id="sin-comunicados-banner" style="display: none;"><p class="texto-suave">No se encontraron comunicados que coincidan con tu búsqueda.</p></div>
<?php endif; ?>

<div id="contenedor-comunicados">
<?php foreach ($comunicados as $c): ?>
  <article class="card comunicado-card" data-categoria="<?= e($c['categoria']) ?>">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2 style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <?= e($c['titulo']) ?>
          <?php if ($c['categoria'] === 'Importantes'): ?>
            <span class="badge badge-oro" style="background: #fef3c7; color: #d97706; border-color: #fde68a;">⭐ Importante</span>
          <?php elseif ($c['categoria'] === 'Ocultar'): ?>
            <span class="badge" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;">👁️ Oculto</span>
          <?php endif; ?>
        </h2>
        <small class="texto-suave" style="display: block; margin-top: 4px;"><?= e($c['depto']) ?> · <?= e($c['autor']) ?> · <?= e(fecha_corta($c['creado_en'])) ?></small>
      </div>
      <div class="grupo-chips">
        <?php if ($c['alcance'] === 'programa'): ?>
          <span class="badge badge-verde">Programa <?= e($c['siglas']) ?></span>
        <?php else: ?>
          <span class="badge badge-oro"><?= e(ucfirst($c['alcance'])) ?></span>
        <?php endif; ?>
        <?php if (Auth::rol() === 'admin' || (int) $c['depto_id'] === Auth::departamentoId()): ?>
          <form method="post" action="<?= url('comunicados/' . $c['id'] . '/eliminar') ?>" data-confirm="¿Retirar este comunicado?">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <button type="submit" class="btn btn-mini btn-peligro">Retirar</button>
          </form>
        <?php endif; ?>
      </div>
    </header>
    <p style="margin:0; padding-bottom: 12px;"><?= nl2br(e($c['contenido'])) ?></p>

    <?php if ($puedeEmitir && (Auth::rol() === 'admin' || (int) $c['depto_id'] === Auth::departamentoId())): ?>
      <div style="margin-top: 12px; display: flex; align-items: center; gap: 8px; font-size: 12px; border-top: 1px dashed var(--gris-100); padding-top: 10px;">
        <span style="color: var(--gris-600); font-weight: 500;">Categoría del Comunicado:</span>
        <form method="post" action="<?= url('comunicados/' . $c['id'] . '/categoria') ?>" style="display:inline-flex; align-items:center;">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <select name="categoria" onchange="this.form.submit()" style="font-size:11px; padding: 2px 6px; border-radius:4px; border:1px solid var(--gris-100); cursor: pointer; background: #fff;">
            <option value="General" <?= $c['categoria'] === 'General' ? 'selected' : '' ?>>General</option>
            <option value="Importantes" <?= $c['categoria'] === 'Importantes' ? 'selected' : '' ?>>Importantes (Destacar)</option>
            <option value="Ocultar" <?= $c['categoria'] === 'Ocultar' ? 'selected' : '' ?>>Ocultar (Borrador/Oculto)</option>
          </select>
        </form>
      </div>
    <?php endif; ?>
  </article>
<?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('buscador-comunicados');
  const banner = document.getElementById('sin-comunicados-banner');
  const cards = document.querySelectorAll('.comunicado-card');

  if (input) {
    input.addEventListener('input', function() {
      const query = this.value.toLowerCase().trim();
      let visibles = 0;

      cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(query)) {
          card.style.display = 'block';
          visibles++;
        } else {
          card.style.display = 'none';
        }
      });

      if (visibles === 0) {
        banner.style.display = 'block';
      } else {
        banner.style.display = 'none';
      }
    });
  }
});
</script>
