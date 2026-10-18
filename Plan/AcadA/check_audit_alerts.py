import sys

sys.stdout.reconfigure(encoding='utf-8')

with open(r"D:\Proyectos\plataformaFI\Plan\AcadA\auditoria_sintactica_metodologica.txt", "r", encoding="utf-8") as f:
    lines = f.readlines()

issues = []
for i, line in enumerate(lines):
    if "Infinitivo: False" in line or "Infinitivo=False" in line or "Temporalidad=False" in line or "Consistencia numérica interna: False" in line:
        issues.append(f"Line {i+1}: {line.strip()} (Context: {lines[i-1].strip()})")

print(f"Total syntax/consistency alerts found: {len(issues)}")
for iss in issues[:20]:
    print("  ->", iss)
