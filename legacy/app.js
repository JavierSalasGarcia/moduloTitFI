/**
 * =========================================================================
 * FI-PLATAFORMA: SISTEMA DE ACCESO GRANULAR Y RELACIONES INTERDEPARTAMENTALES
 * =========================================================================
 * Demo Interactiva con la Identidad Gráfica UAEMéx 2021-2025
 * Datos Oficiales del Manual de Organización 2026 de la Facultad de Ingeniería
 */

// 1. DYNAMIC CATEGORIZATION HELPER
function getDepartmentCategory(key) {
  if (
    key.includes("docencia") || 
    key.includes("licenciatura") || 
    key.includes("escolares") || 
    key.includes("evaluacion_profesional") || 
    key.includes("tutoria") || 
    key.includes("propedeuticas") ||
    key.includes("academica")
  ) {
    return "academia";
  }
  if (
    key.includes("investigacion") || 
    key.includes("estudios_avanzados") || 
    key.includes("estructural") || 
    key.includes("movilidad")
  ) {
    return "investigacion";
  }
  if (
    key.includes("difusion") || 
    key.includes("extension") || 
    key.includes("laboratorio") || 
    key.includes("continua") || 
    key.includes("cultura")
  ) {
    return "extension";
  }
  if (
    key.includes("administrativa") || 
    key.includes("planeacion") || 
    key.includes("tecnologias") || 
    key.includes("organizacion")
  ) {
    return "administracion";
  }
  return "direccion";
}

// Helper to extract a short descriptive title from function text
function getShortFunctionTitle(text, index) {
  // Strip leading numbers or Roman numerals
  let clean = text.replace(/^([IVX\d\.\)]+|Titular|Objetivo|Naturaleza Jur.dica)\.?\s*(-|:)?\s*/i, "").trim();
  
  // Clean double spaces
  clean = clean.replace(/\s+/g, " ");
  
  const cleanLower = clean.toLowerCase();
  
  // High-level Semantic Classifiers based on UAEMéx MO context
  if (cleanLower.includes("entrega") && cleanLower.includes("recepci")) {
    return "Proceso de Entrega-Recepción";
  }
  if (cleanLower.includes("gesti.n de la calidad") || cleanLower.includes("calidad") || cleanLower.includes("sgc")) {
    return "Gestión del Sistema de Calidad";
  }
  if (cleanLower.includes("auditor")) {
    return "Atención de Auditorías";
  }
  if (cleanLower.includes("presupuesto") || cleanLower.includes("recursos financieros")) {
    return "Control Presupuestal";
  }
  if (cleanLower.includes("servicios de ensayo") || cleanLower.includes("ensayo de concreto") || cleanLower.includes(" ema")) {
    return "Ensayos de Materiales (EMA)";
  }
  if (cleanLower.includes("metrolog.a") || cleanLower.includes("calibraci")) {
    return "Metrología y Calibración Industrial";
  }
  if (cleanLower.includes("agenda estad.stica") || (cleanLower.includes("informaci") && cleanLower.includes("estad.stica"))) {
    return "Agenda e Información Estadística";
  }
  if (cleanLower.includes("glosa del informe") || cleanLower.includes("glosa")) {
    return "Glosa del Informe Anual";
  }
  if (cleanLower.includes("transparencia") || cleanLower.includes("rendici.n de cuentas")) {
    return "Transparencia y Rendición";
  }
  if (cleanLower.includes("tutor.a") || cleanLower.includes("tutorado")) {
    return "Programa de Tutorías Académicas";
  }
  if (cleanLower.includes("preinscripci") || cleanLower.includes("inscripci") || cleanLower.includes("reinscripci")) {
    return "Trámites de Control Escolar";
  }
  if (cleanLower.includes("certificados") || cleanLower.includes("certificaci")) {
    return "Certificación de Estudios";
  }
  if (cleanLower.includes("examen de admisi")) {
    return "Proceso de Admisión Estudiantil";
  }
  if (cleanLower.includes("equivalencia") || cleanLower.includes("revalidaci")) {
    return "Trámites de Equivalencia y Revalidación";
  }
  if (cleanLower.includes("expediente") && cleanLower.includes("digital")) {
    return "Resguardo de Expedientes Digitales";
  }
  if (cleanLower.includes("jurado") || cleanLower.includes("evaluaci.n profesional")) {
    return "Evaluación Profesional y Grados";
  }
  if (cleanLower.includes("cuerpos acad.micos") || cleanLower.includes("cuerpo acad.mico")) {
    return "Gestión de Cuerpos Académicos";
  }
  if (cleanLower.includes("estancias postdoctorales") || cleanLower.includes("postdoctoral")) {
    return "Estancias Postdoctorales";
  }
  if (cleanLower.includes("publicaci") && (cleanLower.includes("libro") || cleanLower.includes("art.culo"))) {
    return "Publicaciones Científicas";
  }
  if (cleanLower.includes("intercambio") || cleanLower.includes("movilidad")) {
    return "Movilidad e Intercambio Académico";
  }
  if (cleanLower.includes("servicio social") || cleanLower.includes("pr.cticas profesionales")) {
    return "Servicio Social y Prácticas";
  }
  if (cleanLower.includes("educaci.n continua") || cleanLower.includes("diplomado")) {
    return "Cursos de Educación Continua";
  }
  if (cleanLower.includes("consejo de gobierno") || cleanLower.includes("consejo acad.mico")) {
    return "Apoyo a Consejos Universitarios";
  }
  if (cleanLower.includes("plan de desarrollo") || cleanLower.includes("poa ") || cleanLower.includes("poa,")) {
    return "Plan de Desarrollo y POA";
  }
  if (cleanLower.includes("fuentes alternas") || cleanLower.includes("financiamiento alterno")) {
    return "Gestión de Financiamiento Alterno";
  }
  if (cleanLower.includes("proyectos curriculares") || cleanLower.includes("dise.o curricular")) {
    return "Evaluación y Proyectos Curriculares";
  }
  if (cleanLower.includes("concursos de oposici") || cleanLower.includes("juicios de promoci")) {
    return "Concursos e Ingreso Docente";
  }
  if (cleanLower.includes("licencia") && cleanLower.includes("personal acad.mico")) {
    return "Licencias del Personal Docente";
  }
  if (cleanLower.includes("ambiente propicio") || cleanLower.includes("clima acad.mico")) {
    return "Bienestar y Clima Estudiantil";
  }
  if (cleanLower.includes("difusi.n cultural") || cleanLower.includes("cultural y")) {
    return "Difusión Cultural y Deportiva";
  }
  if (cleanLower.includes("acordar con el director") || cleanLower.includes("acordar con la persona titular de la direcci")) {
    return "Acuerdos con la Dirección";
  }
  if (cleanLower.includes("acordar con el subdirector") || cleanLower.includes("acordar con la persona titular de la subdirecci")) {
    return "Acuerdos con la Subdirección";
  }
  if (cleanLower.includes("sustituir al director") || cleanLower.includes("ausencia del director")) {
    return "Sustitución de la Dirección";
  }
  if (cleanLower.includes("personal acad.mico ad -honorem") || cleanLower.includes("ad-honorem")) {
    return "Seguimiento de Personal Externo";
  }
  if (cleanLower.includes("becarios") || cleanLower.includes("tesistas")) {
    return "Monitoreo de Becarios y Tesistas";
  }
  if (cleanLower.includes("bases de datos")) {
    return "Administración de Bases de Datos";
  }
  if (cleanLower.includes("las dem.s funciones inherentes")) {
    return "Funciones Complementarias de Ley";
  }

  // Fallback: extract first 4-5 words of clean string, capitalize first letter of each
  let words = clean.split(' ').filter(w => w.trim().length > 0);
  if (words.length > 0) {
    let titleWords = [];
    let count = 0;
    for (let word of words) {
      titleWords.push(word);
      let cleanW = word.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ]/g, "");
      if (cleanW.length > 2) {
        count++;
      }
      if (count >= 3 || titleWords.length >= 5) break;
    }
    let titleStr = titleWords.join(' ');
    titleStr = titleStr.replace(/[\s,:-]+$/, "");
    titleStr = titleStr.charAt(0).toUpperCase() + titleStr.slice(1);
    if (titleStr.length > 40) {
      titleStr = titleStr.substring(0, 37) + "...";
    }
    return titleStr;
  }
  
  return `Atribución Nº ${index + 1}`;
}


// Group names mapping
const CATEGORY_NAMES = {
  direccion: "Cuerpo Directivo y Consejos",
  academia: "Subdirección Académica y Licenciaturas",
  investigacion: "Investigación y Posgrados",
  extension: "Difusión, Extensión y Laboratorios",
  administracion: "Subdirección y Soporte Administrativo"
};

const CATEGORY_ICONS = {
  direccion: "award",
  academia: "graduation-cap",
  investigacion: "flask",
  extension: "share-2",
  administracion: "settings"
};

// 2. STATE MANAGEMENT
let state = {
  activeRole: "", 
  activeTab: "portal",
  sidebarContextCollapsed: false,
  activeContextItemIndex: 0,
  relationTargetKey: "",
  
  // Interdepartmental function links state
  mappedRelations: JSON.parse(localStorage.getItem("fi_mapped_relations")) || [
    {
      id: "rel-demo-1",
      sourceDept: "coordinadores_de_docencia",
      sourceFuncIdx: 0, 
      targetDept: "subdireccion_academica",
      targetFuncIdx: 1, 
      notes: "Acuerdos académicos y validación de programas de estudios profesionales."
    },
    {
      id: "rel-demo-2",
      sourceDept: "coordinacion_de_planeacion",
      sourceFuncIdx: 0, 
      targetDept: "direccion",
      targetFuncIdx: 4, 
      notes: "Vínculo de planeación anual, metas físicas y financieras de la Facultad."
    }
  ],
  browseDeptKey: "",
  consultationRequests: JSON.parse(localStorage.getItem("fi_consultation_requests")) || [
    {
      id: "req_demo_1",
      sourceDept: "coordinadores_de_docencia",
      targetDept: "departamento_de_servicios_escolares",
      requestedFuncs: [0, 2],
      justification: "Revisión técnica de calendarios de inscripción y actas.",
      status: "approved",
      timestamp: new Date().toISOString()
    }
  ]
};

