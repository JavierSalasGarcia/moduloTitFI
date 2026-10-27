<?php
/**
 * Visor de calaveritas: un libro con hojas que se voltean, sobre un fondo de Día de Muertos.
 * Variables: $obras (numero, titulo, texto, autor opcional), $favoritos (números), $puedeMarcar, $puedeVotar, $baseUrl, $csrf, $propias.
 */
$propias = array_map('intval', $propias ?? []);
$favoritos = array_map('intval', $favoritos ?? []);
$total = count($obras);
?>
<style>
.lb-escena{position:relative;overflow:hidden;border-radius:16px;padding:22px 14px 26px;color:#fdf3dc;
  background:
    radial-gradient(ellipse at 50% 120%,rgba(255,150,30,.55),transparent 60%),
    radial-gradient(circle at 12% 18%,rgba(255,190,60,.18) 0 6px,transparent 7px) 0 0/90px 90px,
    radial-gradient(circle at 62% 70%,rgba(255,120,30,.16) 0 8px,transparent 9px) 0 0/130px 130px,
    linear-gradient(180deg,#1d1030 0%,#2b1647 55%,#3a1b3f 100%)}
.lb-papel{position:absolute;top:0;left:0;right:0;height:46px;display:flex;pointer-events:none}
.lb-papel i{flex:1;margin:0 2px;border-radius:0 0 12px 12px;opacity:.88;clip-path:polygon(0 0,100% 0,100% 70%,80% 100%,60% 70%,40% 100%,20% 70%,0 100%)}
.lb-papel i:nth-child(5n+1){background:#f08a24}.lb-papel i:nth-child(5n+2){background:#d6336c}.lb-papel i:nth-child(5n+3){background:#7c3aed}.lb-papel i:nth-child(5n+4){background:#2fb5a0}.lb-papel i:nth-child(5n){background:#f5c518}
.lb-titulo{text-align:center;margin:30px 0 4px;font-family:Georgia,serif;font-size:22px;color:#ffd166;text-shadow:0 2px 8px rgba(0,0,0,.45)}
.lb-sub{text-align:center;margin:0 0 16px;font-size:13.5px;opacity:.85}
.lb-libro{position:relative;width:min(460px,calc(50% - 14px));height:min(72vh,560px);margin:0 auto 0 50%;perspective:1800px}
.lb-libro::before{content:"";position:absolute;inset:6px -10px -10px calc(-100% - 12px);background:#5a3418;border-radius:6px 14px 14px 6px;box-shadow:0 18px 36px rgba(0,0,0,.55)}
.lb-hoja{position:absolute;inset:0;transform-origin:left center;transform-style:preserve-3d;transition:transform .95s cubic-bezier(.45,.05,.25,1);cursor:default}
.lb-hoja.volteada{transform:rotateY(-178deg)}
.lb-cara{position:absolute;inset:0;backface-visibility:hidden;-webkit-backface-visibility:hidden;border-radius:4px 12px 12px 4px;overflow:hidden;
  background:linear-gradient(100deg,#e9d5a8 0,#fbf1d6 7%,#fdf6e3 40%,#f6e8c4 100%);color:#3b2a1a;box-shadow:inset 8px 0 14px -8px rgba(80,50,10,.45)}
.lb-cara.dorso{transform:rotateY(180deg);background:linear-gradient(260deg,#e9d5a8 0,#fbf1d6 9%,#f3e2b8 100%);display:flex;align-items:center;justify-content:center;font-size:64px;opacity:.9}
.lb-pagina{display:flex;flex-direction:column;height:100%;padding:22px 24px 16px 30px;box-sizing:border-box}
.lb-num{font:700 12px/1 system-ui,sans-serif;letter-spacing:.12em;text-transform:uppercase;color:#a0522d}
.lb-pagina h3{margin:6px 0 10px;font-family:Georgia,serif;font-size:22px;line-height:1.2;color:#4a2511}
.lb-verso{flex:1;overflow:auto;white-space:pre-wrap;font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.55;padding-right:6px}
.lb-pie{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:10px;padding-top:8px;border-top:1px dashed #cbb27a}
.lb-estrella{border:0;background:none;font-size:30px;line-height:1;cursor:pointer;color:#b08a2e;padding:2px 6px;transition:transform .2s}
.lb-estrella:hover{transform:scale(1.2)}.lb-estrella.on{color:#f5a300;text-shadow:0 0 10px rgba(245,163,0,.7)}
.lb-estrella[disabled]{opacity:.4;cursor:not-allowed}
.lb-nav{display:flex;align-items:center;justify-content:center;gap:14px;margin:20px 0 0;flex-wrap:wrap}
.lb-nav button{border:0;border-radius:999px;padding:9px 18px;font-weight:700;background:#ffd166;color:#3b1f00;cursor:pointer}
.lb-nav button[disabled]{opacity:.35;cursor:default}
.lb-cuenta{font-variant-numeric:tabular-nums;font-size:14px}
.lb-favs{margin:22px auto 0;max-width:640px;background:rgba(0,0,0,.28);border:1px solid rgba(255,209,102,.35);border-radius:12px;padding:12px 14px}
.lb-favs h4{margin:0 0 8px;color:#ffd166;font-family:Georgia,serif;font-size:17px}
.lb-fav{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:6px 0;border-top:1px solid rgba(255,255,255,.12)}
.lb-fav:first-of-type{border-top:0}.lb-fav span{flex:1;min-width:140px}
.lb-fav button,.lb-fav .lb-voto{border:0;border-radius:8px;padding:5px 11px;font-weight:600;cursor:pointer;background:rgba(255,255,255,.14);color:#fdf3dc}
.lb-fav .lb-voto{background:#f08a24;color:#2a1200}
.lb-aviso{margin:0;font-size:13.5px;opacity:.9}
@media (max-width:720px){.lb-libro{width:min(100%,460px);margin:0 auto}.lb-libro::before{inset:6px -10px -10px 6px}.lb-hoja.volteada{transform:rotateY(-100deg);opacity:0;pointer-events:none}.lb-libro{height:min(70vh,520px)}.lb-pagina h3{font-size:19px}}
@media (prefers-reduced-motion:reduce){.lb-hoja{transition:opacity .2s}.lb-hoja.volteada{transform:none;opacity:0;pointer-events:none}}
</style>

<div class="lb-escena" id="libro-calaveritas">
  <div class="lb-papel" aria-hidden="true"><?php for ($i = 0; $i < 18; $i++): ?><i></i><?php endfor; ?></div>
  <h3 class="lb-titulo">☠ El libro de las calaveritas ☠</h3>
  <p class="lb-sub"><?= $puedeMarcar ? 'Voltea las hojas, marca con ★ las que más te gusten y al final elige la mejor entre tus favoritas.' : 'Voltea las hojas para leer las calaveritas participantes.' ?></p>

  <?php if ($total === 0): ?>
    <p class="lb-sub">Aún no hay calaveritas en la votación.</p>
  <?php else: ?>
  <div class="lb-libro" id="lb-libro" tabindex="0" aria-label="Libro de calaveritas: usa las flechas del teclado para pasar las hojas">
    <?php foreach ($obras as $i => $o): $n = (int) $o['numero']; ?>
      <div class="lb-hoja" data-i="<?= $i ?>" data-n="<?= $n ?>" style="z-index:<?= $total - $i ?>">
        <div class="lb-cara">
          <div class="lb-pagina">
            <div class="lb-num">Calaverita N.º <?= $n ?> · <?= $i + 1 ?> de <?= $total ?></div>
            <h3><?= e($o['titulo']) ?></h3>
            <div class="lb-verso"><?= e($o['texto']) ?></div>
            <div class="lb-pie">
              <?php if (in_array($n, $propias, true)): ?>
                <small>Es tu obra.</small>
              <?php elseif ($puedeMarcar): ?>
                <small>Agregar a favoritas</small>
                <button type="button" class="lb-estrella <?= in_array($n, $favoritos, true) ? 'on' : '' ?>" data-fav="<?= $n ?>" aria-pressed="<?= in_array($n, $favoritos, true) ? 'true' : 'false' ?>" aria-label="Favorita: calaverita <?= $n ?>"><?= in_array($n, $favoritos, true) ? '★' : '☆' ?></button>
              <?php else: ?><small>&nbsp;</small><?php endif; ?>
            </div>
          </div>
        </div>
        <div class="lb-cara dorso" aria-hidden="true">🌼</div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="lb-nav">
    <button type="button" id="lb-ant" disabled>◀ Anterior</button>
    <span class="lb-cuenta" id="lb-cuenta">1 de <?= $total ?></span>
    <button type="button" id="lb-sig">Siguiente ▶</button>
  </div>
  <?php endif; ?>

  <?php if ($puedeMarcar): ?>
  <div class="lb-favs" id="lb-favs">
    <h4>★ Mis favoritas (<span id="lb-nfav"><?= count($favoritos) ?></span>)</h4>
    <?php if ($puedeVotar): ?><p class="lb-aviso">Cuando termines de leer, elige <strong>la mejor</strong> entre tus favoritas. Tienes un solo voto y es anónimo.</p><?php endif; ?>
    <div id="lb-lista-favs">
      <?php foreach ($obras as $i => $o): $n = (int) $o['numero']; if (!in_array($n, $favoritos, true)) continue; ?>
        <div class="lb-fav" data-fav-fila="<?= $n ?>">
          <span>N.º <?= $n ?> · <?= e($o['titulo']) ?></span>
          <button type="button" data-ir="<?= $i ?>">Leer</button>
          <?php if ($puedeVotar): ?>
          <form method="post" action="<?= url($baseUrl . '/votar') ?>" onsubmit="return confirm('¿Elegir la calaverita N.º <?= $n ?> como la mejor? Solo tienes un voto.')" style="margin:0">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="numero" value="<?= $n ?>">
            <button class="lb-voto" type="submit">🏆 Es la mejor</button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="lb-aviso" id="lb-sin-favs" <?= $favoritos ? 'style="display:none"' : '' ?>>Todavía no marcas ninguna. Toca la ☆ de las calaveritas que te gusten.</p>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var libro = document.getElementById('lb-libro');
  if (!libro) return;
  var hojas = Array.prototype.slice.call(libro.querySelectorAll('.lb-hoja'));
  var actual = 0, total = hojas.length;
  var ant = document.getElementById('lb-ant'), sig = document.getElementById('lb-sig'), cuenta = document.getElementById('lb-cuenta');
  var temporizadores = {};
  function zFinal(k) { return k < actual ? k + 1 : total - k + total; }
  function mostrar(i) {
    var previo = actual;
    actual = Math.max(0, Math.min(total - 1, i));
    hojas.forEach(function (h, k) {
      var cambia = h.classList.contains('volteada') !== (k < actual);
      h.classList.toggle('volteada', k < actual);
      h.setAttribute('aria-hidden', k === actual ? 'false' : 'true');
      if (cambia) {
        // La hoja que gira va por encima de todas mientras dura el giro y luego se acomoda en su pila.
        h.style.zIndex = 3 * total;
        clearTimeout(temporizadores[k]);
        temporizadores[k] = setTimeout(function () { h.style.zIndex = zFinal(k); }, 950);
      } else if (!temporizadores[k]) {
        h.style.zIndex = zFinal(k);
      }
    });
    ant.disabled = actual === 0; sig.disabled = actual === total - 1;
    cuenta.textContent = (actual + 1) + ' de ' + total;
  }
  ant.addEventListener('click', function () { mostrar(actual - 1); });
  sig.addEventListener('click', function () { mostrar(actual + 1); });
  libro.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowRight') { mostrar(actual + 1); e.preventDefault(); }
    if (e.key === 'ArrowLeft') { mostrar(actual - 1); e.preventDefault(); }
  });
  var x0 = null;
  libro.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
  libro.addEventListener('touchend', function (e) {
    if (x0 === null) return;
    var dx = e.changedTouches[0].clientX - x0; x0 = null;
    if (Math.abs(dx) > 50) mostrar(actual + (dx < 0 ? 1 : -1));
  });
  document.addEventListener('click', function (e) {
    var ir = e.target.closest && e.target.closest('[data-ir]');
    if (ir) { mostrar(parseInt(ir.dataset.ir, 10)); libro.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  });
  mostrar(0);

<?php if ($puedeMarcar): ?>
  var titulos = {};
  hojas.forEach(function (h, k) { titulos[h.dataset.n] = { i: k, t: h.querySelector('h3').textContent }; });
  var url = <?= json_encode(url($baseUrl . '/favorito')) ?>, csrf = <?= json_encode((string) $csrf) ?>, puedeVotar = <?= $puedeVotar ? 'true' : 'false' ?>, base = <?= json_encode(url($baseUrl . '/votar')) ?>;
  function pintarFavs(lista) {
    var cont = document.getElementById('lb-lista-favs'); cont.innerHTML = '';
    lista.forEach(function (n) {
      var d = titulos[n]; if (!d) return;
      var fila = document.createElement('div'); fila.className = 'lb-fav';
      var s = document.createElement('span'); s.textContent = 'N.º ' + n + ' · ' + d.t; fila.appendChild(s);
      var b = document.createElement('button'); b.type = 'button'; b.dataset.ir = d.i; b.textContent = 'Leer'; fila.appendChild(b);
      if (puedeVotar) {
        var f = document.createElement('form'); f.method = 'post'; f.action = base; f.style.margin = '0';
        f.onsubmit = function () { return confirm('¿Elegir la calaverita N.º ' + n + ' como la mejor? Solo tienes un voto.'); };
        f.innerHTML = '<input type="hidden" name="_csrf"><input type="hidden" name="numero"><button class="lb-voto" type="submit">🏆 Es la mejor</button>';
        f.elements[0].value = csrf; f.elements[1].value = n; fila.appendChild(f);
      }
      cont.appendChild(fila);
    });
    document.getElementById('lb-nfav').textContent = lista.length;
    document.getElementById('lb-sin-favs').style.display = lista.length ? 'none' : '';
    libro.querySelectorAll('[data-fav]').forEach(function (b) {
      var on = lista.indexOf(parseInt(b.dataset.fav, 10)) !== -1;
      b.classList.toggle('on', on); b.textContent = on ? '★' : '☆'; b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }
  libro.addEventListener('click', function (e) {
    var b = e.target.closest('[data-fav]'); if (!b) return;
    b.disabled = true;
    var fd = new FormData(); fd.append('_csrf', csrf); fd.append('numero', b.dataset.fav);
    fetch(url, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (r) { if (r.ok && r.j.ok) pintarFavs(r.j.favoritos); else alert(r.j.error || 'No se pudo guardar tu favorita.'); })
      .catch(function () { alert('No se pudo guardar tu favorita. Revisa tu conexión.'); })
      .then(function () { b.disabled = false; });
  });
<?php endif; ?>
})();
</script>
