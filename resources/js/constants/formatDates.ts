import { DateTime } from "luxon";

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