// 3. DOM ELEMENTS
const DOM = {
  roleSelector: document.getElementById("roleSelector"),
  activeRoleName: document.getElementById("activeRoleName"),
  profileInitials: document.getElementById("profileInitials"),
  sidebarContext: document.getElementById("sidebarContext"),
  contextTitleText: document.getElementById("contextTitleText"),
  contextNavContent: document.getElementById("contextNavContent"),
  canvasPageTitleText: document.getElementById("canvasPageTitleText"),
  canvasPageTitle: document.getElementById("canvasPageTitle"),
  canvasMainBody: document.getElementById("canvasMainBody"),
  toggleContextBtn: document.getElementById("toggleContextBtn"),
  
  // Navigation Tabs Buttons
  tabPortal: document.getElementById("tabBtnPortal"),
  tabFunciones: document.getElementById("tabBtnFunciones"),
  tabRelaciones: document.getElementById("tabBtnRelaciones"),
  tabDirectorio: document.getElementById("tabBtnDirectorio")
};

// 4. SETUP RELATIONSHIPS DYNAMICALLY BASED ON DEPARTMENT TYPE
function getRelationshipsFor(key) {
  const currentDept = MO_DEPARTAMENTOS_DATA[key];
  const cat = getDepartmentCategory(key);
  
  // Custom explicit relationships for demo
  if (key.includes("docencia") || key.includes("licenciatura")) {
    return [
      {
        targetKey: "departamento_de_servicios_escolares",
        title: "Departamento de Servicios Escolares",
        desc: "Coordinación de inscripciones de alumnos, control de actas de calificaciones y asignación oficial de grupos.",
        actions: ["Consultar Listas de Alumnos Oficiales", "Solicitar Apertura de Actas de Examen"],
        icon: "archive",
        type: "sibling"
      },
      {
        targetKey: "departamento_de_tutoria_academica",
        title: "Departamento de Tutoría Académica",
        desc: "Gestión de alumnos en riesgo académico, asignaciones de profesores tutores y asesoría estudiantil.",
        actions: ["Canalizar Estudiantes en Riesgo", "Consultar Asignación de Tutores de la Carrera"],
        icon: "activity",
        type: "sibling"
      },
      {
        targetKey: "departamento_de_evaluacion_profesional_y_grado",
        title: "Departamento de Evaluación Profesional y Grado",
        desc: "Propuestas de jurados evaluadores de tesis y calendarización de protocolos de examen profesional.",
        actions: ["Proponer Comité y Jurado de Tesis", "Revisar Historial de Egresados Registrados"],
        icon: "award",
        type: "sibling"
      }
    ];
  }
  
  if (key === "departamento_de_servicios_escolares") {
    // Services Escolares connects back to Docencia
    return [
      {
        targetKey: "coordinacion_de_docencia_de_la_licenciatura_en_ingenieria_en_inteligencia_artificial",
        title: "Coordinación de Docencia (IA)",
        desc: "Validación de la oferta de cupos, asignación docente por grupo y listados oficiales de alumnos.",
        actions: ["Enviar Listado Oficial de Alumnos Inscritos", "Notificar Cierre de Periodo Escolar"],
        icon: "graduation-cap",
        type: "sibling"
      },
      {
        targetKey: "departamento_de_evaluacion_profesional_y_grado",
        title: "Departamento de Evaluación Profesional y Grado",
        desc: "Emisión de constancias de liberación de créditos (Kardex Completo) para integrar el expediente de examen profesional.",
        actions: ["Validar Kardex Completo de Pasantes", "Enviar Archivo Digital de Certificados"],
        icon: "award",
        type: "sibling"
      }
    ];
  }
  
  if (key === "coordinacion_de_investigacion") {
    return [
      {
        targetKey: "coordinacion_de_estudios_avanzados",
        title: "Coordinación de Estudios Avanzados",
        desc: "Vincular a estudiantes de posgrados (Maestría/Doctorado) con proyectos científicos con financiamiento.",
        actions: ["Consultar Padrón de Alumnos de Posgrado", "Registrar Tesis Asociadas a Proyectos"],
        icon: "book-open",
        type: "sibling"
      },
      {
        targetKey: "subdireccion_administrativa",
        title: "Subdirección Administrativa",
        desc: "Gestión de presupuestos de convocatorias externas, compras de equipamiento científico y refacciones de laboratorios.",
        actions: ["Solicitar Adquisición de Equipo de Laboratorio", "Enviar Presupuesto de Convocatoria CONAHCYT"],
        icon: "credit-card",
        type: "hierarchical"
      }
    ];
  }
  
  if (key === "subdireccion_administrativa") {
    return [
      {
        targetKey: "departamento_de_tecnologias_de_la_informacion_y_comunicaciones",
        title: "Departamento de TIC",
        desc: "Planificación de la infraestructura tecnológica, redes cableadas/inalámbricas y desarrollo de sistemas escolares.",
        actions: ["Autorizar Mantenimiento de Servidores", "Liberar Insumos de Fibra Óptica y Cableado"],
        icon: "monitor",
        type: "hierarchical"
      },
      {
        targetKey: "coordinacion_de_planeacion",
        title: "Coordinación de Planeación",
        desc: "Seguimiento al presupuesto anual, metas físicas y financieras del Plan de Desarrollo Institucional.",
        actions: ["Aprobar Presupuesto de Desarrollo Físico", "Revisar Indicadores Trimestrales de Metas"],
        icon: "trending-up",
        type: "hierarchical"
      }
    ];
  }

  // Fallback default relationships for other departments:
  // All departments connect to Dirección (Reporting) and their respective Subdirección (Académica/Administrativa)
  const reportingSub = (cat === "academia" || cat === "investigacion") ? "subdireccion_academica" : "subdireccion_administrativa";
  const reportingSubTitle = (cat === "academia" || cat === "investigacion") ? "Subdirección Académica" : "Subdirección Administrativa";
  
  return [
    {
      targetKey: "direccion",
      title: "Dirección de la Facultad",
      desc: "Presentación de acuerdos, visto bueno del plan anual y reportes estadísticos institucionales oficiales.",
      actions: ["Enviar Informe Trimestral de Actividades", "Solicitar Aprobación de Plan de Trabajo"],
      icon: "award",
      type: "hierarchical"
    },
    {
      targetKey: reportingSub,
      title: reportingSubTitle,
      desc: "Coordinación directa de presupuestos o planeación de actividades adscritas a esta subdirección.",
      actions: ["Notificar Oficios Recibidos", "Gestionar Recursos del Departamento"],
      icon: cat === "academia" || cat === "investigacion" ? "graduation-cap" : "settings",
      type: "hierarchical"
    }
  ];
}

// 5. INITIALIZATION
function initApp() {
  // Sort departments by title
  const sortedKeys = Object.keys(MO_DEPARTAMENTOS_DATA).sort((a, b) => {
    return MO_DEPARTAMENTOS_DATA[a].title.localeCompare(MO_DEPARTAMENTOS_DATA[b].title);
  });
  
  // Set default active role to the first sorted department key
  // Prefer IA Coordinación if available to load a nice default
  const defaultKey = sortedKeys.find(k => k.includes("inteligencia_artificial")) || sortedKeys[0];
  state.activeRole = defaultKey;
  state.browseDeptKey = defaultKey;
  
  setupEventListeners();
  populateRoleSelector(sortedKeys);
  updateRoleState();
}

function setupEventListeners() {
  // Toggle context sidebar
  DOM.toggleContextBtn.addEventListener("click", () => {
    state.sidebarContextCollapsed = !state.sidebarContextCollapsed;
    if (state.sidebarContextCollapsed) {
      DOM.sidebarContext.classList.add("collapsed");
      DOM.toggleContextBtn.innerHTML = '<i data-lucide="menu-square"></i>';
    } else {
      DOM.sidebarContext.classList.remove("collapsed");
      DOM.toggleContextBtn.innerHTML = '<i data-lucide="menu"></i>';
    }
    lucide.createIcons();
  });

  // Global Tab switches
  const tabs = [
    { el: DOM.tabPortal, name: "portal" },
    { el: DOM.tabFunciones, name: "funciones" },
    { el: DOM.tabRelaciones, name: "relaciones" },
    { el: DOM.tabDirectorio, name: "directorio" }
  ];

  tabs.forEach(tab => {
    tab.el.addEventListener("click", () => {
      tabs.forEach(t => t.el.classList.remove("active"));
      tab.el.classList.add("active");
      
      state.activeTab = tab.name;
      state.activeContextItemIndex = 0;
      
      renderInterface();
    });
  });

  // Selector de Rol (dropdown)
  DOM.roleSelector.addEventListener("change", (e) => {
    state.activeRole = e.target.value;
    state.activeContextItemIndex = 0;
    state.browseDeptKey = state.activeRole; // Sync browsed department
    
    // Initialize default target relation key
    const relations = getRelationshipsFor(state.activeRole);
    state.relationTargetKey = relations[0]?.targetKey || "";
    
    updateRoleState();
  });
}

function populateRoleSelector(keys) {
  DOM.roleSelector.innerHTML = "";
  
  // Group keys by category for cleaner organization in optgroup elements
  const categories = {
    direccion: [],
    academia: [],
    investigacion: [],
    extension: [],
    administracion: []
  };
  
  keys.forEach(key => {
    const cat = getDepartmentCategory(key);
    categories[cat].push(key);
  });
  
  // Build drop-down with optgroup elements
  Object.keys(CATEGORY_NAMES).forEach(catKey => {
    const groupDepts = categories[catKey];
    if (groupDepts.length === 0) return;
    
    const optgroup = document.createElement("optgroup");
    optgroup.label = CATEGORY_NAMES[catKey];
    
    groupDepts.forEach(key => {
      const option = document.createElement("option");
      option.value = key;
      option.textContent = MO_DEPARTAMENTOS_DATA[key].title;
      optgroup.appendChild(option);
    });
    
    DOM.roleSelector.appendChild(optgroup);
  });
}

