export function authHeaders() {
    return {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `${localStorage.getItem("token_type") ?? "Bearer"} ${localStorage.getItem("access_token") ?? ""}`,
        "X-Tenant": window.location.hostname.split(".")[0],
    };
}

export function adminAuthHeaders() {
    return {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `${localStorage.getItem("admin_token_type") ?? "Bearer"} ${localStorage.getItem("admin_access_token") ?? ""}`,
        "X-Tenant": window.location.hostname.split(".")[0],
    };
}