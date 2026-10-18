import fs from "node:fs/promises";
import path from "node:path";
import { FileBlob, SpreadsheetFile } from "@oai/artifact-tool";

const workbookPath = "D:/Proyectos/plataformaFI/Plan/Memoria de cálculo NS 2026-2030Ultima.xlsx";
const previewDir = "D:/Proyectos/plataformaFI/tmp/memoria-calculo/previews-before";
const input = await FileBlob.load(workbookPath);
const workbook = await SpreadsheetFile.importXlsx(input);

const overview = await workbook.inspect({
  kind: "workbook,sheet,table",
  maxChars: 18000,
  tableMaxRows: 8,
  tableMaxCols: 12,
  tableMaxCellChars: 100,
});
console.log(overview.ndjson);

await fs.mkdir(previewDir, { recursive: true });
const sheets = workbook.worksheets.items;
for (let index = 0; index < sheets.length; index += 1) {
  const sheet = sheets[index];
  const safeName = `${String(index + 1).padStart(2, "0")}-${sheet.name.replace(/[^a-zA-Z0-9_-]+/g, "-")}`;
  const image = await workbook.render({ sheetName: sheet.name, autoCrop: "all", scale: 0.8, format: "png" });
  await fs.writeFile(path.join(previewDir, `${safeName}.png`), new Uint8Array(await image.arrayBuffer()));
}
console.log(JSON.stringify({ previewDir, sheetCount: sheets.length }, null, 2));