function updateRoleState() {
  const currentRole = MO_DEPARTAMENTOS_DATA[state.activeRole];
  DOM.activeRoleName.textContent = currentRole.title;
  
  // Compute nice initials for avatar badge
  const words = currentRole.title.split(' ').filter(w => w.length > 2 && !["del", "los", "las", "para", "con"].includes(w.toLowerCase()));
  const initials = words.slice(0, 2).map(w => w[0].toUpperCase()).join('');
  DOM.profileInitials.textContent = initials || "FI";
  
  DOM.roleSelector.value = state.activeRole;
  
  // Initialize target relation key if empty
  const relations = getRelationshipsFor(state.activeRole);
  if (!state.relationTargetKey || !relations.some(r => r.targetKey === state.relationTargetKey)) {
    state.relationTargetKey = relations[0]?.targetKey || "";
  }
  
  renderInterface();
}

// 6. RENDERING PIPELINE
function renderInterface() {
  const currentRole = MO_DEPARTAMENTOS_DATA[state.activeRole];
  
  // Update header text based on active tab
  const tabTitles = {
    portal: { text: "Portal del Departamento", icon: "layout-dashboard" },
    funciones: { text: "Facultades y Funciones Oficiales", icon: "briefcase" },
    relaciones: { text: "Canales de Relación y Trámites", icon: "git-branch" },
    directorio: { text: "Organigrama de la Facultad", icon: "users" }
  };
  
  DOM.canvasPageTitleText.textContent = tabTitles[state.activeTab].text;
  DOM.canvasPageTitle.innerHTML = `<i data-lucide="${tabTitles[state.activeTab].icon}"></i> <span style="margin-left: 8px">${tabTitles[state.activeTab].text}</span>`;
  
  // Render Context Sidebar Title
  const contextTitles = {
    portal: "Resumen Portal",
    funciones: "Funciones del Depto.",
    relaciones: "Relaciones Autorizadas",
    directorio: "Filtros Organigrama"
  };
  DOM.contextTitleText.textContent = contextTitles[state.activeTab];
  
  // Render Context List & Main Body
  renderContextNav(currentRole);
  renderMainCanvas(currentRole);
  
  // Hydrate SVG Icons with Lucide
  lucide.createIcons();
}

// 6.1 Sidebar Context Renderer
function renderContextNav(role) {
  DOM.contextNavContent.innerHTML = "";
  
  const ul = document.createElement("ul");
  ul.className = "context-list";
  
  if (state.activeTab === "portal") {
    // Portal items
    const items = [
      { label: "Resumen de Entrada", icon: "home", desc: "Indicadores generales" },
      { label: "Avisos UAEMéx", icon: "bell", desc: "Gaceta y circulares" },
      { label: "Contacto Directo", icon: "mail", desc: "Oficio y firma digital" }
    ];
    items.forEach((item, idx) => {
      const li = createListItem(item.label, item.icon, item.desc, idx === state.activeContextItemIndex);
      li.addEventListener("click", () => {
        state.activeContextItemIndex = idx;
        renderInterface();
      });
      ul.appendChild(li);
    });
    
  } else if (state.activeTab === "funciones") {
    const browsedDeptKey = state.browseDeptKey || state.activeRole;
    const browsedRole = MO_DEPARTAMENTOS_DATA[browsedDeptKey] || role;
    
    const browseContainer = document.createElement("div");
    browseContainer.style.padding = "0 8px 12px 8px";
    browseContainer.style.borderBottom = "1px solid var(--border-color)";
    browseContainer.style.marginBottom = "12px";
    
    browseContainer.innerHTML = `
      <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; display: block; margin-bottom: 6px;">
        Explorar Departamento:
      </label>
      <select id="sidebar-browse-dept" style="width: 100%; padding: 8px 12px; font-size: 13px; border-radius: 6px; border: 1px solid var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary); font-family: inherit; font-weight: 500;">
      </select>
    `;
    
    DOM.contextNavContent.appendChild(browseContainer);
    
    const selectEl = browseContainer.querySelector("#sidebar-browse-dept");
    const sortedKeys = Object.keys(MO_DEPARTAMENTOS_DATA).sort((a, b) => {
      return MO_DEPARTAMENTOS_DATA[a].title.localeCompare(MO_DEPARTAMENTOS_DATA[b].title);
    });
    
    sortedKeys.forEach(k => {
      const opt = document.createElement("option");
      opt.value = k;
      opt.textContent = MO_DEPARTAMENTOS_DATA[k].title;
      if (k === browsedDeptKey) opt.selected = true;
      selectEl.appendChild(opt);
    });
    
    selectEl.addEventListener("change", (e) => {
      state.browseDeptKey = e.target.value;
      state.activeContextItemIndex = 0;
      renderInterface();
    });
    
    if (!browsedRole.functions || browsedRole.functions.length === 0) {
      const li = document.createElement("li");
      li.className = "item-desc";
      li.style.padding = "12px";
      li.textContent = "No hay funciones cargadas.";
      ul.appendChild(li);
    } else {
      browsedRole.functions.forEach((fn, idx) => {
        // Build short preview of function text
        const cleanText = fn.replace(/^([IVX\d\.\)]+|Titular|Objetivo|Naturaleza Jur.dica)\.?\s*(-|:)?\s*/i, "").trim();
        const shortDesc = cleanText.substring(0, 32) + "...";
        const label = getShortFunctionTitle(fn, idx);
        const li = createListItem(label, "check-square", shortDesc, idx === state.activeContextItemIndex);
        
        li.addEventListener("click", () => {
          state.activeContextItemIndex = idx;
          renderInterface();
        });
        ul.appendChild(li);
      });
    }
    
  } else if (state.activeTab === "relaciones") {
    // Relations list
    const relations = getRelationshipsFor(state.activeRole);
    
    if (relations.length === 0) {
      const li = document.createElement("li");
      li.className = "item-desc";
      li.style.padding = "12px";
      li.textContent = "Sin relaciones.";
      ul.appendChild(li);
    } else {
      relations.forEach((rel) => {
        const isActive = rel.targetKey === state.relationTargetKey;
        const li = createListItem(rel.title, rel.icon, "Interactuar", isActive);
        
        const tag = document.createElement("span");
        tag.className = "relation-tag";
        tag.textContent = "Conector";
        li.appendChild(tag);
        
        li.addEventListener("click", () => {
          state.relationTargetKey = rel.targetKey;
          renderInterface();
        });
        ul.appendChild(li);
      });
    }
    
  } else if (state.activeTab === "directorio") {
    // Categories for directory
    const cats = [
      { label: "Todos los Departamentos", icon: "grid", key: "all" },
      { label: "1. Cuerpo Directivo", icon: "award", key: "direccion" },
      { label: "2. Área Académica", icon: "graduation-cap", key: "academia" },
      { label: "3. Investigación y Posgrado", icon: "flask", key: "investigacion" },
      { label: "4. Extensión y Enlace", icon: "share-2", key: "extension" },
      { label: "5. Soporte Administrativo", icon: "settings", key: "administracion" }
    ];
    cats.forEach((cat, idx) => {
      const li = createListItem(cat.label, cat.icon, "Filtrar organigrama", idx === state.activeContextItemIndex);
      li.addEventListener("click", () => {
        state.activeContextItemIndex = idx;
        renderInterface();
      });
      ul.appendChild(li);
    });
  }
  
  DOM.contextNavContent.appendChild(ul);
}

// 6.2 Main Canvas Content Renderer
function renderMainCanvas(role) {
  DOM.canvasMainBody.innerHTML = "";
  
  if (state.activeTab === "portal") {
    renderPortalTab(role);
  } else if (state.activeTab === "funciones") {
    renderFuncionesTab(role);
  } else if (state.activeTab === "relaciones") {
    renderRelacionesTab(role);
  } else if (state.activeTab === "directorio") {
    renderDirectorioTab();
  }
}

