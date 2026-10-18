# Documento de Diseño: Identidad Visual y Estilos UAEMéx

Este documento recopila las directrices de identidad visual extraídas del **Manual de Identidad Institucional 2021-2025 de la UAEMéx** para su integración en los sistemas y módulos individuales de la plataforma de la Facultad de Ingeniería.

---

## 🎨 1. Paleta de Colores Institucionales

Los colores representan la identidad oficial y deben aplicarse estrictamente en la interfaz de usuario para mantener la coherencia gráfica.

### Colores Primarios (Uso Obligatorio)

1. **Verde Institucional (UAEM Verde)**
   - **Pantone**: 350 C
   - **RGB**: `44, 82, 52`
   - **HEX / HTML**: `#2C5234`
   - **CMYK**: `C: 80, M: 21, Y: 79, K: 64`
   - **Uso**: Fondos principales de barras de navegación (sidebar primario), botones de acción principal, tipografías para títulos de secciones clave.

2. **Oro Institucional (UAEM Oro)**
   - **Pantone**: 112 C
   - **RGB**: `156, 132, 18`
   - **HEX / HTML**: `#9C8412`
   - **CMYK**: `C: 10, M: 20, Y: 100, K: 40`
   - **Uso**: Acentos visuales, bordes de elementos activos (fichas seleccionadas), íconos destacados y enlaces interactivos activos.

### Colores Secundarios (Soporte e Imagotipo)

1. **Verde Salvia (Verde Secundario)**
   - **RGB**: `85, 135, 102`
   - **HEX / HTML**: `#558766`
   - **CMYK**: `C: 69, M: 28, Y: 65, K: 11`
   - **Uso**: Fondos secundarios de barras laterales extendidas (submenús), hovers de elementos de lista, badges e indicadores de estados activos.

2. **Oro Arena (Oro Secundario)**
   - **RGB**: `179, 159, 82`
   - **HEX / HTML**: `#B29E52`
   - **CMYK**: `C: 30, M: 29, Y: 74, K: 11`
   - **Uso**: Badges de alertas, fondos de botones secundarios, texto de etiquetas informativas e indicadores secundarios de estado.

---

## 🔤 2. Tipografía y Estilo de Texto

### Tipografía del Sistema:
- El manual oficial de la institución especifica el uso de la tipografía **Calibri** (en sus variantes Regular, Light, Italic, Bold) para documentos oficiales y cartelería, debido a su amplia disponibilidad en todos los sistemas operativos.
- Para aplicaciones web e interfaces digitales modernas, se recomienda usar **Plus Jakarta Sans**, **Inter** o **System-UI** como alternativas de renderizado web, manteniendo **Calibri** como tipografía de respaldo de sistema.

### Reglas de Aplicación:
- **Títulos de Nivel 1 (`h1`)**: Peso 800, tamaño 24px - 28px, color Verde Institucional.
- **Títulos de Sección / Tarjetas (`h2` / `h3`)**: Peso 700, tamaño 16px - 18px, color Verde Institucional o Texto Principal.
- **Títulos de Menús y Enlaces (`nav`)**: Peso 600, tamaño 13px - 14px, color Texto Secundario (`#4B5563`).
- **Detalle de Etiquetas y Subtexto**: Peso 400 o 500, tamaño 11px, color Texto Muted (`#9CA3AF`).

---

## 🚫 3. Restricciones Críticas de Identidad (Pág. 11 del Manual)

Al maquetar y diseñar módulos independientes, los desarrolladores de la plataforma deben seguir las siguientes prohibiciones:
1. **No cambiar ni invertir los colores institucionales**. El Verde y el Oro deben cumplir siempre con sus roles respectivos.
2. **No usar sombras artificiales o efectos tridimensionales exagerados** sobre el escudo institucional o logotipos oficiales.
3. **No deformar las proporciones originales** de la identidad gráfica ni de los contenedores de marca.
4. **No comprometer la lectura**. Todas las combinaciones de color Verde y Oro sobre fondos claros u oscuros deben cumplir con un contraste mínimo de **4.5:1** conforme a WCAG (Accesibilidad).
5. **No usar volumen, perspectivas o gradientes complejos** que no formen parte de la paleta plana especificada.
