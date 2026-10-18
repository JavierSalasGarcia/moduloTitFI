# Graph Report - d:\Proyectos\plataformaFI  (2026-07-17)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 811 nodes · 1746 edges · 94 communities (83 shown, 11 thin omitted)
- Extraction: 76% EXTRACTED · 24% INFERRED · 0% AMBIGUOUS · INFERRED: 412 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Auth
- FPDF
- Database
- FpdiTrait.php
- Controller
- StreamReader
- .Header
- self
- app.js
- PdfParser
- PdfType
- Fpdi
- FpdfTplTrait.php
- Lzw
- PdfArray
- PdfNumeric
- Buscador
- ModuleManager
- CrossReference
- Tokenizer
- PdfDictionary
- Page
- PdfString
- FpdfTplTrait
- TcpdfFpdi
- mo_data.js

## God Nodes (most connected - your core abstractions)
1. `Auth` - 123 edges
2. `FPDF` - 89 edges
3. `Database` - 61 edges
4. `flash()` - 61 edges
5. `PdfType` - 52 edges
6. `PdfParser` - 43 edges
7. `StreamReader` - 41 edges
8. `redirigir()` - 30 edges
9. `PdfDictionary` - 25 edges
10. `Controller` - 22 edges

## Surprising Connections (you probably didn't know these)
- `importPage()` --calls--> `PdfArray`  [INFERRED]
  lib/fpdi/src/FpdiTrait.php → lib/fpdi/src/PdfParser/Type/PdfArray.php
- `importPage()` --calls--> `PdfName`  [INFERRED]
  lib/fpdi/src/FpdiTrait.php → lib/fpdi/src/PdfParser/Type/PdfName.php
- `importPage()` --calls--> `PdfNumeric`  [INFERRED]
  lib/fpdi/src/FpdiTrait.php → lib/fpdi/src/PdfParser/Type/PdfNumeric.php
- `importPage()` --calls--> `PageBoundaries`  [INFERRED]
  lib/fpdi/src/FpdiTrait.php → lib/fpdi/src/PdfReader/PageBoundaries.php
- `ComisionesController` --inherits--> `Controller`  [EXTRACTED]
  app/Modules/Comisiones/ComisionesController.php → app/Core/Controller.php

## Import Cycles
- None detected.

## Communities (94 total, 11 thin omitted)

### Community 0 - "Auth"
Cohesion: 0.05
Nodes (13): Auth, descargar_archivo(), flash(), guardar_archivo(), guardar_archivo_datos(), guardar_archivos(), url(), AjustesController (+5 more)

### Community 2 - "Database"
Cohesion: 0.06
Nodes (12): Database, ajuste(), redirigir(), Router, View, ComisionesController, DirectorioController, EncuestasController (+4 more)

### Community 3 - "FpdiTrait.php"
Cohesion: 0.07
Nodes (24): Exception, _put(), _putimages(), _putlinks(), _putxobjectdict(), FpdiException, adjustLastLink(), getPdfParserInstance() (+16 more)

### Community 4 - "Controller"
Cohesion: 0.05
Nodes (7): Controller, AlumnosController, BusquedaController, EventosController, FuncionesController, PortalController, Controller

### Community 5 - "StreamReader"
Cohesion: 0.08
Nodes (5): AbstractReader, FixedReader, LineReader, StreamReader, ReaderInterface

### Community 6 - ".Header"
Cohesion: 0.07
Nodes (5): Totp, WebAuthn, AuthController, CalendarioController, SeguridadController

### Community 7 - "self"
Cohesion: 0.07
Nodes (5): PdfFirmador, GraphicsState, Matrix, Vector, self

### Community 8 - "app.js"
Cohesion: 0.26
Nodes (20): CATEGORY_ICONS, CATEGORY_NAMES, createListItem(), DOM, getDepartmentCategory(), getRelationshipsFor(), getShortFunctionTitle(), initApp() (+12 more)

### Community 10 - "PdfType"
Cohesion: 0.12
Nodes (6): writePdfType(), PdfBoolean, PdfHexString, PdfNull, PdfToken, PdfType

### Community 11 - "Fpdi"
Cohesion: 0.16
Nodes (7): FpdfTpl, FpdfTrait, FpdiTrait, Fpdi, Fpdi, Fpdi, TCPDF

### Community 12 - "FpdfTplTrait.php"
Cohesion: 0.17
Nodes (11): beginTemplate(), getNextTemplateId(), getTemplateSize(), _out(), SetDrawColor(), SetFillColor(), SetFont(), SetFontSize() (+3 more)

### Community 13 - "Lzw"
Cohesion: 0.15
Nodes (5): FilterInterface, Ascii85, AsciiHex, Flate, Lzw

### Community 14 - "PdfArray"
Cohesion: 0.17
Nodes (3): PdfArray, PdfIndirectObjectReference, PdfReader

### Community 20 - "PdfDictionary"
Cohesion: 0.38
Nodes (3): importPage(), PdfDictionary, PdfStream

### Community 23 - "FpdfTplTrait"
Cohesion: 0.40
Nodes (4): FpdfTplTrait, FpdfTpl, FpdfTpl, tFPDF

## Knowledge Gaps
- **5 isolated node(s):** `CATEGORY_NAMES`, `CATEGORY_ICONS`, `state`, `DOM`, `MO_DEPARTAMENTOS_DATA`
  These have ≤1 connection - possible missing edges or undocumented components.
- **11 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Auth` connect `Auth` to `Database`, `Controller`, `.Header`, `self`?**
  _High betweenness centrality (0.425) - this node is a cross-community bridge._
- **Why does `PdfType` connect `PdfType` to `FpdiTrait.php`, `self`, `PdfParser`, `Fpdi`, `PdfArray`, `PdfNumeric`, `PdfDictionary`, `Page`, `PdfString`?**
  _High betweenness centrality (0.197) - this node is a cross-community bridge._
- **Why does `FPDF` connect `FPDF` to `.Header`, `FpdfTplTrait`?**
  _High betweenness centrality (0.170) - this node is a cross-community bridge._
- **Are the 112 inferred relationships involving `Auth` (e.g. with `.despachar()` and `.crear()`) actually correct?**
  _`Auth` has 112 INFERRED edges - model-reasoned connections that need verification._
- **Are the 54 inferred relationships involving `Database` (e.g. with `.crear()` and `.index()`) actually correct?**
  _`Database` has 54 INFERRED edges - model-reasoned connections that need verification._
- **Are the 60 inferred relationships involving `flash()` (e.g. with `.actualizarUsuario()` and `.crearUsuario()`) actually correct?**
  _`flash()` has 60 INFERRED edges - model-reasoned connections that need verification._
- **What connects `CATEGORY_NAMES`, `CATEGORY_ICONS`, `state` to the rest of the system?**
  _5 weakly-connected nodes found - possible documentation gaps or missing edges._