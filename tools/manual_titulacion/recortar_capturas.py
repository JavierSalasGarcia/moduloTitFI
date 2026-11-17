"""Quita los bordes de fondo uniforme de capturas ya tomadas (idempotente). Uso: python3 recortar_capturas.py <carpeta img>"""
import glob
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from lib import recortar_fondo  # noqa: E402
from PIL import Image  # noqa: E402

carpeta = sys.argv[1]
m = json.load(open(os.path.join(carpeta, '_capturas.json'), encoding='utf-8'))
n = 0
for f in glob.glob(os.path.join(carpeta, '*.jpg')):
    im = Image.open(f).convert('RGB')
    if im.width == 1036 and im.convert('L').crop((0, 200, 6, im.height)).getextrema()[1] >= 250:
        # Capturas tomadas con 6 px del menú lateral (versión anterior del arnés): se quitan.
        im = im.crop((6, 0, im.width, im.height))
    r = recortar_fondo(im)
    if r.size != Image.open(f).size:
        r.save(f, 'JPEG', quality=80, optimize=True, progressive=True)
        k = os.path.splitext(os.path.basename(f))[0]
        if k in m:
            m[k]['ancho'], m[k]['alto'] = r.size
        n += 1
json.dump(m, open(os.path.join(carpeta, '_capturas.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1, sort_keys=True)
print('recortadas', n)