// 6.2.1 PORTAL TAB
function renderPortalTab(role) {
  // Welcome Hero Card
  const welcomeCard = document.createElement("div");
  welcomeCard.className = "welcome-card";
  
  const cat = getDepartmentCategory(role.key);
  const catName = CATEGORY_NAMES[cat];
  
  welcomeCard.innerHTML = `
    <span class="welcome-card-role">${catName}</span>
    <h2 class="welcome-card-title">${role.title}</h2>
    <p class="welcome-card-subtitle">Estructura Orgánica FI. ${role.origin}.</p>
  `;
  DOM.canvasMainBody.appendChild(welcomeCard);
  
  // Statistics Widgets
  const statsContainer = document.createElement("div");
  statsContainer.className = "stats-container";
  
  const stats = [
    { number: role.functions.length, label: "Funciones en el Manual", icon: "clipboard-list" },
    { number: role.origin.match(/\d+/)?.[0] || "---", label: "Página en el PDF", icon: "book-open" },
    { number: "2026", label: "Vigencia del MO", icon: "calendar" }
  ];
  
  stats.forEach(stat => {
    const widget = document.createElement("div");
    widget.className = "stat-widget";
    widget.innerHTML = `
      <div class="stat-icon"><i data-lucide="${stat.icon}"></i></div>
      <div class="stat-info">
        <span class="stat-number">${stat.number}</span>
        <span class="stat-label">${stat.label}</span>
      </div>
    `;
    statsContainer.appendChild(widget);
  });
  DOM.canvasMainBody.appendChild(statsContainer);
  
  // Dashboard layout
  const grid = document.createElement("div");
  grid.className = "dashboard-grid";
  
  const notificationsCard = document.createElement("div");
  notificationsCard.className = "glass-card span-8";
  notificationsCard.innerHTML = `
    <h3 class="card-title"><i data-lucide="bell"></i> Circulares e Información del Manual</h3>
    <div style="display: flex; flex-direction: column; gap: 16px;">
      <div style="padding-bottom: 12px; border-bottom: 1px solid var(--border-color)">
        <span style="font-size: 11px; font-weight: 700; color: var(--uaem-gold); display: block; margin-bottom: 4px;">PÁGINAS OFICIALES EN PDF: ${role.origin}</span>
        <strong style="font-size: 14px; color: var(--uaem-green)">Facultades y Atribuciones de Ley</strong>
        <p style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Las atribuciones legales mostradas en la pestaña "Mis Funciones" corresponden textualmente a la versión 2 oficial autorizada por el H. Consejo Universitario en mayo de 2026.</p>
      </div>
      <div style="padding-bottom: 12px;">
        <span style="font-size: 11px; font-weight: 700; color: var(--uaem-gold); display: block; margin-bottom: 4px;">CONECTIVIDAD INTERDEPARTAMENTAL</span>
        <strong style="font-size: 14px; color: var(--uaem-green)">Acceso Granular de Red</strong>
        <p style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Utiliza el menú lateral izquierdo "Relaciones e Interacciones" para consultar qué otros departamentos interactúan directamente con ${role.title} y qué flujos de datos se coordinan mutuamente.</p>
      </div>
    </div>
  `;
  grid.appendChild(notificationsCard);
  
  const statusSummaryCard = document.createElement("div");
  statusSummaryCard.className = "glass-card span-4";
  statusSummaryCard.innerHTML = `
    <h3 class="card-title"><i data-lucide="shield-check"></i> Estatus Técnico</h3>
    <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
      <div style="display: flex; justify-content: space-between; align-items: center">
        <span>Módulo de Base de Datos</span>
        <span class="status-pill success">Verificado</span>
      </div>
      <div style="display: flex; justify-content: space-between; align-items: center">
        <span>Firma Digital FI</span>
        <span class="status-pill success">Habilitada</span>
      </div>
      <div style="display: flex; justify-content: space-between; align-items: center">
        <span>Identidad Visual</span>
        <span style="font-weight: 700; color: var(--uaem-gold)">UAEM 2021-2025</span>
      </div>
      <div style="padding-top: 12px; border-top: 1px solid var(--border-color); text-align: center; color: var(--text-muted); font-size: 11px;">
        Manual de Organización <br> Facultad de Ingeniería
      </div>
    </div>
  `;
  grid.appendChild(statusSummaryCard);
  
  // Pending Access Requests Received by this department
  const pendingRequests = state.consultationRequests.filter(r => 
    r.targetDept === state.activeRole && r.status === "pending"
  );
  
  if (pendingRequests.length > 0) {
    const reqCard = document.createElement("div");
    reqCard.className = "glass-card span-12";
    reqCard.style.borderLeft = "6px solid var(--uaem-gold)";
    reqCard.style.marginTop = "20px";
    
    let reqsHtml = "";
    pendingRequests.forEach(req => {
      const sourceDeptData = MO_DEPARTAMENTOS_DATA[req.sourceDept];
      const sourceTitle = sourceDeptData ? sourceDeptData.title : req.sourceDept;
      
      reqsHtml += `
        <div class="request-card" style="margin-bottom: 12px; border: 1px solid var(--border-color); padding: 16px; border-radius: 8px; background: var(--bg-secondary); display: flex; justify-content: space-between; align-items: center;">
          <div style="text-align: left;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <span style="font-size: 11px; font-weight: 700; color: var(--uaem-gold); text-transform: uppercase;">Solicitud de Consulta Recibida</span>
              <span style="font-size: 9px; background: var(--border-color); color: var(--text-secondary); padding: 1px 4px; border-radius: 4px;">Pendiente</span>
            </div>
            <strong style="font-size: 14px; color: var(--text-primary); display: block; margin-top: 6px;">
              De: ${sourceTitle}
            </strong>
            <p style="font-size: 12px; color: var(--text-secondary); margin: 6px 0 0 0;">
              <strong>Motivo/Justificación:</strong> "${req.justification}"
            </p>
            <p style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
              Solicita consultar las funciones oficiales número: ${req.requestedFuncs.map(i => i + 1).join(", ")}
            </p>
          </div>
          <div style="display: flex; gap: 8px;">
            <button class="action-btn-primary btn-approve-request" data-id="${req.id}" style="font-size: 11px; padding: 6px 12px; border-radius: 4px; background-color: var(--uaem-green); color: white; border: none; cursor: pointer; font-weight: 600;">
              Aprobar
            </button>
            <button class="action-btn-secondary btn-reject-request" data-id="${req.id}" style="font-size: 11px; padding: 6px 12px; border-radius: 4px; border: 1px solid rgba(220, 53, 69, 0.2); background: rgba(220, 53, 69, 0.05); color: var(--status-error); cursor: pointer; font-weight: 600;">
              Rechazar
            </button>
          </div>
        </div>
      `;
    });
    
    reqCard.innerHTML = `
      <h3 class="card-title" style="display: flex; align-items: center; gap: 8px; color: var(--uaem-gold); margin-bottom: 16px;">
        <i data-lucide="shield-alert"></i> Peticiones de Acceso y Consulta del Organigrama
      </h3>
      ${reqsHtml}
    `;
    
    // Attach events
    reqCard.querySelectorAll(".btn-approve-request").forEach(btn => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-id");
        const reqObj = state.consultationRequests.find(r => r.id === id);
        if (reqObj) {
          reqObj.status = "approved";
          localStorage.setItem("fi_consultation_requests", JSON.stringify(state.consultationRequests));
          alert("¡Solicitud de acceso aprobada correctamente!");
          renderInterface();
        }
      });
    });
    
    reqCard.querySelectorAll(".btn-reject-request").forEach(btn => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-id");
        const reqObj = state.consultationRequests.find(r => r.id === id);
        if (reqObj) {
          reqObj.status = "rejected";
          localStorage.setItem("fi_consultation_requests", JSON.stringify(state.consultationRequests));
          alert("Solicitud rechazada.");
          renderInterface();
        }
      });
    });
    
    grid.appendChild(reqCard);
  }
  
  DOM.canvasMainBody.appendChild(grid);
}

