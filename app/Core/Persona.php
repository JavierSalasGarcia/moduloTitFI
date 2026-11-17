<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Trato a las personas según su grado y género (campos de la cuenta): evita fórmulas como «Estimado(a)».
 * Una «persona» es un arreglo con nombre y, si se conocen, grado y genero ('masculino'|'femenino').
 */
final class Persona
{
    public const GRADOS = ['Dr.', 'Dra.', 'M. en C.', 'M. en I.', 'M. en C. C.', 'Mtro.', 'Mtra.', 'Ing.', 'Lic.', 'Arq.', 'C.P.', 'L.A.E.'];

    /** Persona con cuenta, por correo; null si no existe. */
    public static function porEmail(string $email): ?array
    {
        static $cache = [];
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        if (!array_key_exists($email, $cache)) {
            try {
                $u = Identidad::usuarioPorCorreo($email);   // correo vigente o uno anterior verificado
                $cache[$email] = $u ? ['nombre' => $u['nombre'], 'grado' => $u['grado'], 'genero' => $u['genero']] : null;
            } catch (\Throwable) {
                $cache[$email] = null;   // aún sin la migración
            }
            // Sin cuenta: el directorio de profesores da su género («Profesor» / «Profesora») y su nombre.
            $cache[$email] ??= Profesores::porEmail($email);
        }
        return $cache[$email];
    }

    public static function porId(int $id): ?array
    {
        try {
            return Database::una('SELECT nombre, grado, genero FROM usuarios WHERE id = ?', [$id]) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function genero(array $p): ?string
    {
        $g = $p['genero'] ?? null;
        return in_array($g, ['masculino', 'femenino'], true) ? $g : null;
    }

    /** «Dr. Javier Salas García» (grado y nombre; no repite el grado si el nombre ya lo trae). */
    public static function tratamiento(array $p, bool $mayusculas = false): string
    {
        $nombre = trim((string) ($p['nombre'] ?? ''));
        $grado = self::abreviarGrado(trim((string) ($p['grado'] ?? '')), self::genero($p));
        // No antepone el grado si el nombre ya trae cualquier tratamiento («DR. JUAN…», «M. en C. Ana…»).
        $yaTrae = preg_match('/^(DRA?|MTRA?|M\.?\s*EN\s*[A-Z]|LIC|ING|ARQ|C\.\s*P|L\.\s*A\.\s*E|DOCTORA?|MAESTRA?|LICENCIADA?|INGENIERA?)\b\.?/iu', $nombre);
        if ($grado !== '' && !$yaTrae && !preg_match('/^' . preg_quote(rtrim($grado, '.'), '/') . '\b/iu', $nombre)) {
            $nombre = $grado . ' ' . $nombre;
        }
        return $mayusculas ? mb_strtoupper($nombre) : $nombre;
    }

    /** Convierte un grado escrito completo («Doctorado», «Maestría en…», «Ingeniería») en su abreviatura de trato. */
    public static function abreviarGrado(string $grado, ?string $genero = null): string
    {
        $g = mb_strtolower($grado);
        $fem = $genero === 'femenino';
        return match (true) {
            $g === '' => '',
            str_starts_with($g, 'doctor') => $fem ? 'Dra.' : 'Dr.',
            str_starts_with($g, 'maestr') || str_starts_with($g, 'master') => $fem ? 'Mtra.' : 'Mtro.',
            str_starts_with($g, 'licenciad') || $g === 'licenciatura' => 'Lic.',
            str_starts_with($g, 'ingenier') => 'Ing.',
            str_starts_with($g, 'arquitect') => 'Arq.',
            str_starts_with($g, 'especialid') => $grado,
            default => $grado,
        };
    }

    /**
     * Nombre en mayúsculas («DR. JAVIER SALAS GARCIA») a nombre propio («Dr. Javier Salas Garcia»); un nombre que ya trae
     * minúsculas se respeta. Con $titulo, aplica la misma regla a un título de trabajo escrito todo en mayúsculas.
     */
    public static function nombrePropio(string $texto, bool $titulo = false): string
    {
        $texto = trim($texto);
        if ($texto === '' || mb_strtoupper($texto) !== $texto || !preg_match('/\p{Lu}/u', $texto)) {
            return $texto;
        }
        $menores = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'el', 'en', 'a', 'al', 'o', 'u', 'por', 'para', 'con', 'un', 'una'];
        $palabras = preg_split('/(\s+)/u', mb_strtolower($texto), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $salida = '';
        $primera = true;
        foreach ($palabras as $p) {
            if (trim($p) === '') { $salida .= $p; continue; }
            if ($titulo && !$primera) {
                $salida .= $p;          // títulos: solo la inicial en mayúscula (tipo oración)
            } elseif (!$primera && in_array($p, $menores, true)) {
                $salida .= $p;
            } else {
                $salida .= mb_strtoupper(mb_substr($p, 0, 1)) . mb_substr($p, 1);
            }
            $primera = false;
        }
        // Abreviaturas de grado conocidas («M. En C.» → «M. en C.»).
        return preg_replace('/\bM\. En\b/u', 'M. en', $salida) ?? $salida;
    }

    /** Elige la forma masculina o femenina; sin género conocido usa la masculina genérica. */
    public static function f(array $p, string $masculino, string $femenino): string
    {
        return self::genero($p) === 'femenino' ? $femenino : $masculino;
    }

    /** «Estimado Dr. Javier Salas García» / «Estimada Dra. …»; si no se conoce el género, un saludo neutro. */
    public static function saludo(array $p): string
    {
        $t = self::nombrePropio(self::tratamiento($p));
        return self::genero($p) === null ? 'Hola ' . $t : self::f($p, 'Estimado ', 'Estimada ') . $t;
    }

    /** Completa grado y género de una persona con los de su cuenta, si tiene. */
    public static function completar(array $p, string $email): array
    {
        $c = self::porEmail($email);
        if ($c) {
            $p['nombre'] = $p['nombre'] ?? $c['nombre'];
            $p['grado'] = $p['grado'] ?? $c['grado'];
            $p['genero'] = $p['genero'] ?? $c['genero'];
        }
        return $p;
    }
}
