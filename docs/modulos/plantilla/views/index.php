<section class="card">
  <p>Contenido del módulo independiente.</p>
  <form method="post" action="<?= url('ejemplo') ?>">
    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <button class="btn btn-primario" type="submit">Guardar</button>
  </form>
</section>
