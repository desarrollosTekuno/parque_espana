
export const formatBytes = (bytes: number): string => {
    if (bytes >= 1_048_576) return `${(bytes / 1_048_576).toFixed(1)} MB`;
    if (bytes >= 1_024) return `${(bytes / 1_024).toFixed(0)} KB`;
    return `${bytes} B`;
};

export const getFileMeta = (mime: string | null) => {
    if (!mime) return { icon: "mdi-file-outline", color: "grey" };
    if (mime.startsWith("image/")) return { icon: "mdi-file-image", color: "teal" };
    if (mime === "application/pdf") return { icon: "mdi-file-pdf-box", color: "red" };
    if (mime.includes("word") || mime.includes("document")) return { icon: "mdi-file-word", color: "blue" };
    if (mime.includes("excel") || mime.includes("spreadsheet")) return { icon: "mdi-file-excel", color: "green" };
    return { icon: "mdi-file-outline", color: "grey" };
}