<?php use App\Core\Auth; ?>
<div class="barra-acciones" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
  <div style="display: flex; gap: 10px; align-items: center; flex: 1; flex-wrap: wrap;">
    <div class="buscador" style="margin: 0; flex: 1;">
      <?= icono('buscar', 16) ?>
      <input type="text" id="buscador-usuarios" placeholder="Buscar por nombre, correo, rol, carrera o coordinación..." autocomplete="off">
    </div>
    <form method="get" action="<?= url('ajustes/usuarios') ?>" style="margin:0;">
      <label for="orden-usuarios" class="sr-only">Ordenar usuarios</label>
      <select name="orden" id="orden-usuarios" onchange="this.form.submit()" aria-label="Ordenar usuarios" style="min-width: 205px;">
        <option value="recientes" <?= $orden === 'recientes' ? 'selected' : '' ?>>Más recientes primero</option>
        <option value="antiguos" <?= $orden === 'antiguos' ? 'selected' : '' ?>>Más antiguos primero</option>
        <option value="nombre_asc" <?= $orden === 'nombre_asc' ? 'selected' : '' ?>>Nombre: A–Z</option>
        <option value="nombre_desc" <?= $orden === 'nombre_desc' ? 'selected' : '' ?>>Nombre: Z–A</option>
      </select>
    </form>
  </div>
  <div style="display: flex; gap: 10px;">
    <button type="button" class="btn" onclick="abrirModalExportar()" style="border-color: var(--gris-300); color: var(--gris-700);">📥 Exportar...</button>
    <button type="button" class="btn btn-primario" onclick="abrirModalCrear()">+ Nuevo Usuario</button>
  </div>
</div>

<div class="card sin-relleno" style="overflow-x: auto;">
  <table class="tabla" style="width: 100%; border-collapse: collapse; text-align: left;">
    <thead>
      <tr>
        <th style="padding: 12px 16px;">Nombre</th>
        <th style="padding: 12px 16px;">Email</th>
        <th style="padding: 12px 16px;">Rol</th>
        <th style="padding: 12px 16px;">Departamento / Coordinación</th>
        <th style="padding: 12px 16px;">Fecha de registro</th>
        <th style="padding: 12px 16px;">Estado</th>
        <th style="padding: 12px 16px; text-align: right;">Acciones</th>
      </tr>
    </thead>
    <tbody id="tabla-usuarios-body">
      <?php foreach ($usuarios as $usr): ?>
        <tr class="usuario-fila" style="border-bottom: 1px solid var(--gris-100);">
          <td class="usr-nombre" style="padding: 12px 16px; font-weight: 500;"><?= e($usr['nombre']) ?></td>
          <td class="usr-email" style="padding: 12px 16px;"><?= e($usr['email']) ?></td>
          <td style="padding: 12px 16px;"><span class="badge" style="background:#f1f5f9; color:#334155; text-transform:uppercase; font-size:10.5px;"><?= e($usr['rol']) ?></span></td>
          <td class="usr-depto" style="padding: 12px 16px;">
            <?= e($usr['depto_nombre'] ?: ($usr['programa_siglas'] ?: '—')) ?>
            <?php if ($usr['rol'] === 'coordinador' && $usr['posgrado_codigo']): ?>
              <span class="chip" style="background: var(--oro-050); color: var(--oro-texto); font-weight: bold; font-size: 11px; margin-left: 6px;">Coordinación: <?= e($usr['posgrado_codigo']) ?></span>
            <?php endif; ?>
          </td>
          <td class="usr-registro texto-suave" style="padding: 12px 16px; white-space: nowrap;">
            <time datetime="<?= e((string)$usr['creado_en']) ?>"><?= e(fecha_corta((string)$usr['creado_en'])) ?></time>
          </td>
          <td style="padding: 12px 16px;">
            <?php if ($usr['activo']): ?>
              <span class="chip estado-en_atencion" style="background:#e8f5e9; color:#2e7d32;">Activo</span>
            <?php else: ?>
              <span class="chip estado-cerrada" style="background:#ffebee; color:#c62828;">Inactivo</span>
            <?php endif; ?>
          </td>
          <td style="padding: 12px 16px; text-align: right;">
            <button type="button" class="btn btn-mini" onclick="abrirModalEditar(<?= e(json_encode($usr)) ?>)">Editar</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <tr id="sin-resultados-fila" style="display: none;">
        <td colspan="7" style="padding: 24px; text-align: center; color: var(--gris-600);">No se encontraron coordinadores o usuarios que coincidan con la búsqueda.</td>
      </tr>
    </tbody>
  </table>
