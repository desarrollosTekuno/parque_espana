import { DateTime } from "luxon";

const TZ = "America/Mexico_City";

// function formatDate(date) {
//   if (!date) return ""
//   return DateTime.fromISO(date).toFormat("dd/MM/yyyy HH:mm")
// }
export const formatDate = (date: string | null) => {
    if (!date) return "";
    return DateTime.fromISO(date).toFormat("dd/MM/yyyy");
};
export const formatDateTime = (date: string | null) => {
    if (!date) return "";
    return DateTime.fromISO(date).toFormat("dd/MM/yyyy hh:mm a");
};
export const formatDateTimeNoTZ = (date: string | null) => {
    if (!date) return "";

    return DateTime.fromISO(date, { setZone: true }).toFormat("dd/MM/yyyy hh:mm a");
};
export const formatTime = (time: string | null) => {
    if (!time) return "";
    return DateTime.fromISO(time).toFormat("hh:mm a");
};

/** Returns the current Mexico City time as a datetime-local input value ("YYYY-MM-DDTHH:mm"). */
export const nowAsLocalInput = (): string =>
    DateTime.now().setZone(TZ).toFormat("yyyy-MM-dd'T'HH:mm");

/**
 * Converts a UTC ISO string from the server to local Mexico City time
 * formatted for display ("dd/MM/yyyy hh:mm a").
 */
export const utcToLocalDisplay = (date: string | null): string => {
    if (!date) return "—";
    return DateTime.fromISO(date, { zone: "utc" })
        .setZone(TZ)
        .toFormat("dd/MM/yyyy hh:mm a");
};

/**
 * Converts a UTC ISO string from the server to a time-only display
 * in Mexico City timezone ("hh:mm a").
 */
export const utcToLocalTime = (date: string | null): string => {
    if (!date) return "—";
    return DateTime.fromISO(date, { zone: "utc" })
        .setZone(TZ)
        .toFormat("HH:mm");
};
