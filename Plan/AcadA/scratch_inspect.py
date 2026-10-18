with open(r"D:\Proyectos\plataformaFI\tools\generar_plan_preliminar.py", "r", encoding="utf-8") as f:
    lines = f.readlines()
print(f"Total lines in generar_plan_preliminar.py: {len(lines)}")
for l in lines[:45]:
    print(l.rstrip())
