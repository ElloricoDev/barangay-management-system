const moduleMap = {
    residents: "Residents",
    certificates: "Certificates",
    blotter: "Blotter",
    dashboard: "Dashboard",
    users: "Users",
    roles: "Roles",
    role: "Role",
    permissions: "Permissions",
    permission: "Permission",
    audit: "Audit Logs",
    system: "System",
    logs: "Logs",
    backup: "Backup",
    reports: "Reports",
    finance: "Finance",
    financial: "Financial",
    payment: "Payment",
    payments: "Payments",
    official: "Official",
    receipts: "Receipts",
    collection: "Collection",
    transaction: "Transaction",
    history: "History",
    summary: "Summary",
    document: "Document",
    documents: "Documents",
    archive: "Archive",
    data: "Data",
    youth: "Youth",
    programs: "Programs",
    committee: "Committee",
    delegation: "Delegation",
    matrix: "Access Matrix",
    settings: "Settings",
    funds: "Funds",
    budget: "Budget",
    disbursement: "Disbursement",
};

const actionMap = {
    view: "View",
    create: "Create",
    update: "Update",
    delete: "Delete",
    approve: "Approve",
    reject: "Reject",
    submit: "Submit",
    release_if_approved: "Release (If Approved)",
    upload: "Upload",
    download: "Download",
    export: "Export",
    archive: "Archive",
    restore: "Restore",
    reset: "Reset",
    manage: "Manage",
    record: "Record",
    toggle: "Toggle",
};

const toWords = (value) =>
    String(value ?? "")
        .split(/[_-]/g)
        .filter(Boolean)
        .map((chunk) => moduleMap[chunk] ?? `${chunk.charAt(0).toUpperCase()}${chunk.slice(1)}`)
        .join(" ");

export const permissionLabel = (permission) => {
    const raw = String(permission ?? "").trim();
    if (!raw) return "-";

    const parts = raw.split(".");
    if (parts.length === 1) return toWords(parts[0]);

    const action = actionMap[parts[parts.length - 1]] ?? toWords(parts[parts.length - 1]);
    const resource = toWords(parts.slice(0, -1).join("_"));
    return `${resource}: ${action}`;
};
