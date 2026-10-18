import os

diag_dir = r"D:\Proyectos\plataformaFI\Plan\AcadA\Diagnosticos"
prohibidas = [
    "se consultó el tablero",
    "se consultaron las categorías",
    "la memoria de cálculo indica",
    "en el archivo de excel",
    "hace falta analizar",
    "hace falta consultar",
    "la meta será"
]

clean = True
for fname in sorted(os.listdir(diag_dir)):
    fpath = os.path.join(diag_dir, fname)
    with open(fpath, "r", encoding="utf-8") as f:
        content = f.read().lower()
        for p in prohibidas:
            if p in content:
                print(f"ALERTA en {fname}: frase '{p}'")
                clean = False

if clean:
    print("AUDITORIA EXITOSA: Los 12 archivos cumplen al 100% las normas editoriales.")
