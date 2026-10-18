import os
import json
import sys

sys.stdout.reconfigure(encoding='utf-8')

rev_base = r"D:\Proyectos\plataformaFI\storage\plan\revisiones"
print("Checking contents of:", rev_base)

if os.path.exists(rev_base):
    subdirs = os.listdir(rev_base)
    print(f"Found {len(subdirs)} subdirectories in revisiones")
    for sd in subdirs:
        sd_path = os.path.join(rev_base, sd)
        if os.path.isdir(sd_path):
            files = os.listdir(sd_path)
            print(f"Dir: {sd[:12]}... contains {len(files)} files: {files[:5]}")
            for f in files:
                if f.endswith(".json"):
                    fpath = os.path.join(sd_path, f)
                    try:
                        with open(fpath, "r", encoding="utf-8") as jf:
                            d = json.load(jf)
                        print(f"   File {f}: keys={list(d.keys())}")
                    except Exception as err:
                        print(f"   Error reading {f}: {err}")
else:
    print("Path does not exist!")