// 6.2.2 FUNCIONES TAB (REAL EXTRACTED FUNCTIONS)
function renderFuncionesTab(role) {
  const browsedDeptKey = state.browseDeptKey || state.activeRole;
  const browsedRole = MO_DEPARTAMENTOS_DATA[browsedDeptKey] || role;
  
  const currentFnIndex = state.activeContextItemIndex;
  const currentFnText = browsedRole.functions[currentFnIndex];
  
  if (!currentFnText) {
    DOM.canvasMainBody.innerHTML = `<div class="glass-card"><p>No hay funciones seleccionadas o registradas.</p></div>`;
    return;
  }
  
  const isReadOnly = browsedDeptKey !== state.activeRole;
  
  // Access permission checking for external departments
  let isAuthorized = true;
  if (isReadOnly) {
    // 1. Pre-authorized default relations
    const relations = getRelationshipsFor(state.activeRole);
    const hasDefaultRelation = relations.some(r => r.targetKey === browsedDeptKey);
    
    // 2. Direct authorities (Dirección, Subdirección de adscripción) are pre-authorized
    const cat = getDepartmentCategory(state.activeRole);
    const reportingSub = (cat === "academia" || cat === "investigacion") ? "subdireccion_academica" : "subdireccion_administrativa";
    const isDirectAuthority = browsedDeptKey === "direccion" || browsedDeptKey === reportingSub;
    
    if (!hasDefaultRelation && !isDirectAuthority) {
      // 3. Otherwise, check if there is an approved request for this specific function index
      const approvedReq = state.consultationRequests.find(r => 
        r.sourceDept === state.activeRole && 
        r.targetDept === browsedDeptKey && 
        r.status === "approved" && 
        r.requestedFuncs.includes(currentFnIndex)
      );
      if (!approvedReq) {
        isAuthorized = false;
      }
    }
  }
  
  if (!isAuthorized) {
    const restrictedCard = document.createElement("div");
    restrictedCard.className = "glass-card fade-in";
    restrictedCard.style.padding = "40px";
    restrictedCard.style.textAlign = "center";
    restrictedCard.style.borderLeft = "6px solid var(--status-error)";
    restrictedCard.style.marginTop = "20px";
    
    restrictedCard.innerHTML = `
      <div style="width: 56px; height: 56px; border-radius: 50%; background-color: rgba(220, 53, 69, 0.1); color: var(--status-error); display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px auto; border: 1px solid rgba(220, 53, 69, 0.2);">
        <i data-lucide="lock"></i>
      </div>
      <h3 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">Acceso Restringido</h3>
      <p style="font-size: 13px; color: var(--text-secondary); max-width: 450px; margin: 0 auto 20px auto; line-height: 1.5;">
        No cuentas con autorización del titular de <strong>${browsedRole.title}</strong> para consultar esta atribución específica. Debes emitir una solicitud de consulta desde el Organigrama.
      </p>
      <div style="display: flex; gap: 12px; justify-content: center;">
        <button class="action-btn-primary" id="btn-request-access-now" style="font-size: 12px; padding: 8px 16px; background-color: var(--uaem-green); border: none; border-radius: 6px; color: white; cursor: pointer; display: flex; align-items: center; gap: 6px; font-weight: 600;">
          <i data-lucide="network"></i> Abrir Organigrama para Solicitar
        </button>
        <button class="action-btn-secondary" id="btn-back-to-own" style="font-size: 12px; padding: 8px 16px; border: 1px solid var(--border-color); background: transparent; cursor: pointer; font-weight: 600; color: var(--text-primary);">
          Volver a Mis Funciones
        </button>
      </div>
    `;
    
    DOM.canvasMainBody.innerHTML = "";
    DOM.canvasMainBody.appendChild(restrictedCard);
    lucide.createIcons();
    
    restrictedCard.querySelector("#btn-request-access-now").addEventListener("click", () => {
      state.activeTab = "relaciones";
      state.activeContextItemIndex = 0;
      
      const tabs = [
        { el: DOM.tabPortal, name: "portal" },
        { el: DOM.tabFunciones, name: "funciones" },
        { el: DOM.tabRelaciones, name: "relaciones" },
        { el: DOM.tabDirectorio, name: "directorio" }
      ];
      tabs.forEach(t => {
        if (t.name === "relaciones") t.el.classList.add("active");
        else t.el.classList.remove("active");
      });
      
      renderInterface();
      setTimeout(() => {
        openOrganigramaModal(browsedDeptKey);
      }, 100);
    });
    
    restrictedCard.querySelector("#btn-back-to-own").addEventListener("click", () => {
      state.browseDeptKey = state.activeRole;
      state.activeContextItemIndex = 0;
      renderInterface();
    });
    
    return;
  }
  
  // Extract number prefix (like "1.", "13.", "I.") and clean description text
  const numberMatch = currentFnText.match(/^([IVX\d]+)[\.\)]?/i);
  const numLabel = numberMatch ? numberMatch[1] : (currentFnIndex + 1);
  const cleanBodyText = currentFnText.replace(/^([IVX\d\.\)]+|Titular|Objetivo|Naturaleza Jur.dica)\.?\s*(-|:)?\s*/i, "").trim();
  
  
  const container = document.createElement("div");
  container.className = "glass-card fade-in";
  
  // Header of function display
  const fHeader = document.createElement("div");
  fHeader.style.marginBottom = "24px";
  fHeader.innerHTML = `
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
      <div style="width: 40px; height: 40px; border-radius: 8px; background-color: ${isReadOnly ? 'var(--bg-secondary)' : 'var(--uaem-green-light)'}; display: flex; align-items: center; justify-content: center; color: ${isReadOnly ? 'var(--text-muted)' : 'var(--uaem-green)'}; font-size: 20px; border: 1px solid var(--border-color);">
        <i data-lucide="${isReadOnly ? 'eye' : 'check-square'}"></i>
      </div>
      <div>
        <h2 style="font-size: 20px; font-weight: 800; color: ${isReadOnly ? 'var(--text-primary)' : 'var(--uaem-green)'}; display: flex; align-items: center; gap: 8px;">
          Función Oficial Nº ${numLabel} 
          ${isReadOnly ? '<span style="font-size: 10px; background-color: var(--bg-secondary); color: var(--text-muted); border: 1px solid var(--border-color); padding: 2px 6px; border-radius: 4px; font-weight: 600;">Solo Lectura</span>' : ''}
        </h2>
        <p style="font-size: 13px; color: var(--text-secondary);">${browsedRole.title}</p>
      </div>
    </div>
  `;
  container.appendChild(fHeader);
  
  // Large full text print
  const bodyCard = document.createElement("div");
  bodyCard.style.padding = "24px";
  bodyCard.style.backgroundColor = "var(--bg-primary)";
  bodyCard.style.borderRadius = "var(--border-radius-md)";
  bodyCard.style.border = "1px solid var(--border-color)";
  bodyCard.style.borderLeft = isReadOnly ? "6px solid var(--border-color)" : "6px solid var(--uaem-gold)";
  bodyCard.style.boxShadow = "var(--shadow-sm)";
  
  bodyCard.innerHTML = `
    <p style="font-size: 16px; line-height: 1.8; color: var(--text-primary); font-weight: 500;">
      "${cleanBodyText}"
    </p>
    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px dashed var(--border-color); display: flex; justify-content: space-between; font-size: 11px; color: var(--text-muted);">
      <span>Estatuto / Atribución Oficial del Manual</span>
      <span>${browsedRole.origin}</span>
    </div>
  `;
  container.appendChild(bodyCard);
  
  if (!isReadOnly) {
    // Simulated tracking & follow-up log section
    const trackingSec = document.createElement("div");
    trackingSec.className = "tracking-section";
    trackingSec.style.marginTop = "32px";
    trackingSec.style.padding = "20px";
    trackingSec.style.backgroundColor = "var(--bg-secondary)";
    trackingSec.style.borderRadius = "var(--border-radius-md)";
    trackingSec.style.border = "1px solid var(--border-color)";
    
    trackingSec.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <span style="font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">
          Monitoreo y Seguimiento Continuo
        </span>
        <span class="status-pill success" style="background-color: var(--uaem-green-light); color: var(--uaem-green); border-color: var(--border-green); display: flex; align-items: center; gap: 4px; padding: 4px 10px; font-size: 10px; border-radius: 20px;">
          <span class="pulse-indicator" style="width: 6px; height: 6px; border-radius: 50%; background-color: var(--uaem-green); display: inline-block;"></span> Activo
        </span>
      </div>
      
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5; margin: 0;">
          Esta función tiene carácter permanente y recibe un seguimiento continuo. Puedes registrar avances o reportes de las acciones realizadas para mantener actualizado el expediente de auditoría del departamento.
        </p>
        
        <div style="display: flex; gap: 10px; margin-top: 8px;">
          <button class="action-btn-primary" style="font-size: 12px; padding: 8px 16px; background-color: var(--uaem-green); display: flex; align-items: center; gap: 6px; border: none; border-radius: 6px; color: white; cursor: pointer; font-weight: 600;" id="btn-registrar-avance">
            <i data-lucide="edit-3"></i> Registrar Avance en Bitácora
          </button>
          <button class="action-btn-secondary" style="font-size: 12px; padding: 8px 16px; background: transparent; border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); cursor: pointer; display: flex; align-items: center; gap: 6px; font-weight: 600;" id="btn-ver-historial">
            <i data-lucide="history"></i> Ver Historial de Seguimiento
          </button>
        </div>
      </div>
    `;
    container.appendChild(trackingSec);
    
    // Attach event listeners
    const btnAvance = trackingSec.querySelector("#btn-registrar-avance");
    btnAvance.addEventListener("click", () => {
      const desc = prompt("Ingresa una descripción corta de las acciones de seguimiento continuo realizadas:", "Se revisó y dio seguimiento a las actividades programadas del periodo...");
      if (desc) {
        alert(`[Bitácora de Seguimiento]\n\nAvance registrado con éxito:\n"${desc}"\n\nEl reporte ha sido anexado al expediente digital del departamento para el monitoreo continuo.`);
      }
    });
    
    const btnHistorial = trackingSec.querySelector("#btn-ver-historial");
    btnHistorial.addEventListener("click", () => {
      alert(`[Historial de Monitoreo Continuo]\n\nÚltimos eventos:\n- 2026-07-01: Apertura de periodo de planeación física.\n- 2026-06-15: Carga del informe del trimestre anterior.\n\nEstatus General: Conforme y en seguimiento activo.`);
    });

    // Render the Linking section
    const linkSec = document.createElement("div");
    linkSec.style.marginTop = "32px";
    linkSec.style.padding = "20px";
    linkSec.style.backgroundColor = "var(--bg-primary)";
    linkSec.style.borderRadius = "var(--border-radius-md)";
    linkSec.style.border = "1px solid var(--border-color)";
    
    // Find active links for this function
    const activeLinks = state.mappedRelations.filter(rel => 
      rel.sourceDept === state.activeRole && rel.sourceFuncIdx === currentFnIndex
    );
    
    let linksHtml = "";
    if (activeLinks.length === 0) {
      linksHtml = `
        <div style="text-align: center; padding: 24px; color: var(--text-muted); font-size: 13px;">
          <i data-lucide="link-2-off" style="width: 24px; height: 24px; margin-bottom: 8px; opacity: 0.5; display: block; margin-left: auto; margin-right: auto;"></i>
          <p style="margin: 0;">No se han establecido enlaces para esta atribución.</p>
        </div>
      `;
    } else {
      linksHtml = `<div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">`;
      activeLinks.forEach((link, lidx) => {
        const targetDeptData = MO_DEPARTAMENTOS_DATA[link.targetDept];
        const targetFnText = targetDeptData?.functions[link.targetFuncIdx] || "Atribución Desconocida";
        const targetFnLabel = getShortFunctionTitle(targetFnText, link.targetFuncIdx);
        
        linksHtml += `
          <div class="glass-card" style="padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 6px; display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); box-shadow: none;">
            <div style="flex-grow: 1; margin-right: 16px; text-align: left;">
              <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 11px; font-weight: 700; color: var(--uaem-gold); text-transform: uppercase;">Enlace Registrado</span>
                <span style="font-size: 9px; background: var(--border-color); color: var(--text-secondary); padding: 1px 4px; border-radius: 4px;">Lectura</span>
              </div>
              <strong style="font-size: 13px; color: var(--text-primary); display: block; margin-top: 4px;">
                ${targetDeptData?.title || "Departamento Destino"}
              </strong>
              <span style="font-size: 12px; color: var(--text-secondary); cursor: pointer; text-decoration: underline;" class="link-go-to" data-dept="${link.targetDept}" data-idx="${link.targetFuncIdx}">
                Función: "${targetFnLabel}" (Ver atribución)
              </span>
              ${link.notes ? `<p style="font-size: 11px; color: var(--text-muted); margin: 6px 0 0 0; font-style: italic;">Nota: "${link.notes}"</p>` : ''}
            </div>
            <button class="action-btn-secondary btn-delete-link" data-id="${link.id}" style="color: var(--status-error); border-color: rgba(220, 53, 69, 0.2); background: rgba(220, 53, 69, 0.05); padding: 6px 12px; font-size: 11px; cursor: pointer; display: flex; align-items: center; gap: 4px; border-radius: 6px;">
              <i data-lucide="trash-2"></i> Quitar
            </button>
          </div>
        `;
      });
      linksHtml += `</div>`;
    }
    
    linkSec.innerHTML = `
      <h3 style="font-size: 14px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; color: var(--text-primary);">
        <i data-lucide="link"></i> Enlaces Interdepartamentales Registrados
      </h3>
      
      ${linksHtml}
      
      <div style="border-top: 1px solid var(--border-color); padding-top: 20px; margin-top: 20px; text-align: left;">
        <h4 style="font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px;">Nuevo Vínculo de Proceso</h4>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
          <div>
            <label style="font-size: 11px; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 4px;">Departamento Destino:</label>
            <select id="link-target-dept" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); font-family: inherit; font-size: 12px;">
              <option value="">-- Seleccionar Área --</option>
            </select>
          </div>
          <div>
            <label style="font-size: 11px; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 4px;">Función Asociada:</label>
            <select id="link-target-func" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); font-family: inherit; font-size: 12px;" disabled>
              <option value="">-- Primero elige el área --</option>
            </select>
          </div>
        </div>
        
        <div style="margin-bottom: 16px;">
          <label style="font-size: 11px; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 4px;">Notas de Coordinación / Trámite:</label>
          <input type="text" id="link-notes" placeholder="Ej. Envío periódico de reportes, visto bueno técnico..." style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); font-family: inherit; font-size: 12px;" />
        </div>
        
        <div style="display: flex; justify-content: flex-end;">
          <button class="action-btn-primary" id="btn-save-link" style="font-size: 12px; padding: 8px 16px; background-color: var(--uaem-green); border: none; border-radius: 6px; color: white; font-weight: 600; display: flex; align-items: center; gap: 6px; cursor: pointer;" disabled>
            <i data-lucide="plus"></i> Enlazar Atribución
          </button>
        </div>
      </div>
    `;
    container.appendChild(linkSec);
    
    // Attach event listeners for linking
    const targetDeptSelect = linkSec.querySelector("#link-target-dept");
    const targetFuncSelect = linkSec.querySelector("#link-target-func");
    const notesInput = linkSec.querySelector("#link-notes");
    const saveLinkBtn = linkSec.querySelector("#btn-save-link");
    
    // Populate departments (except active one)
    const sortedDepts = Object.keys(MO_DEPARTAMENTOS_DATA)
      .filter(k => k !== state.activeRole)
      .sort((a, b) => MO_DEPARTAMENTOS_DATA[a].title.localeCompare(MO_DEPARTAMENTOS_DATA[b].title));
      
    sortedDepts.forEach(k => {
      const opt = document.createElement("option");
      opt.value = k;
      opt.textContent = MO_DEPARTAMENTOS_DATA[k].title;
      targetDeptSelect.appendChild(opt);
    });
    
    // When target department changes, populate functions list
    targetDeptSelect.addEventListener("change", (e) => {
      const targetKey = e.target.value;
      targetFuncSelect.innerHTML = "";
      
      if (!targetKey) {
        targetFuncSelect.innerHTML = '<option value="">-- Primero elige el área --</option>';
        targetFuncSelect.disabled = true;
        saveLinkBtn.disabled = true;
        return;
      }
      
      const targetData = MO_DEPARTAMENTOS_DATA[targetKey];
      if (!targetData.functions || targetData.functions.length === 0) {
        targetFuncSelect.innerHTML = '<option value="">Sin funciones disponibles</option>';
        targetFuncSelect.disabled = true;
        saveLinkBtn.disabled = true;
      } else {
        targetFuncSelect.innerHTML = '<option value="">-- Seleccionar Función --</option>';
        targetData.functions.forEach((fn, fidx) => {
          const opt = document.createElement("option");
          opt.value = fidx;
          const label = getShortFunctionTitle(fn, fidx);
          opt.textContent = `${fidx + 1}. ${label}`;
          targetFuncSelect.appendChild(opt);
        });
        targetFuncSelect.disabled = false;
      }
    });
    
    // Enable/disable save button
    targetFuncSelect.addEventListener("change", (e) => {
      saveLinkBtn.disabled = !e.target.value;
    });
    
    // Save link action
    saveLinkBtn.addEventListener("click", () => {
      const tDept = targetDeptSelect.value;
      const tFuncIdx = parseInt(targetFuncSelect.value);
      const notes = notesInput.value.trim();
      
      if (!tDept || isNaN(tFuncIdx)) return;
      
      const newRel = {
        id: "rel_" + Date.now(),
        sourceDept: state.activeRole,
        sourceFuncIdx: currentFnIndex,
        targetDept: tDept,
        targetFuncIdx: tFuncIdx,
        notes: notes
      };
      
      state.mappedRelations.push(newRel);
      localStorage.setItem("fi_mapped_relations", JSON.stringify(state.mappedRelations));
      
      alert("¡Enlace funcional registrado y mapeado con éxito!");
      renderInterface();
    });
    
    // Delete link action
    linkSec.querySelectorAll(".btn-delete-link").forEach(btn => {
      btn.addEventListener("click", (e) => {
        const id = btn.getAttribute("data-id");
        if (confirm("¿Estás seguro de que deseas eliminar este enlace?")) {
          state.mappedRelations = state.mappedRelations.filter(r => r.id !== id);
          localStorage.setItem("fi_mapped_relations", JSON.stringify(state.mappedRelations));
          renderInterface();
        }
      });
    });
    
    // Link navigation ("go to" read only)
    linkSec.querySelectorAll(".link-go-to").forEach(el => {
      el.addEventListener("click", () => {
        const dept = el.getAttribute("data-dept");
        const idx = parseInt(el.getAttribute("data-idx"));
        state.browseDeptKey = dept;
        state.activeContextItemIndex = idx;
        renderInterface();
      });
    });
  } else {
    // In read-only mode, we can show a back button to return to our own department's functions!
    const backSec = document.createElement("div");
    backSec.style.marginTop = "24px";
    backSec.style.display = "flex";
    backSec.style.justifyContent = "flex-start";
    
    const btnRegresar = document.createElement("button");
    btnRegresar.className = "action-btn-secondary";
    btnRegresar.innerHTML = `<i data-lucide="arrow-left"></i> Volver a Mis Funciones`;
    btnRegresar.addEventListener("click", () => {
      state.browseDeptKey = state.activeRole;
      state.activeContextItemIndex = 0;
      renderInterface();
    });
    backSec.appendChild(btnRegresar);
    container.appendChild(backSec);
  }
  
  DOM.canvasMainBody.appendChild(container);
}

