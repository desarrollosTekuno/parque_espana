import { listenFirebaseMessages } from "../firebase";
import { customToastSwal } from "../utils/swal";

export function registerFirebaseForegroundListener() {
    listenFirebaseMessages((payload) => {
        if (payload.notification) {
            customToastSwal({
                icon: "info",
                title: payload.notification.title || "Notificacion",
                text: payload.notification.body || "",
            });
            return;
        }

        customToastSwal({
            icon: "info",
            title: "Notificacion",
            text: "Se recibio un mensaje de Firebase.",
        });
    });
}
