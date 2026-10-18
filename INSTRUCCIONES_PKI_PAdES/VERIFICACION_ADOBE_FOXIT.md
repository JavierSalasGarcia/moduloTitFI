# Distribución e instalación del certificado público raíz

El archivo que se entrega a los usuarios es `traza-fi-root-ca.crt.pem`: contiene únicamente la llave pública y los datos de la autoridad raíz. No contiene una llave privada.

## Publicación segura

1. Publique el archivo desde Traza FI mediante la ruta `/firmas/certificado`.
2. Publique su huella creada con el Algoritmo de Hash Seguro de 256 bits (SHA-256), función para detectar cambios, en un segundo canal institucional que no dependa del mismo servidor.
3. Lea la huella en una sesión presencial o inclúyala en un oficio firmado para el grupo piloto.
4. Nunca instruya a instalar un archivo recibido solo por correo o mensajería sin comparar la huella.

## Adobe Acrobat

1. Abra **Preferencias → Firmas → Identidades y certificados de confianza → Más**.
2. Importe el certificado raíz.
3. Habilite confianza para validar firmas de documentos, no para usos ajenos al proyecto.
4. Reinicie la aplicación y abra un PDF de prueba. El panel de firmas debe mostrar la cadena individual → intermedia → raíz.

## Foxit PDF Editor o Reader

1. Abra **Preferencias → Administrador de confianza**.
2. Importe la autoridad raíz en certificados confiables.
3. Limite la confianza a la validación de firmas.
4. Reinicie la aplicación y compruebe la cadena del PDF de prueba.

La marca “válida” indica integridad criptográfica y confianza local en la raíz instalada. No sustituye la aprobación institucional ni convierte por sí sola el esquema en firma electrónica avanzada.
