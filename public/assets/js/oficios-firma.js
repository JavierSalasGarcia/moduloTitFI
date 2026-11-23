/*
 * Firma de oficios con la e.firma del SAT (FIEL), SIEMPRE en el navegador.
 * El certificado (.cer), la llave (.key) y la contraseña se leen aquí, se usan para firmar y se descartan: no se envían al servidor ni se
 * guardan. Al servidor solo viaja la firma (CMS/PKCS#7 desprendido) y el certificado público. Requiere node-forge (vendor/forge.min.js).
 */
(function (root) {
  'use strict';
  var F = root.forge;

  function aBinario(buffer) {
    var bytes = new Uint8Array(buffer), partes = [], paso = 0x8000;
    for (var i = 0; i < bytes.length; i += paso) partes.push(String.fromCharCode.apply(null, bytes.subarray(i, i + paso)));
    return partes.join('');
  }

  function leerArchivo(archivo) {
    return new Promise(function (ok, mal) {
      var r = new FileReader();
      r.onload = function () { ok(aBinario(r.result)); };
      r.onerror = function () { mal(new Error('No se pudo leer el archivo.')); };
      r.readAsArrayBuffer(archivo);
    });
  }

  function aHexTexto(hex) {
    // El «número de serie» del SAT es el serial del certificado escrito como texto ASCII (cada byte es un dígito).
    var txt = '';
    for (var i = 0; i < hex.length; i += 2) {
      var b = parseInt(hex.substr(i, 2), 16);
      if (b < 0x30 || b > 0x39) return hex.toUpperCase();
      txt += String.fromCharCode(b);
    }
    return txt;
  }

  function campo(attrs, nombre) {
    for (var i = 0; i < attrs.length; i++) if (attrs[i].shortName === nombre || attrs[i].name === nombre) return attrs[i].value;
    return '';
  }

  /** Abre la e.firma. Lanza Error con un mensaje claro si algo no cuadra. */
  function abrir(cerBin, keyBin, contrasena) {
    var cert;
    try { cert = F.pki.certificateFromAsn1(F.asn1.fromDer(cerBin)); }
    catch (e) { throw new Error('El archivo .cer no es un certificado válido. Elige el .cer de tu e.firma (no el de sellos digitales).'); }

    var ahora = new Date();
    if (ahora < cert.validity.notBefore || ahora > cert.validity.notAfter) throw new Error('El certificado no está vigente (vence el ' + cert.validity.notAfter.toLocaleDateString('es-MX') + ').');

    var ku = cert.getExtension('keyUsage');
    if (ku && !ku.digitalSignature && !ku.nonRepudiation) throw new Error('Este certificado no sirve para firmar documentos. ¿Es el .cer de la e.firma (y no el de sellos digitales)?');

    var privada;
    try {
      var info = F.pki.decryptPrivateKeyInfo(F.asn1.fromDer(keyBin), contrasena);
      if (!info) throw new Error('contraseña');
      privada = F.pki.privateKeyFromAsn1(info);
    } catch (e) { throw new Error('No se pudo abrir la llave: revisa la contraseña de tu e.firma y que el archivo sea el .key.'); }
    if (!privada || !privada.n) throw new Error('La llave no es una llave RSA válida.');
    if (privada.n.toString(16) !== cert.publicKey.n.toString(16)) throw new Error('La llave (.key) no corresponde a ese certificado (.cer).');

    var emisor = cert.issuer.attributes;
    return {
      cert: cert, privada: privada,
      resumen: {
        nombre: campo(cert.subject.attributes, 'CN'),
        serie: aHexTexto(cert.serialNumber),
        emisor: campo(emisor, 'O') || campo(emisor, 'CN'),
        desde: cert.validity.notBefore.toISOString(), hasta: cert.validity.notAfter.toISOString(),
        certificadoDer: F.util.encode64(cerBin)
      }
    };
  }

  /** Firma $contenido (cadena binaria) y devuelve el CMS desprendido en hexadecimal (SHA-256, RSA PKCS#1 v1.5). */
  function firmar(fiel, contenidoBin) {
    var p7 = F.pkcs7.createSignedData();
    p7.content = F.util.createBuffer(contenidoBin, 'raw');
    p7.addCertificate(fiel.cert);
    p7.addSigner({
      key: fiel.privada, certificate: fiel.cert, digestAlgorithm: F.pki.oids.sha256,
      authenticatedAttributes: [
        { type: F.pki.oids.contentType, value: F.pki.oids.data },
        { type: F.pki.oids.messageDigest },
        { type: F.pki.oids.signingTime, value: new Date() }
      ]
    });
    p7.sign({ detached: true });
    return F.util.bytesToHex(F.asn1.toDer(p7.toAsn1()).getBytes());
  }

  var api = { leerArchivo: leerArchivo, abrir: abrir, firmar: firmar, aBinario: aBinario };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.OficioFiel = api;
})(typeof window !== 'undefined' ? window : (typeof global !== 'undefined' ? global : this));
