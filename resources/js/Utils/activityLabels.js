const toTitle = (value) =>
    String(value ?? "")
        .replace(/[_-]+/g, " ")
        .replace(/\b\w/g, (char) => char.toUpperCase())
        .trim();

export const actionLabel = (action) => {
    const raw = String(action ?? "").trim();
    if (!raw) return "-";

    const parts = raw.split(".").filter(Boolean);
    const verbMap = {
        create: "Created",
        update: "Updated",
        delete: "Deleted",
        destroy: "Deleted",
        approve: "Approved",
        reject: "Rejected",
        submit: "Submitted",
        release: "Released",
        upload: "Uploaded",
        download: "Downloaded",
        export: "Exported",
        archive: "Archived",
        restore: "Restored",
        reset: "Reset",
        toggle: "Toggled",
        login: "Logged In",
        logout: "Logged Out",
        record: "Recorded",
    };

    if (parts.length === 1) {
        const single = parts[0].toLowerCase();
        return verbMap[single] ?? toTitle(parts[0]);
    }

    const verbRaw = parts[parts.length - 1].toLowerCase();
    const verb = verbMap[verbRaw] ?? toTitle(parts[parts.length - 1]);
    const target = toTitle(parts.slice(0, -1).join(" "));
    return `${verb} ${target}`.trim();
};

export const moduleLabel = (auditableType) => {
    if (!auditableType) return "-";
    const parts = String(auditableType).split("\\");
    return parts[parts.length - 1] || "-";
};