</div>

<!-- MODAL EXPORTAR USUARIOS -->
<div id="modal-exportar" class="modal-fondo" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.4); z-index:1000; align-items:center; justify-content:center;">
  <div class="card" style="width:100%; max-width:450px; padding:24px; position:relative; background:#fff;">
    <header class="tarjeta-cabecera" style="margin-bottom:20px;">
      <h2>Exportar Listado de Usuarios</h2>
      <button type="button" class="btn btn-mini" onclick="cerrarModalExportar()">&times;</button>
    </header>
    <form method="get" action="<?= url('ajustes/usuarios/exportar') ?>" class="formulario">
      
      <label>Filtrar por Rol
        <select name="export_rol" id="export-rol" onchange="toggleExportFields(this.value)">
          <option value="todos">Todos los usuarios</option>
          <option value="coordinadores">Coordinadores / Personal Administrativo</option>
          <option value="profesores">Profesores</option>
          <option value="alumnos">Alumnos</option>
        </select>
      </label>

      <label id="export-contrato-wrapper">Tipo de Contratación (Profesores)
        <select name="export_contrato">
          <option value="todos">Todos los contratos</option>
          <option value="PTC">PTC (Tiempo Completo)</option>
          <option value="Medio Tiempo">Medio Tiempo (MT)</option>
          <option value="CAF">CAF (CAF)</option>
          <option value="Asignatura">Asignatura (Asig)</option>
          <option value="Técnico Académico">Técnico Académico (TA)</option>
        </select>
      </label>

      <label id="export-programa-wrapper">Programa / Carrera (Alumnos)
        <select name="export_programa">
          <option value="todos">Todos los programas</option>
          <?php foreach ($programas as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['siglas']) ?> · <?= e($p['nombre']) ?> (<?= e($p['nivel']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="acciones-formulario" style="margin-top:20px; display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" class="btn" onclick="cerrarModalExportar()">Cancelar</button>
        <button type="submit" class="btn btn-primario">📥 Descargar CSV</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL CREAR USUARIO -->
<div id="modal-crear" class="modal-fondo" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.4); z-index:1000; align-items:center; justify-content:center;">
  <div class="card" style="width:100%; max-width:500px; padding:24px; position:relative; background:#fff; max-height:90vh; overflow-y:auto;">
    <header class="tarjeta-cabecera" style="margin-bottom:20px;">
      <h2>Crear Nuevo Usuario</h2>
      <button type="button" class="btn btn-mini" onclick="cerrarModalCrear()">&times;</button>
    </header>
    <form method="post" action="<?= url('ajustes/usuarios/crear') ?>" class="formulario">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      
      <label>Nombre Completo <span class="req">*</span>
        <input type="text" name="nombre" required placeholder="Ej. Dr. Javier Salas">
      </label>

      <label>Grado académico (como se antepone al nombre)
        <input type="text" name="grado" maxlength="30" list="grados-lista" placeholder="Ej. Dr., Mtra., Ing.">
      </label>
      <label>Género
        <select name="genero"><option value="">Sin indicar</option><option value="masculino">Masculino</option><option value="femenino">Femenino</option></select>
      </label>

      <label>Correo Electrónico <span class="req">*</span>
        <input type="email" name="email" required pattern=".*[uU][aA][eE][mM][eE][xX]\.[mM][xX].*" title="El correo debe contener uaemex.mx" placeholder="Ej. jsalasg@uaemex.mx">
        <small>Debe contener uaemex.mx en alguna parte.</small>
      </label>

      <label>Contraseña <span class="req">*</span>
        <input type="password" name="password" required placeholder="Mínimo 6 caracteres">
      </label>

      <label>Rol <span class="req">*</span>
        <select name="rol" id="crear-rol" required onchange="toggleFormFields(this.value, 'crear')">
          <option value="coordinador">Coordinador de Posgrado</option>
          <option value="titular">Titular (Coordinador)</option>
          <option value="operativo">Operativo (Personal del departamento)</option>
          <option value="profesor">Profesor</option>
          <option value="alumno">Alumno</option>
          <option value="admin">Administrador General</option>
        </select>
      </label>

      <!-- Campos condicionales para Coordinador (Posgrado) -->
      <label id="crear-posgrado-wrapper" style="display:none;">Posgrado que Coordina
        <select name="posgrado_id">
          <option value="">-- Selecciona posgrado --</option>
          <?php foreach ($posgrados as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['codigo']) ?> · <?= e($p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <!-- Campos condicionales para Profesor (Contratación) -->
      <label id="crear-contratacion-wrapper" style="display:none;">Tipo de Contratación
        <select name="tipo_contratacion">
          <option value="">-- Selecciona --</option>
          <option value="PTC">PTC (Tiempo Completo)</option>
          <option value="Medio Tiempo">Medio Tiempo (MT)</option>
          <option value="CAF">CAF (CAF)</option>
          <option value="Asignatura">Asignatura (Asig)</option>
          <option value="Técnico Académico">Técnico Académico (TA)</option>
        </select>
      </label>

      <!-- Campos condicionales para Alumno (Carrera/Programa) -->
      <label id="crear-programa-wrapper" style="display:none;">Programa o Carrera
        <select name="programa_id">
          <option value="">-- Selecciona programa --</option>
          <?php foreach ($programas as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['siglas']) ?> · <?= e($p['nombre']) ?> (<?= e($p['nivel']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>

      <label id="crear-depto-wrapper">Departamento / Coordinación
        <select name="departamento_id">
          <option value="">Ninguno / Sin departamento</option>
          <?php foreach ($departamentos as $d): ?>
            <option value="<?= $d['id'] ?>"><?= e($d['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-top:10px; font-weight:normal; font-size:13px; color:var(--gris-700);">
        <input type="checkbox" name="permite_contrasena" value="1" checked>
        <span>Permitir inicio de sesión con contraseña (transición)</span>
      </label>

      <div class="acciones-formulario" style="margin-top:20px; display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" class="btn" onclick="cerrarModalCrear()">Cancelar</button>
        <button type="submit" class="btn btn-primario">Crear Usuario</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDITAR USUARIO -->
<div id="modal-editar" class="modal-fondo" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.4); z-index:1000; align-items:center; justify-content:center;">
  <div class="card" style="width:100%; max-width:500px; padding:24px; position:relative; background:#fff; max-height:90vh; overflow-y:auto;">
    <header class="tarjeta-cabecera" style="margin-bottom:20px;">
      <h2>Editar Usuario</h2>
      <button type="button" class="btn btn-mini" onclick="cerrarModalEditar()">&times;</button>
    </header>
    <form id="form-editar" method="post" action="" class="formulario">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      
      <label>Nombre Completo <span class="req">*</span>
        <input type="text" id="edit-nombre" name="nombre" required>
      </label>

      <label>Grado académico (como se antepone al nombre)
        <input type="text" id="edit-grado" name="grado" maxlength="30" list="grados-lista" placeholder="Ej. Dr., Mtra., Ing.">
      </label>
      <label>Género
        <select id="edit-genero" name="genero"><option value="">Sin indicar</option><option value="masculino">Masculino</option><option value="femenino">Femenino</option></select>
      </label>

      <label>Correo Electrónico <span class="req">*</span>
        <input type="email" id="edit-email" name="email" required pattern=".*[uU][aA][eE][mM][eE][xX]\.[mM][xX].*" title="El correo debe contener uaemex.mx">
      </label>

      <label>Contraseña (Dejar vacío para no cambiar)
        <input type="password" name="password" placeholder="Ingresa nueva contraseña si deseas cambiarla">
      </label>

      <label>Rol <span class="req">*</span>
        <select id="edit-rol" name="rol" required onchange="toggleFormFields(this.value, 'edit')">
          <option value="coordinador">Coordinador de Posgrado</option>
          <option value="titular">Titular (Coordinador)</option>
          <option value="operativo">Operativo (Personal del departamento)</option>
          <option value="profesor">Profesor</option>
          <option value="alumno">Alumno</option>
          <option value="admin">Administrador General</option>
        </select>
      </label>

      <!-- Campos condicionales para Coordinador (Posgrado) -->
      <label id="edit-posgrado-wrapper" style="display:none;">Posgrado que Coordina
        <select id="edit-posgrado" name="posgrado_id">
          <option value="">-- Selecciona posgrado --</option>
          <?php foreach ($posgrados as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['codigo']) ?> · <?= e($p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <!-- Campos condicionales para Profesor (Contratación) -->
      <label id="edit-contratacion-wrapper" style="display:none;">Tipo de Contratación
        <select id="edit-contratacion" name="tipo_contratacion">
          <option value="">-- Selecciona --</option>
          <option value="PTC">PTC (Tiempo Completo)</option>
          <option value="Medio Tiempo">Medio Tiempo (MT)</option>
          <option value="CAF">CAF (CAF)</option>
          <option value="Asignatura">Asignatura (Asig)</option>
          <option value="Técnico Académico">Técnico Académico (TA)</option>
        </select>
      </label>

      <!-- Campos condicionales para Alumno (Carrera/Programa) -->
      <label id="edit-programa-wrapper" style="display:none;">Programa o Carrera
        <select id="edit-programa" name="programa_id">
          <option value="">-- Selecciona programa --</option>
          <?php foreach ($programas as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['siglas']) ?> · <?= e($p['nombre']) ?> (<?= e($p['nivel']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>

      <label id="edit-depto-wrapper">Departamento / Coordinación
        <select id="edit-depto" name="departamento_id">
          <option value="">Ninguno / Sin departamento</option>
          <?php foreach ($departamentos as $d): ?>
            <option value="<?= $d['id'] ?>"><?= e($d['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-top:10px; font-weight:normal; font-size:13px; color:var(--gris-700);">
        <input type="checkbox" id="edit-activo" name="activo" value="1">
        <span>Usuario Activo (Permitir acceso a la plataforma)</span>
      </label>

      <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-top:6px; font-weight:normal; font-size:13px; color:var(--gris-700);">
        <input type="checkbox" id="edit-permite-contrasena" name="permite_contrasena" value="1">
        <span>Permitir inicio de sesión con contraseña (recuperación/transición)</span>
      </label>

      <div class="acciones-formulario" style="margin-top:20px; display:flex; justify-content:space-between; align-items:center;">
        <button type="submit" formAction="" id="btn-inactivar-usuario" class="btn btn-peligro" style="padding:8px 12px;">Dar de Baja</button>
        <div style="display:flex; gap:8px;">
          <button type="button" class="btn" onclick="cerrarModalEditar()">Cancelar</button>
          <button type="submit" class="btn btn-primario">Guardar Cambios</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
// Buscador en tiempo real para todas las cuentas
document.addEventListener('DOMContentLoaded', () => {
  const buscador = document.getElementById('buscador-usuarios');
  const filas = document.querySelectorAll('.usuario-fila');
  const sinResultados = document.getElementById('sin-resultados-fila');

  if (buscador) {
    buscador.addEventListener('input', function() {
      const q = this.value.trim().toLowerCase();
      let visibles = 0;

      filas.forEach(fila => {
        const nombre = fila.querySelector('.usr-nombre').textContent.toLowerCase();
        const email = fila.querySelector('.usr-email').textContent.toLowerCase();
        const depto = fila.querySelector('.usr-depto').textContent.toLowerCase();
        const registro = fila.querySelector('.usr-registro').textContent.toLowerCase();
        const rol = fila.cells[2].textContent.toLowerCase();

        if (nombre.includes(q) || email.includes(q) || depto.includes(q) || registro.includes(q) || rol.includes(q)) {
          fila.style.display = '';
          visibles++;
        } else {
          fila.style.display = 'none';
        }
      });

      sinResultados.style.display = (visibles === 0) ? '' : 'none';
    });
  }
});

// Cambiar visibilidad de campos de tipo de contratación, carrera/programa y posgrados
function toggleFormFields(rol, prefix) {
  const cWrapper = document.getElementById(`${prefix}-contratacion-wrapper`);
  const pWrapper = document.getElementById(`${prefix}-programa-wrapper`);
  const dWrapper = document.getElementById(`${prefix}-depto-wrapper`);
  const posWrapper = document.getElementById(`${prefix}-posgrado-wrapper`);

  if (rol === 'profesor') {
    if (cWrapper) cWrapper.style.display = 'block';
    if (pWrapper) pWrapper.style.display = 'none';
    if (dWrapper) dWrapper.style.display = 'block';
    if (posWrapper) posWrapper.style.display = 'none';
  } else if (rol === 'alumno') {
    if (cWrapper) cWrapper.style.display = 'none';
    if (pWrapper) pWrapper.style.display = 'block';
    if (dWrapper) dWrapper.style.display = 'none'; // Alumnos no tienen departamento laboral
    if (posWrapper) posWrapper.style.display = 'none';
  } else if (rol === 'coordinador') {
    if (cWrapper) cWrapper.style.display = 'none';
    if (pWrapper) pWrapper.style.display = 'none';
    if (dWrapper) dWrapper.style.display = 'none'; // Coordinadores no tienen depto laboral (tienen posgrado)
    if (posWrapper) posWrapper.style.display = 'block';
  } else {
    if (cWrapper) cWrapper.style.display = 'none';
    if (pWrapper) pWrapper.style.display = 'none';
    if (dWrapper) dWrapper.style.display = 'block';
    if (posWrapper) posWrapper.style.display = 'none';
  }
}

// Filtros para exportar
function toggleExportFields(rol) {
  const cWrapper = document.getElementById('export-contrato-wrapper');
  const pWrapper = document.getElementById('export-programa-wrapper');

  if (rol === 'profesores') {
    cWrapper.style.display = 'block';
    pWrapper.style.display = 'none';
  } else if (rol === 'alumnos') {
    cWrapper.style.display = 'none';
    pWrapper.style.display = 'block';
  } else if (rol === 'todos') {
    cWrapper.style.display = 'block';
    pWrapper.style.display = 'block';
  } else {
    cWrapper.style.display = 'none';
    pWrapper.style.display = 'none';
  }
}

// Modales
function abrirModalExportar() {
  document.getElementById('modal-exportar').style.display = 'flex';
  toggleExportFields(document.getElementById('export-rol').value);
}
function cerrarModalExportar() {
  document.getElementById('modal-exportar').style.display = 'none';
}

function abrirModalCrear() {
  document.getElementById('modal-crear').style.display = 'flex';
  toggleFormFields(document.getElementById('crear-rol').value, 'crear');
}
function cerrarModalCrear() {
  document.getElementById('modal-crear').style.display = 'none';
}

function abrirModalEditar(usr) {
  document.getElementById('edit-nombre').value = usr.nombre;
  document.getElementById('edit-email').value = usr.email;
  document.getElementById('edit-grado').value = usr.grado || '';
  document.getElementById('edit-genero').value = usr.genero || '';
  document.getElementById('edit-rol').value = usr.rol;
  document.getElementById('edit-depto').value = usr.departamento_id || '';
  document.getElementById('edit-contratacion').value = usr.tipo_contratacion || '';
  document.getElementById('edit-programa').value = usr.programa_id || '';
  document.getElementById('edit-posgrado').value = usr.posgrado_ids || '';
  document.getElementById('edit-activo').checked = usr.activo == 1;
  document.getElementById('edit-permite-contrasena').checked = usr.permite_contrasena == 1;

  toggleFormFields(usr.rol, 'edit');

  const form = document.getElementById('form-editar');
  form.action = `<?= url('ajustes/usuarios') ?>/${usr.id}/actualizar`;
  
  const btnInactivar = document.getElementById('btn-inactivar-usuario');
  btnInactivar.onclick = function(e) {
    if (!confirm(`¿Estás seguro de marcar a ${usr.nombre} como inactivo (dar de baja)?`)) {
      e.preventDefault();
      return;
    }
    form.action = `<?= url('ajustes/usuarios') ?>/${usr.id}/eliminar`;
  };

  document.getElementById('modal-editar').style.display = 'flex';
}

function cerrarModalEditar() {
  document.getElementById('modal-editar').style.display = 'none';
}

// Cerrar modales al hacer clic afuera
document.querySelectorAll('.modal-fondo').forEach(modal => {
  modal.addEventListener('click', function(e) {
    if (e.target === this) {
      this.style.display = 'none';
    }
  });
});
</script>
<datalist id="grados-lista"><?php foreach (\App\Core\Persona::GRADOS as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist>