// 6.2.3 RELACIONES TAB
function renderRelacionesTab(role) {
  const relations = getRelationshipsFor(state.activeRole);
  const currentRel = relations.find(r => r.targetKey === state.relationTargetKey) || relations[0];
  
  if (!currentRel) {
    DOM.canvasMainBody.innerHTML = `<div class="glass-card"><p>No hay canales de interdepartamentales configurados para este rol.</p></div>`;
    return;
  }
  
  const container = document.createElement("div");
  container.className = "glass-card fade-in";
  
  container.innerHTML = `
    <h3 class="card-title"><i data-lucide="git-branch"></i> Conector de Relación Interdepartamental</h3>
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">
      Esta vista muestra la comunicación técnica formal de <strong>${role.title}</strong> con otros departamentos de la Facultad de Ingeniería.
    </p>
  `;
  
  // Visual Flow Diagram (Active -> Target)
  const diag = document.createElement("div");
  diag.className = "relationship-diagram";
  
  const activeNode = document.createElement("div");
  activeNode.className = "relation-node active-node";
  activeNode.innerHTML = `
    <div class="node-icon"><i data-lucide="briefcase"></i></div>
    <span class="node-name" style="font-size: 11px;">${role.title}</span>
    <span class="status-pill success" style="font-size: 8px; margin-top: 4px;">Emisor</span>
  `;
  
  const relType = currentRel.type || "sibling";
  const connector = document.createElement("div");
  connector.className = `relation-connector ${relType}`;
  connector.innerHTML = `<div class="connector-line-glow"></div>`;
  
  const targetNode = document.createElement("div");
  targetNode.className = "relation-node target-node";
  targetNode.innerHTML = `
    <div class="node-icon"><i data-lucide="${currentRel.icon}"></i></div>
    <span class="node-name" style="font-size: 11px;">${currentRel.title}</span>
    <span class="status-pill success" style="font-size: 8px; margin-top: 4px; background-color: var(--uaem-gold-light); color: var(--uaem-gold); border-color: var(--border-gold);">Destino</span>
    <span style="font-size: 8px; color: var(--text-muted); margin-top: 4px; text-decoration: underline; font-weight: 600;">👁️ Impersonar</span>
  `;
  
  // Click target node to impersonate and view functions
  targetNode.addEventListener("click", () => {
    state.activeTab = "funciones";
    state.browseDeptKey = currentRel.targetKey;
    state.activeContextItemIndex = 0; // select first function
    
    // Sync tab styling
    const tabs = [
      { el: DOM.tabPortal, name: "portal" },
      { el: DOM.tabFunciones, name: "funciones" },
      { el: DOM.tabRelaciones, name: "relaciones" },
      { el: DOM.tabDirectorio, name: "directorio" }
    ];
    tabs.forEach(t => {
      if (t.name === "funciones") t.el.classList.add("active");
      else t.el.classList.remove("active");
    });
    
    renderInterface();
  });
  
  diag.appendChild(activeNode);
  diag.appendChild(connector);
  diag.appendChild(targetNode);
  container.appendChild(diag);
  
  // Description and Actions
  const detailsSec = document.createElement("div");
  detailsSec.className = "relation-details";
  detailsSec.style.marginTop = "24px";
  detailsSec.innerHTML = `
    <h4 style="font-weight: 700; font-size: 14px; color: var(--uaem-green);">Acuerdo de Interacción:</h4>
    <div class="relation-desc-card">
      <strong>Propósito oficial:</strong> ${currentRel.desc} <br>
      <span style="font-size: 11px; color: var(--text-muted); font-style: italic; margin-top: 6px; display: inline-block;">
        Tipo de Conexión: ${relType === "hierarchical" ? "Jerárquica Directa (Línea Fuerte Continua)" : "Colaboración / Consulta (Línea Sibling Discontinua)"}
      </span>
    </div>
    <h4 style="font-weight: 700; font-size: 14px; color: var(--uaem-green); margin-top: 16px;">Operaciones conjuntas disponibles:</h4>
  `;
  
  const actionsList = document.createElement("div");
  actionsList.className = "relation-actions-list";
  
  currentRel.actions.forEach(act => {
    const btn = document.createElement("button");
    btn.className = "relation-action-btn";
    btn.innerHTML = `<i data-lucide="send"></i> <span>${act}</span>`;
    
    btn.addEventListener("click", () => {
      alert(`[Acción Cruzada]\nDe: ${role.title}\nPara: ${currentRel.title}\n\nOperación: "${act}"\n\nEstatus: Solicitud transmitida formalmente. Registrada en el sistema de mensajería del Manual de Organización.`);
    });
    
    actionsList.appendChild(btn);
  });
  
  detailsSec.appendChild(actionsList);
  container.appendChild(detailsSec);
  
  DOM.canvasMainBody.appendChild(container);
  
  // 6.2.3.2 ORGANIGRAMA INTERACTIVO SECTION
  const orgSection = document.createElement("div");
  orgSection.className = "organigrama-section fade-in";
  
  orgSection.innerHTML = `
    <h3 style="font-size: 16px; font-weight: 800; color: var(--uaem-green); margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
      <i data-lucide="network"></i> Organigrama y Consulta de Facultades (Añadir Relación)
    </h3>
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
      Estructura de jerarquía lineal y colaboración de la Facultad de Ingeniería. Los departamentos destacados con un <span style="color: var(--uaem-gold); font-weight: 700;">borde dorado</span> tienen relaciones de consulta activas o autorizadas. Haz clic sobre cualquier departamento para solicitar acceso o impersonar.
    </p>
  `;
  
  const buildOrganigramaNode = (key, titleShort) => {
    const isSelf = key === state.activeRole;
    const relations = getRelationshipsFor(state.activeRole);
    const hasRelation = relations.some(r => r.targetKey === key);
    
    const isApproved = state.consultationRequests.some(r => 
      r.sourceDept === state.activeRole && r.targetDept === key && r.status === "approved"
    );
    
    let nodeClass = "organigrama-node";
    if (isSelf) nodeClass += " active-role";
    else if (hasRelation || isApproved) nodeClass += " connected-role";
    
    const fullTitle = MO_DEPARTAMENTOS_DATA[key]?.title || titleShort;
    
    return `
      <div class="${nodeClass}" data-key="${key}" title="${fullTitle}">
        ${titleShort}
      </div>
    `;
  };
  
  const orgContainer = document.createElement("div");
  orgContainer.className = "organigrama-container";
  orgContainer.innerHTML = `
    <div class="organigrama-tree">
      <!-- TOP LAYER: Consejo de Gobierno & Dirección -->
      <div class="organigrama-row-top" style="margin-bottom: 16px;">
        ${buildOrganigramaNode("consejo_de_gobierno", "Consejo de Gobierno")}
        ${buildOrganigramaNode("direccion", "Dirección")}
        <div style="position: absolute; right: 26%; top: 76px;">
          ${buildOrganigramaNode("consejo_academico", "Consejo Académico")}
        </div>
      </div>
      
      <!-- MAIN BRANCHES SPLIT -->
      <div class="organigrama-row">
        <!-- BRANCH 1: SUBDIRECCIÓN ACADÉMICA -->
        <div class="organigrama-branch">
          ${buildOrganigramaNode("subdireccion_academica", "Subdirección Académica")}
          <div class="organigrama-sublist">
            ${buildOrganigramaNode("coordinacion_de_docencia_de_la_licenciatura_en_ingenieria_civil", "Lic. Ing. Civil")}
            ${buildOrganigramaNode("coordinacion_de_docencia_de_la_licenciatura_en_ingenieria_mecanica", "Lic. Ing. Mecánica")}
            ${buildOrganigramaNode("coordinacion_de_docencia_de_la_licenciatura_en_ingenieria_en_electronica", "Lic. Ing. Electrónica")}
            ${buildOrganigramaNode("coordinacion_de_docencia_de_la_licenciatura_en_ingenieria_en_computacion", "Lic. Ing. Computación")}
            ${buildOrganigramaNode("coordinacion_de_docencia_de_la_licenciatura_en_ingenieria_en_sistemas_energeticos_sustentables", "Lic. Ing. Sist. Energéticos")}
            ${buildOrganigramaNode("coordinacion_de_docencia_de_la_licenciatura_en_ingenieria_en_inteligencia_artificial", "Lic. Ing. Int. Artificial")}
            ${buildOrganigramaNode("departamento_de_materias_propedeuticas", "Materias Propedéuticas")}
            ${buildOrganigramaNode("departamento_de_servicios_escolares", "Servicios Escolares")}
            ${buildOrganigramaNode("departamento_de_evaluacion_profesional_y_grado", "Ev. Profesional y Grado")}
            ${buildOrganigramaNode("departamento_de_tutoria_academica", "Tutoría Académica")}
          </div>
        </div>
        
        <!-- BRANCH 2: COORDINACIONES Y CENTROS -->
        <div class="organigrama-branch">
          <div style="font-size: 11px; font-weight: bold; color: var(--text-muted); margin-bottom: 12px; border-bottom: 1px solid var(--border-color); width: 100%; text-align: center; padding-bottom: 4px;">Coordinaciones y Centros</div>
          <div class="organigrama-sublist" style="max-width: 200px;">
            ${buildOrganigramaNode("coordinacion_de_investigacion", "Coordinación de Investigación")}
            ${buildOrganigramaNode("coordinacion_de_estudios_avanzados", "Estudios Avanzados")}
            ${buildOrganigramaNode("coordinacion_de_difusion_cultural", "Difusión Cultural")}
            ${buildOrganigramaNode("coordinacion_de_extension_y_vinculacion", "Extensión y Vinculación")}
            ${buildOrganigramaNode("coordinacion_de_planeacion", "Coordinación de Planeación")}
            ${buildOrganigramaNode("centro_de_investigacion_en_ingenieria_estructural", "C.I. Ing. Estructural")}
            ${buildOrganigramaNode("centro_de_investigacion_en_movilidad_sustentable", "C.I. Movilidad Sustentable")}
            ${buildOrganigramaNode("departamento_de_educacion_continua", "Educación Continua")}
            <div class="organigrama-sublist" style="border-left: 2px dashed var(--uaem-gold); padding-left: 8px; margin-top: 8px;">
              ${buildOrganigramaNode("unidad_de_laboratorio_de_materiales", "U. Lab. Materiales")}
              ${buildOrganigramaNode("unidad_de_laboratorio_de_manufactura_y_metrologia", "U. Lab. Manufactura")}
            </div>
          </div>
        </div>
        
        <!-- BRANCH 3: SUBDIRECCIÓN ADMINISTRATIVA -->
        <div class="organigrama-branch">
          ${buildOrganigramaNode("subdireccion_administrativa", "Subdirección Administrativa")}
          <div class="organigrama-sublist">
            ${buildOrganigramaNode("departamento_de_tecnologias_de_la_informacion_y_comunicaciones", "Departamento de TIC")}
          </div>
        </div>
      </div>
    </div>
  `;
  
  orgSection.appendChild(orgContainer);
  DOM.canvasMainBody.appendChild(orgSection);
  
  // Bind click events to organigrama nodes
  orgContainer.querySelectorAll(".organigrama-node").forEach(node => {
    node.addEventListener("click", () => {
      const key = node.getAttribute("data-key");
      if (key === state.activeRole) {
        alert("Este es tu propio departamento activo.");
        return;
      }
      openOrganigramaModal(key);
    });
  });
  
  lucide.createIcons();
}

