# Generador local de bitácoras SA

El script `generar_bitacora.py` crea siempre 100 folios criptográficamente
aleatorios (50 hojas carta, dos folios por hoja), superpone los QR sobre
`D:\SA\arte\bitacora.pdf` y guarda el resultado en
`D:\SA\arte\bitacora`.

## Preparación

```powershell
py -m pip install -r D:\Proyectos\plataformaFI\tools\sa\requirements.txt
```

## Ejecución

```powershell
py D:\Proyectos\plataformaFI\tools\sa\generar_bitacora.py
```

Cada ejecución produce un PDF y un TXT del lote. `folios_usados.txt` es el
historial acumulado: no debe borrarse ni editarse, pues evita reutilizar
folios en ejecuciones posteriores. Un archivo de bloqueo evita ejecuciones
simultáneas. Los folios contienen aproximadamente 129 bits de entropía y el
QR apunta a `https://fingenieria.mx/public/fila/sa/reg.php?<folio>`.

El folio no concede acceso al expediente. El sitio exige sesión y que el
correo de la cuenta esté incluido expresamente en la lista de acceso.

Para otra oficina se indican su prefijo y URL, por ejemplo:

```powershell
py generar_bitacora.py --prefijo DIR --url-base "https://fingenieria.mx/public/fila/direccion/reg.php?"
```
