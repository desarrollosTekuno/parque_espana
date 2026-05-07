import type { ValidationRule } from "vuetify";

/* ─────────────────────────────
 * VALIDACIONES BÁSICAS
 * ───────────────────────────── */
export const required: ValidationRule = (v) => !!v || "El campo es requerido";

export const email: ValidationRule = (v) =>
  !v || /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/.test(v) || "El correo no es válido";

export const postalCode = (v: string) =>
  !v || /^\d{5}$/.test(v) || "El código postal debe tener 5 dígitos";

export const curp: ValidationRule = (v) =>
    /^[A-Z]{1}[AEIOU]{1}[A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM]{1}(AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS)[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]{1}\d{1}$/.test(
        v,
    ) || "La CURP no es válida";

export const selectRequired: ValidationRule = (v) => {
    if (Array.isArray(v))
        return v.length > 0 || "Debe seleccionar al menos un elemento";
    if (typeof v === "object" && v !== null)
        return (
            Object.keys(v).length > 0 || "Debe seleccionar al menos un elemento"
        );
    return !!v || "Debe seleccionar al menos un elemento";
};

export const onlyLetters = (v: string) => {
    if (!v) return true;
    return /^[A-Za-zÀ-ÿ\s]+$/.test(v) || "Solo se permiten letras y espacios";
};

export const alphaNumeric = (v: string) => {
    if (!v) return true;
    return /^[A-Za-zÀ-ÿ0-9,\s]+$/.test(v) || "Solo se permiten letras, numeros, comas y espacios";
};

export const sanitizeLetters = (value: string) => {
    // Reemplaza cualquier cosa que no sea letra ni espacio
    return value.replace(/[^A-Za-zÀ-ÿ\s]/g, "");
};

