import Swal, { SweetAlertOptions } from "sweetalert2";

const customSwal = (options) => {
    return Swal.fire({
        confirmButtonColor: "#43A047",
        cancelButtonColor: "#BDBDBD",
        confirmButtonText: "Confirmar",
        cancelButtonText: "Cancelar",
        denyButtonColor: "#D32F2F",
        denyButtonText: "Denegar",
        ...options,
    });
};

type ActionType = "approve" | "reject" | "payment" | "publish" | "default";

const actionConfig: Record<ActionType, any> = {
    approve: {
        confirmButtonColor: "#2E7D32",
        icon: "success",
    },
    reject: {
        confirmButtonColor: "#D32F2F",
        icon: "warning",
    },
    payment: {
        confirmButtonColor: "#F57C00",
        icon: "question",
    },
    publish: {
        confirmButtonColor: "#1976D2",
        icon: "info",
    },
    default: {
        confirmButtonColor: "#43A047",
        icon: "warning",
    }
};

type ConfirmOptions = SweetAlertOptions & {
    confirmText?: string;
    cancelText?: string;
    actionType?: ActionType;
};

const customToastSwal = (options) => {
    return Swal.fire({
        showCancelButton: false,
        showConfirmButton: false,
        showDenyButton: false,
        timer: 2500,
        timerProgressBar: true,
        toast: true,
        position: "top-end",
        customClass: {
            container: "my-swal",
        },
        didOpen: (popup) => {
            popup.addEventListener("mouseenter", Swal.stopTimer);
            popup.addEventListener("mouseleave", Swal.resumeTimer);
        },
        ...options,
    });
};
const customConfirmSwal = (options: ConfirmOptions = {}) => {

    const {
        title = "¿Estás segura?",
        text = "Esta acción es irreversible",
        confirmText = "Sí, continuar",
        cancelText = "Cancelar",
        icon = "warning",
        actionType = "default",
        ...rest
    } = options;

    const config = actionConfig[actionType];

    return Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonColor: config.confirmButtonColor,
        cancelButtonColor: "#BDBDBD",
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        reverseButtons: true,
        showLoaderOnConfirm: true,
        allowOutsideClick: false,
        allowEscapeKey: false,
        customClass: {
            container: "my-swal",
        },
        ...rest,
    });
};

 const confirmRejectWithReason = () => {
    return Swal.fire({
        title: "¿Rechazar anuncio?",
        text: "Debes indicar el motivo del rechazo",
        icon: "warning",
        input: "textarea",
        inputPlaceholder: "Escribe el motivo...",
        inputAttributes: {
            "aria-label": "Motivo de rechazo"
        },
        showCancelButton: true,
        confirmButtonText: "Sí, rechazar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#D32F2F",
        cancelButtonColor: "#BDBDBD",
        reverseButtons: true,

        inputValidator: (value) => {
            if (!value || value.trim().length < 5) {
                return "El motivo debe tener al menos 5 caracteres";
            }
            return null;
        }
    });
};
export { customConfirmSwal, customSwal, customToastSwal, confirmRejectWithReason };
