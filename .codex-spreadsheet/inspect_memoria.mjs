import { FileBlob, SpreadsheetFile } from "@oai/artifact-tool";

const source = "C:/Users/edigi/Downloads/Memoria de cálculo NS 2026-2030Ultima.xlsx";
const workbook = await SpreadsheetFile.importXlsx(await FileBlob.load(source));
const sheets = await workbook.inspect({ kind: "sheet", include: "id,name", maxChars: 12000 });
console.log(sheets.ndjson);
for (let i = 0; i < 22; i++) {
  const sheet = workbook.worksheets.getItemAt(i);
  const header = await workbook.inspect({
    kind: "table",
    sheetId: sheet.name,
    range: "A1:W8",
    maxChars: 8000,
    tableMaxRows: 8,
    tableMaxCols: 23,
    tableMaxCellChars: 140,
  });
  console.log(header.ndjson);
}