// Global modal builder for organigrama requests
function openOrganigramaModal(deptKey) {
  const deptData = MO_DEPARTAMENTOS_DATA[deptKey];
  if (!deptData) return;
  
  // Create modal backdrop and content
  const backdrop = document.createElement("div");
  backdrop.className = "modal-backdrop";
  
  // Find current requests status
  const existingReqs = state.consultationRequests.filter(r => 
    r.sourceDept === state.activeRole && r.targetDept === deptKey
  );
  const pendingReq = existingReqs.find(r => r.status === "pending");
  const approvedReq = existingReqs.find(r => r.status === "approved");
  
  let statusHtml = "";
  if (pendingReq) {
    statusHtml = `
      <div style="background-color: var(--uaem-gold-light); color: var(--uaem-gold); border: 1px solid var(--border-gold); padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; text-align: left;">
        <strong>Solicitud Pendiente:</strong> Tienes una solicitud en espera de aprobación por parte del titular. <br>
        <span style="font-size: 11px; color: var(--text-secondary);">Enviada el ${new Date(pendingReq.timestamp).toLocaleDateString()}</span>
      </div>
    `;
  } else if (approvedReq) {
    statusHtml = `
      <div style="background-color: var(--uaem-green-light); color: var(--uaem-green); border: 1px solid rgba(44, 82, 52, 0.2); padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; text-align: left;">
        <strong>Acceso Autorizado:</strong> El titular ha aprobado tu consulta para las funciones seleccionadas.
      </div>
    `;
  }
  
  let functionsListHtml = "";
  if (!deptData.functions || deptData.functions.length === 0) {
    functionsListHtml = `<p style="color: var(--text-muted); font-size: 13px;">No hay funciones registradas para este departamento.</p>`;
  } else {
    deptData.functions.forEach((fn, idx) => {
      const isApproved = approvedReq && approvedReq.requestedFuncs.includes(idx);
      const isPending = pendingReq && pendingReq.requestedFuncs.includes(idx);
      
      const cleanText = fn.replace(/^([IVX\d\.\)]+|Titular|Objetivo|Naturaleza Jur.dica)\.?\s*(-|:)?\s*/i, "").trim();
      const shortTitle = getShortFunctionTitle(fn, idx);
      
      let badge = "";
      let checkboxDisabled = "";
      let checkboxChecked = "";
      if (isApproved) {
        badge = `<span style="font-size: 9px; background-color: var(--uaem-green-light); color: var(--uaem-green); padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 8px;">Autorizado</span>`;
        checkboxDisabled = "disabled";
        checkboxChecked = "checked";
      } else if (isPending) {
        badge = `<span style="font-size: 9px; background-color: var(--uaem-gold-light); color: var(--uaem-gold); padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 8px;">Pendiente</span>`;
        checkboxDisabled = "disabled";
        checkboxChecked = "checked";
      }
      
      functionsListHtml += `
        <label style="display: flex; gap: 10px; align-items: flex-start; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; background-color: var(--bg-secondary); cursor: ${checkboxDisabled ? 'default' : 'pointer'}; margin-bottom: 8px; text-align: left;">
          <input type="checkbox" class="func-select-cb" data-idx="${idx}" ${checkboxChecked} ${checkboxDisabled} style="margin-top: 3px;" />
          <div style="font-size: 13px;">
            <strong style="color: var(--text-primary);">${idx + 1}. ${shortTitle}</strong> ${badge}
            <p style="color: var(--text-secondary); margin-top: 4px; font-size: 12px; line-height: 1.4;">"${cleanText.substring(0, 100)}..."</p>
          </div>
        </label>
      `;
    });
  }
  
  backdrop.innerHTML = `
    <div class="modal-content">
      <div class="modal-header">
        <h3 style="font-size: 16px; font-weight: 800; color: var(--uaem-green); display: flex; align-items: center; gap: 8px;">
          <i data-lucide="shield-alert"></i> Solicitar Consulta: ${deptData.title}
        </h3>
        <button id="btn-close-modal" style="background: transparent; border: none; font-size: 20px; cursor: pointer; color: var(--text-muted);">&times;</button>
      </div>
      <div class="modal-body">
        ${statusHtml}
        
        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px; text-align: left;">
          Selecciona las atribuciones específicas del Manual de Organización que requieres consultar y describe el motivo de la colaboración institucional:
        </p>
        
        <div style="max-height: 250px; overflow-y: auto; margin-bottom: 16px; border: 1px solid var(--border-color); padding: 8px; border-radius: 6px; background: var(--bg-primary);">
          ${functionsListHtml}
        </div>
        
        <div style="text-align: left; margin-bottom: 16px;">
          <label style="font-size: 11px; font-weight: 700; color: var(--text-secondary); display: block; margin-bottom: 6px;">JUSTIFICACIÓN DE LA SOLICITUD:</label>
          <textarea id="modal-justification" placeholder="Escribe el propósito de la consulta (ej. Cotejo de actas, planeación física anual...)" style="width: 100%; height: 80px; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); font-family: inherit; font-size: 13px; resize: none;" ${pendingReq ? 'disabled' : ''}>${pendingReq ? pendingReq.justification : ''}</textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="action-btn-secondary" id="btn-cancel-modal" style="font-size: 12px; padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; cursor: pointer; font-weight: 600; color: var(--text-primary);">
          Cerrar
        </button>
        ${approvedReq ? `
          <button class="action-btn-primary" id="btn-impersonate-modal" style="font-size: 12px; padding: 8px 16px; background-color: var(--uaem-green); border: none; border-radius: 6px; color: white; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 6px;">
            <i data-lucide="eye"></i> Impersonar de Solo Lectura
          </button>
        ` : ''}
        ${!pendingReq && !approvedReq ? `
          <button class="action-btn-primary" id="btn-submit-request" style="font-size: 12px; padding: 8px 16px; background-color: var(--uaem-green); border: none; border-radius: 6px; color: white; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 6px;" disabled>
            <i data-lucide="send"></i> Enviar Solicitud
          </button>
        ` : ''}
      </div>
    </div>
  `;
  
  document.body.appendChild(backdrop);
  lucide.createIcons();
  
  const closeBtn = backdrop.querySelector("#btn-close-modal");
  const cancelBtn = backdrop.querySelector("#btn-cancel-modal");
  const impersonateBtn = backdrop.querySelector("#btn-impersonate-modal");
  const submitBtn = backdrop.querySelector("#btn-submit-request");
  const justificationText = backdrop.querySelector("#modal-justification");
  const checkboxes = backdrop.querySelectorAll(".func-select-cb");
  
  const closeModal = () => {
    document.body.removeChild(backdrop);
  };
  
  closeBtn.addEventListener("click", closeModal);
  cancelBtn.addEventListener("click", closeModal);
  
  if (impersonateBtn) {
    impersonateBtn.addEventListener("click", () => {
      closeModal();
      state.activeTab = "funciones";
      state.browseDeptKey = deptKey;
      state.activeContextItemIndex = approvedReq.requestedFuncs[0] || 0;
      
      const tabs = [
        { el: DOM.tabPortal, name: "portal" },
        { el: DOM.tabFunciones, name: "funciones" },
        { el: DOM.tabRelaciones, name: "relaciones" },
        { el: DOM.tabDirectorio, name: "directorio" }
      ];
      tabs.forEach(t => {
        if (t.name === "funciones") t.el.classList.add("active");
        else t.el.classList.remove("active");
      });
      renderInterface();
    });
  }
  
  if (submitBtn) {
    const updateSubmitState = () => {
      const selected = Array.from(checkboxes).some(cb => cb.checked);
      submitBtn.disabled = !selected || !justificationText.value.trim();
    };
    
    checkboxes.forEach(cb => cb.addEventListener("change", updateSubmitState));
    justificationText.addEventListener("input", updateSubmitState);
    
    submitBtn.addEventListener("click", () => {
      const selectedFuncs = Array.from(checkboxes)
        .filter(cb => cb.checked)
        .map(cb => parseInt(cb.getAttribute("data-idx")));
      
      const newReq = {
        id: "req_" + Date.now(),
        sourceDept: state.activeRole,
        targetDept: deptKey,
        requestedFuncs: selectedFuncs,
        justification: justificationText.value.trim(),
        status: "pending",
        timestamp: new Date().toISOString()
      };
      
      state.consultationRequests.push(newReq);
      localStorage.setItem("fi_consultation_requests", JSON.stringify(state.consultationRequests));
      
      alert("¡Solicitud enviada al titular con éxito! Podrás impersonar y consultar estas funciones en cuanto el titular apruebe la solicitud.");
      closeModal();
      renderInterface();
    });
  }
}

