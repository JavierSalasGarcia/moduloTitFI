import fs from "node:fs/promises";
import path from "node:path";
import { FileBlob, SpreadsheetFile } from "@oai/artifact-tool";

const previewDir = "D:/Proyectos/plataformaFI/tmp/memoria-calculo/previews-after";
await fs.mkdir(previewDir, { recursive: true });

const targets = [
  ["memory", "4 Investigación abierta", "A1:X16", "memoria-proyecto-04"],
  ["memory", "Metas adicionales", "A1:Q12", "memoria-adicionales"],
  ["matrix", "2 EstudiantadoEstProf", "B1:F36", "matriz-proyecto-02"],
  ["matrix", "4 Investigación abierta", "B1:F18", "matriz-proyecto-04"],
  ["matrix", "8 Deporte universitario", "B1:F18", "matriz-proyecto-08"],
  ["matrix", "14 Fortalecimiento del marco no", "B1:F14", "matriz-proyecto-14"],
  ["matrix", "20 Retribución social ", "B1:F32", "matriz-proyecto-20"],
];

const books = {
  memory: await SpreadsheetFile.importXlsx(await FileBlob.load(
    "D:/Proyectos/plataformaFI/Plan/Memoria de cálculo NS 2026-2030Ultima.xlsx"
  )),
  matrix: await SpreadsheetFile.importXlsx(await FileBlob.load(
    "D:/Proyectos/plataformaFI/Plan/Matriz de consistenciaUltima.xlsx"
  )),
};

const results = [];
for (const [bookName, workbook] of Object.entries(books)) {
  const errors = await workbook.inspect({
    kind: "match",
    searchTerm: "#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A|#NUM!|#NULL!|#SPILL!|#CALC!",
    options: { useRegex: true, maxResults: 300 },
    summary: `${bookName} formula error scan`,
    maxChars: 8000,
  });
  results.push({ bookName, formulaErrors: errors.ndjson });
}
for (const [bookName, sheetName, range, fileName] of targets) {
  try {
    const image = await books[bookName].render({ sheetName, range, scale: 1.1, format: "png" });
    const output = path.join(previewDir, `${fileName}.png`);
    await fs.writeFile(output, new Uint8Array(await image.arrayBuffer()));
    results.push({ sheetName, range, output, status: "ok" });
  } catch (error) {
    results.push({ sheetName, range, status: "error", error: String(error) });
  }
}
console.log(JSON.stringify(results, null, 2));