export const validAddress = (v: string) => {
    if (!v) return true;
    const pattern = /^[A-Za-zÀ-ÿ0-9\s,.\-#°/]+$/;
    return (
        pattern.test(v) ||
        "Solo se permiten letras, números y signos comunes (,.-#°/)"
    );
};

export const sanitizeAddress = (value: string) => {
    return value.replace(/[^A-Za-zÀ-ÿ0-9\s,.\-#°/]/g, "");
};

/* ─────────────────────────────
 * VALIDACIONES DE LONGITUD
 * ───────────────────────────── */
// export const minLength =
//     (min: number): ValidationRule =>
//     (v) =>
//         (v && v.length >= min) || `Debe tener al menos ${min} caracteres`;
export const minLength =
    (min: number): ValidationRule =>
    (v) =>
        !v || v.length >= min || `Debe tener al menos ${min} caracteres`;

export const maxLength =
    (max: number): ValidationRule =>
    (v) =>
        !v || v.length <= max || `Debe tener máximo ${max} caracteres`;

export const lengthBetween =
    (min: number, max: number): ValidationRule =>
    (v) =>
        (v && v.length >= min && v.length <= max) ||
        `Debe tener entre ${min} y ${max} caracteres`;

export const optionalLength =
    (min: number, max: number): ValidationRule =>
    (v) => {
        if (!v || v.toString().trim() === "") return true; // opcional
        const len = v.toString().length;
        if (len < min) return `Debe tener al menos ${min} caracteres`;
        if (len > max) return `Debe tener máximo ${max} caracteres`;
        return true;
    };

/* ─────────────────────────────
 * VALIDACIONES DE ARCHIVOS
 * ───────────────────────────── */
export const fileTypeRule =
    (allowedExtensions: string[]): ValidationRule =>
    (v: File | File[] | null) => {
        if (!v || (Array.isArray(v) && v.length === 0)) return true;
        const files = Array.isArray(v) ? v : [v];
        const invalid = files.find((file) => {
            const ext = file.name.split(".").pop()?.toLowerCase();
            return !ext || !allowedExtensions.includes(ext);
        });
        return (
            !invalid ||
            `Solo se permiten archivos con extensión: ${allowedExtensions.join(
                ", ",
            )}`
        );
    };

export const fileMaxSizeRule =
    (maxMB: number): ValidationRule =>
    (v: File | File[] | null) => {
        if (!v || (Array.isArray(v) && v.length === 0)) return true;
        const files = Array.isArray(v) ? v : [v];
        const tooBig = files.find((file) => file.size > maxMB * 1024 * 1024);
        return !tooBig || `El archivo supera el tamaño máximo de ${maxMB} MB`;
    };

export const fileMaxCountRule =
    (max: number): ValidationRule =>
    (v: File | File[] | null) => {
        if (!v) return true;
        const count = Array.isArray(v) ? v.length : 1;
        return (
            count <= max ||
            `Solo puedes subir hasta ${max} archivo${max > 1 ? "s" : ""}`
        );
    };

export const fileExactCountRule =
    (expected: number): ValidationRule =>
    (v: File | File[] | null) => {
        const count = !v ? 0 : Array.isArray(v) ? v.length : 1;
        return (
            count === expected ||
            `Debes subir exactamente ${expected} archivo${expected > 1 ? "s" : ""}`
        );
    };

export const requiredFileRule = (v: File[] | null) => {
    if (!v || v.length === 0) return "Este archivo es requerido";
    return true;
};

export const fileTypeAndSizeRule =
    (allowedExtensions: string[], maxMB: number): ValidationRule =>
    (v: File | File[] | null) => {
        if (!v || (Array.isArray(v) && v.length === 0)) return true;
        const files = Array.isArray(v) ? v : [v];

        const invalidType = files.find((file) => {
            const ext = file.name.split(".").pop()?.toLowerCase();
            return !ext || !allowedExtensions.includes(ext);
        });
        if (invalidType) {
            return `Solo se permiten archivos con extensión: ${allowedExtensions.join(
                ", ",
            )}`;
        }

        const tooBig = files.find((file) => file.size > maxMB * 1024 * 1024);
        if (tooBig) {
            return `El archivo supera el tamaño máximo de ${maxMB} MB`;
        }

        return true;
    };

/* ─────────────────────────────
 * VALIDACIONES DE FECHA
 * ───────────────────────────── */

export const validateFechaNacimiento = (fecha: string): string | true => {
    if (!fecha) {
        return "La fecha de nacimiento es obligatoria";
    }

    const fechaNacimiento = new Date(fecha);
    const hoy = new Date();

    // Validar que sea una fecha válida
    if (isNaN(fechaNacimiento.getTime())) {
        return "La fecha de nacimiento no es válida";
    }

    // Validar que no sea en el futuro
    if (fechaNacimiento > hoy) {
        return "La fecha de nacimiento no puede ser en el futuro";
    }

    // Calcular edad
    let edad = hoy.getFullYear() - fechaNacimiento.getFullYear();
    const mes = hoy.getMonth() - fechaNacimiento.getMonth();
    const dia = hoy.getDate() - fechaNacimiento.getDate();

    if (mes < 0 || (mes === 0 && dia < 0)) {
        edad--;
    }

    if (edad < 18) {
        return "Debes tener al menos 18 años";
    }

    return true;
};

/* ─────────────────────────────
 * VALIDACIONES DE TELÉFONO
 * ───────────────────────────── */
// ✅ Valida que el teléfono tenga formato correcto y 10 dígitos
export const validatePhone: ValidationRule = (value) => {
  if (!value) return true;

  return /^\d{10}$/.test(value) || "El teléfono debe tener exactamente 10 dígitos";
};

// const birthdateRule = (member: MemberForm) => {
//     return (value: string | null) => {
//         if (!value) return "La fecha de nacimiento es requerida";

//         const age = calculateAge(value);
//         member.age = age;

//         if (age === null) return "La fecha de nacimiento no es válida";

//         if (age < 0) return "La fecha de nacimiento no es válida";

//         if (isChildRelationship(member) && age > 24) {
//             return "Los hijos no pueden ser mayores de 24 años";
//         }

//         return true;
//     };
// };

// ✅ Formatea y limpia en tiempo real
export const formatPhone = (value: string): string => {
    if (!value) return "";

    // 🔹 Elimina todo lo que no sea número
    let cleaned = value.replace(/\D/g, "");

    // 🔹 Limita a 10 dígitos máximo
    cleaned = cleaned.slice(0, 10);

    // 🔹 Aplica formato dinámico
    if (cleaned.length > 2 && cleaned.length <= 6) {
        cleaned = `(${cleaned.slice(0, 2)}) ${cleaned.slice(2)}`;
    } else if (cleaned.length > 6) {
        cleaned = `(${cleaned.slice(0, 2)}) ${cleaned.slice(2, 6)}-${cleaned.slice(6, 10)}`;
    }

    return cleaned;
};

/* ─────────────────────────────
 * VALIDACIÓN DE CONTRASEÑA
 * ───────────────────────────── */
export const passwordRule: ValidationRule = (v) => {
    if (!v || v.trim() === "") return "La contraseña es obligatoria";

    const minLength = 8;
    const hasUpper = /[A-Z]/.test(v);
    const hasLower = /[a-z]/.test(v);
    const hasNumber = /\d/.test(v);
    const hasSymbol = /[@$!%*?&._-]/.test(v);

    if (v.length < minLength)
        return `Debe tener al menos ${minLength} caracteres`;
    if (!hasUpper) return "Debe contener al menos una letra mayúscula";
    if (!hasLower) return "Debe contener al menos una letra minúscula";
    if (!hasNumber) return "Debe contener al menos un número";
    if (!hasSymbol) return "Debe contener al menos un símbolo (@$!%*?&._-)";

    return true;
};

export const confirmPasswordRule =
    (password: string): ValidationRule =>
    (v) =>
        v === password || "Las contraseñas no coinciden";

export const phoneRule: ValidationRule = (v) => {
    if (!v || v.toString().trim() === "") return true; // campo opcional
    const digitsOnly = v.toString().replace(/\D/g, "");
    return /^\d{10}$/.test(digitsOnly) || "Debe tener exactamente 10 dígitos";
};