// 6.2.4 DIRECTORIO TAB (ALL 34 DEPARTMENTS FROM MO)
function renderDirectorioTab() {
  const currentFilterIndex = state.activeContextItemIndex;
  const categories = ["all", "direccion", "academia", "investigacion", "extension", "administracion"];
  const activeFilter = categories[currentFilterIndex] || "all";
  
  const container = document.createElement("div");
  container.className = "glass-card fade-in";
  container.innerHTML = `
    <h3 class="card-title"><i data-lucide="users"></i> Estructura Departamental Oficial de la Facultad (34 Áreas en MO)</h3>
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 24px;">
      Este listado contiene todos los departamentos, coordinaciones y consejos oficiales descritos en el Manual de Organización. Haz clic en cualquiera de ellos para ver su objetivo y páginas en el PDF.
    </p>
  `;
  
  // Categorized grid
  const grid = document.createElement("div");
  grid.className = "directory-grid";
  
  // Render departments
  Object.keys(CATEGORY_NAMES).forEach(catKey => {
    if (activeFilter !== "all" && activeFilter !== catKey) return;
    
    // Scan all keys in MO data matching this category
    const catKeys = Object.keys(MO_DEPARTAMENTOS_DATA).filter(k => getDepartmentCategory(k) === catKey);
    if (catKeys.length === 0) return;
    
    const catCol = document.createElement("div");
    catCol.className = "directory-category";
    
    catCol.innerHTML = `
      <h4 class="directory-category-title">
        <i data-lucide="${CATEGORY_ICONS[catKey]}"></i>
        <span>${CATEGORY_NAMES[catKey]}</span>
      </h4>
    `;
    
    catKeys.sort((a,b) => MO_DEPARTAMENTOS_DATA[a].title.localeCompare(MO_DEPARTAMENTOS_DATA[b].title)).forEach(key => {
      const dept = MO_DEPARTAMENTOS_DATA[key];
      const card = document.createElement("div");
      card.className = "directory-card";
      
      const numFunctions = dept.functions ? dept.functions.length : 0;
      card.innerHTML = `
        <div class="directory-card-info">
          <span class="directory-card-name">${dept.title}</span>
          <span class="directory-card-code">${dept.origin}</span>
        </div>
        <span class="directory-card-badge">${numFunctions} Func.</span>
      `;
      
      // Clicking a directory card swaps the active role in the simulation!
      card.addEventListener("click", () => {
        state.activeRole = key;
        state.activeTab = "portal"; // reset to portal
        state.activeContextItemIndex = 0;
        
        // Initialize target relation key
        const relations = getRelationshipsFor(state.activeRole);
        state.relationTargetKey = relations[0]?.targetKey || "";
        
        // Update Selector and State
        DOM.roleSelector.value = key;
        updateRoleState();
        
        alert(`[Demo de Acceso Granular]\nSe ha cambiado la simulación al departamento:\n"${dept.title}"\n\nAhora puedes explorar sus funciones oficiales y relaciones en la interfaz.`);
      });
      
      catCol.appendChild(card);
    });
    
    grid.appendChild(catCol);
  });
  
  container.appendChild(grid);
  DOM.canvasMainBody.appendChild(container);
}

// Help elements mapping
function createListItem(label, icon, desc, isActive) {
  const li = document.createElement("li");
  li.className = `context-list-item ${isActive ? 'active' : ''}`;
  
  const i = document.createElement("i");
  i.setAttribute("data-lucide", icon);
  
  const content = document.createElement("div");
  content.className = "context-list-item-content";
  
  const titleSpan = document.createElement("span");
  titleSpan.textContent = label;
  
  const descSpan = document.createElement("span");
  descSpan.className = "item-desc";
  descSpan.textContent = desc;
  
  content.appendChild(titleSpan);
  content.appendChild(descSpan);
  
  li.appendChild(i);
  li.appendChild(content);
  return li;
}

// 7. INITIALIZATION ON PAGE LOAD
window.addEventListener("DOMContentLoaded", initApp);
