<script setup lang="ts">
import Pagination from "@/components/Pagination.vue";
import PlatformShell from "@/components/PlatformShell.vue";
import { DEFAULT_PAGE_SIZE } from "@/config/pagination";
import { email, type Errors, maxLength, minLength, pattern, required, validate } from "@/utils/validation";
import { Head, router } from "@inertiajs/vue3";
import { computed, onMounted, reactive, ref } from "vue";

interface TenantRow {
    id: string;
    domain: string | null;
    user_count: number;
    total_in: string;
    total_out: string;
    provisioned: boolean;
}

const tenants = ref<TenantRow[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);

const creating = ref(false);
const createError = ref<string | null>(null);
const fieldErrors = ref<Errors>({});
const showCreate = ref(false);

// The tenant-resolving host suffix is fixed by the platform. The domain is not
// customisable: the tenant is resolved from the leading subdomain label on API
// requests (X-Tenant = hostname.split(".")[0]), so the label MUST equal the
// tenant ID. The domain is therefore derived from the ID and shown read-only.
const DOMAIN_SUFFIX = "merchant-wallet.test";

function openCreate() {
    form.id = form.admin_email = form.admin_password = "";
    createError.value = null;
    fieldErrors.value = {};
    showCreate.value = true;
}

function validateForm(): boolean {
    fieldErrors.value = validate(form, {
        id: [
            required("Tenant ID is required."),
            maxLength(64),
            pattern(/^[a-z0-9-]+$/, "Only lowercase letters, digits and hyphens are allowed."),
        ],
        admin_email: [required("Admin email is required."), email(), maxLength(255)],
        admin_password: [
            required("Admin password is required."),
            minLength(6, "Password must be at least 6 characters."),
        ],
    });
    return Object.keys(fieldErrors.value).length === 0;
}

// Normalise the tenant ID to a valid subdomain label: lowercase, digits, and
// hyphens only (matches the backend regex and keeps id/domain in sync).
function onTenantIdInput() {
    form.id = form.id.trim().toLowerCase().replace(/[^a-z0-9-]/g, "");
}

function closeCreate() {
    showCreate.value = false;
    createError.value = null;
    fieldErrors.value = {};
}

const form = reactive({
    id: "",
    admin_email: "",
    admin_password: "",
});

const fullDomain = computed(() => `${form.id || "<tenant-id>"}.${DOMAIN_SUFFIX}`);

const totals = computed(() => ({
    tenants: tenants.value.length,
    total_in: tenants.value.reduce((sum, t) => sum + Number(t.total_in), 0),
    total_out: tenants.value.reduce((sum, t) => sum + Number(t.total_out), 0),
    users: tenants.value.reduce((sum, t) => sum + t.user_count, 0),
}));

const netFloat = computed(() => totals.value.total_in - totals.value.total_out);

// The tenant list is returned in full, so paginate it on the client.
const page = ref(1);
const perPage = ref(DEFAULT_PAGE_SIZE);

const tenantMeta = computed(() => ({
    current_page: page.value,
    last_page: Math.max(1, Math.ceil(tenants.value.length / perPage.value)),
    per_page: perPage.value,
    total: tenants.value.length,
}));

const pagedTenants = computed(() =>
    tenants.value.slice((page.value - 1) * perPage.value, page.value * perPage.value)
);

function changePage(next: number) {
    page.value = next;
}

function changePerPage(next: number) {
    perPage.value = next;
    page.value = 1;
}

function authHeaders(): HeadersInit {
    const token = localStorage.getItem("access_token") ?? "";
    const tokenType = localStorage.getItem("token_type") ?? "Bearer";
    return {
        Accept: "application/json",
        "Content-Type": "application/json",
        Authorization: `${tokenType} ${token}`,
    };
}

function isTokenExpired(): boolean {
    const expiresAt = localStorage.getItem("expires_at");
    if (!expiresAt) return true;
    return Date.now() >= Number(expiresAt);
}

async function logout() {
    try {
        await fetch("/api/platform/logout", {
            method: "POST",
            headers: authHeaders(),
        });
    } catch (e) { }

    localStorage.removeItem("access_token");
    localStorage.removeItem("token_type");
    localStorage.removeItem("expires_at");
    router.visit("/login");
}

async function loadTenants() {
    loading.value = true;
    loadError.value = null;
    try {
        const res = await fetch("/api/platform/tenants", { headers: authHeaders() });
        if (res.status === 401) {
            logout();
            return;
        }
        if (!res.ok) {
            loadError.value = "Could not load tenants.";
            return;
        }
        const body = await res.json();
        tenants.value = body.data ?? [];
        if (page.value > tenantMeta.value.last_page) {
            page.value = tenantMeta.value.last_page;
        }
    } catch (e) {
        loadError.value = "Something went wrong loading tenants.";
    } finally {
        loading.value = false;
    }
}

async function createTenant() {
    if (!validateForm()) {
        return;
    }

    creating.value = true;
    createError.value = null;
    try {
        const res = await fetch("/api/platform/tenants", {
            method: "POST",
            headers: authHeaders(),
            body: JSON.stringify({
                id: form.id,
                domain: `${form.id}.${DOMAIN_SUFFIX}`,
                admin_email: form.admin_email,
                admin_password: form.admin_password,
            }),
        });
        if (res.status === 401) {
            logout();
            return;
        }
        if (res.status === 422) {
            const body = await res.json();
            createError.value = Object.values(body.errors ?? { m: ["Validation failed."] })
                .flat()
                .join(" ");
            return;
        }
        if (!res.ok) {
            createError.value = "Could not create tenant.";
            return;
        }
        form.id = form.admin_email = form.admin_password = "";
        showCreate.value = false;
        createError.value = null;
        await loadTenants();
    } catch (e) {
        createError.value = "Something went wrong creating the tenant.";
    } finally {
        creating.value = false;
    }
}

async function deleteTenant(id: string) {
    if (!confirm(`Delete tenant "${id}" and its database? This cannot be undone.`)) {
        return;
    }
    try {
        const res = await fetch(`/api/platform/tenants/${id}`, {
            method: "DELETE",
            headers: authHeaders(),
        });
        if (res.status === 401) {
            logout();
            return;
        }
        if (res.ok) {
            await loadTenants();
        }
    } catch (e) { }
}

onMounted(() => {
    if (isTokenExpired()) {
        router.visit("/login");
        return;
    }
    loadTenants();
});
</script>

<template>

    <Head title="Platform Dashboard" />

    <PlatformShell active="tenants" eyebrow="Central back office" title="Platform Dashboard">
        <template #actions>
            <button class="refresh-btn" :disabled="loading" @click="loadTenants">
                {{ loading ? "Refreshing…" : "↻ Refresh" }}
            </button>
            <button class="action-btn primary" @click="openCreate">+ New tenant</button>
        </template>

        <section class="float-card">
            <div class="float-info">
                <span class="eyebrow">Aggregate net float across tenants</span>
                <div v-if="loading" class="float-amount is-loading">—</div>
                <div v-else class="float-amount">MYR {{ netFloat.toFixed(2) }}</div>
                <span class="float-note">Deposits less withdrawals over all provisioned tenants.</span>
            </div>
        </section>

        <section class="stats-grid">
            <div class="stat-card">
                <span class="eyebrow">Total tenants</span>
                <div class="stat-value" :class="{ 'is-loading': loading }">
                    {{ loading ? "—" : totals.tenants }}
                </div>
            </div>
            <div class="stat-card">
                <span class="eyebrow">Total users</span>
                <div class="stat-value" :class="{ 'is-loading': loading }">
                    {{ loading ? "—" : totals.users }}
                </div>
            </div>
            <div class="stat-card">
                <span class="eyebrow">Total deposits</span>
                <div class="stat-value is-success" :class="{ 'is-loading': loading }">
                    {{ loading ? "—" : `MYR ${totals.total_in.toFixed(2)}` }}
                </div>
            </div>
            <div class="stat-card">
                <span class="eyebrow">Total withdrawals</span>
                <div class="stat-value" :class="{ 'is-loading': loading }">
                    {{ loading ? "—" : `MYR ${totals.total_out.toFixed(2)}` }}
                </div>
            </div>
        </section>

        <section class="transactions-card">
            <div class="card-header">
                <h2>Tenants</h2>
            </div>

            <p v-if="loadError" class="load-error">{{ loadError }}</p>

            <div v-else-if="!loading && tenants.length" class="table-wrap">
                <table class="txn-table">
                    <thead>
                        <tr>
                            <th class="num">#</th>
                            <th>Tenant</th>
                            <th>Domain</th>
                            <th>Status</th>
                            <th>Users</th>
                            <th>Deposits</th>
                            <th>Withdrawals</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(t, i) in pagedTenants" :key="t.id">
                            <td class="mono muted num">{{ (page - 1) * perPage + i + 1 }}</td>
                            <td class="mono truncate" :title="t.id">{{ t.id }}</td>
                            <td class="mono muted truncate" :title="t.domain ?? '—'">{{ t.domain ?? "—" }}</td>
                            <td>
                                <span class="status-pill" :class="t.provisioned ? 'is-active' : 'is-failed'">
                                    {{ t.provisioned ? "Active" : "Not provisioned" }}
                                </span>
                            </td>
                            <td class="mono">{{ t.user_count }}</td>
                            <td class="mono is-credit">MYR {{ Number(t.total_in).toFixed(2) }}</td>
                            <td class="mono is-debit">MYR {{ Number(t.total_out).toFixed(2) }}</td>
                            <td>
                                <button class="delete-btn" @click="deleteTenant(t.id)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else-if="!loading" class="empty-state">No tenants yet.</p>
            <p v-else class="loading-state">Loading tenants…</p>

            <Pagination v-if="!loading && tenants.length" :current-page="tenantMeta.current_page"
                :last-page="tenantMeta.last_page" :total="tenantMeta.total" :per-page="tenantMeta.per_page"
                @change="changePage" @per-page-change="changePerPage" />
        </section>

        <Teleport to="body">
            <div v-if="showCreate" class="modal-overlay" @click.self="closeCreate">
                <div class="modal" role="dialog" aria-modal="true">
                    <div class="modal-header">
                        <h2>Create tenant</h2>
                        <button class="modal-close" type="button" aria-label="Close" @click="closeCreate">×</button>
                    </div>
                    <form class="create-form" @submit.prevent="createTenant" novalidate>
                        <label>
                            <span>Tenant ID</span>
                            <input v-model="form.id" placeholder="tenant-id (a-z0-9-)"
                                :class="{ 'is-invalid': fieldErrors.id }" @input="onTenantIdInput" />
                            <span v-if="fieldErrors.id" class="field-error">{{ fieldErrors.id }}</span>
                        </label>
                        <label>
                            <span>Domain <em class="hint">— auto-synced with Tenant ID</em></span>
                            <input class="domain-readonly" :value="fullDomain" readonly tabindex="-1" />
                        </label>
                        <label>
                            <span>Admin email</span>
                            <input v-model="form.admin_email" type="email" placeholder="admin email"
                                :class="{ 'is-invalid': fieldErrors.admin_email }" />
                            <span v-if="fieldErrors.admin_email" class="field-error">{{ fieldErrors.admin_email }}</span>
                        </label>
                        <label>
                            <span>Admin password</span>
                            <input v-model="form.admin_password" type="password" placeholder="admin password"
                                :class="{ 'is-invalid': fieldErrors.admin_password }" />
                            <span v-if="fieldErrors.admin_password" class="field-error">{{ fieldErrors.admin_password }}</span>
                        </label>
                        <p v-if="createError" class="load-error">{{ createError }}</p>
                        <div class="form-actions">
                            <button class="action-btn" type="button" @click="closeCreate">Cancel</button>
                            <button class="action-btn primary" :disabled="creating" type="submit">
                                {{ creating ? "Creating…" : "Create" }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </PlatformShell>
</template>

<style scoped>
.float-card {
    --paper: #fafaf8;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    background: linear-gradient(135deg, #1b2430 0%, #232f3e 100%);
    color: var(--paper);
    border-radius: 16px;
    padding: 26px 32px;
    position: relative;
    overflow: hidden;
    margin-bottom: 24px;
    font-family: "Inter", system-ui, sans-serif;
}

.float-card::after {
    content: "◆";
    position: absolute;
    right: 8px;
    top: -24px;
    font-size: 150px;
    color: rgba(36, 84, 255, 0.10);
    pointer-events: none;
}

.float-card .eyebrow {
    color: rgba(250, 250, 248, 0.55);
}

.float-amount {
    font-family: "JetBrains Mono", monospace;
    font-size: 38px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 10px 0 6px;
}

.float-amount.is-loading {
    color: rgba(250, 250, 248, 0.4);
}

.float-note {
    font-size: 12px;
    color: rgba(250, 250, 248, 0.55);
}

.refresh-btn {
    background: white;
    border: 1px solid #e4e1d8;
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 500;
    color: #5b6570;
    cursor: pointer;
}

.refresh-btn:hover:not(:disabled) {
    border-color: #5b6570;
    color: #1b2430;
}

.refresh-btn:disabled {
    opacity: 0.6;
    cursor: default;
}

.action-btn {
    padding: 8px 14px;
    border-radius: 8px;
    border: 1px solid #e4e1d8;
    background: white;
    font-size: 13px;
    font-weight: 500;
    color: #5b6570;
    cursor: pointer;
}

.action-btn.primary {
    background: #2454ff;
    color: white;
    border-color: #2454ff;
}

.action-btn:disabled {
    opacity: 0.6;
    cursor: default;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    border: 1px solid #e4e1d8;
    border-radius: 12px;
    padding: 16px 18px;
    background: white;
}

.eyebrow {
    font-family: "JetBrains Mono", monospace;
    font-size: 11px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #5b6570;
}

.stat-value {
    font-family: "JetBrains Mono", monospace;
    font-size: 20px;
    font-weight: 600;
    margin-top: 6px;
    color: #1b2430;
    white-space: nowrap;
}

.stat-value.is-loading {
    color: #5b6570;
}

.stat-value.is-success {
    color: #1f9d55;
}

.create-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.create-form label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
    color: #5b6570;
}

.create-form input {
    padding: 9px 12px;
    border: 1px solid #e4e1d8;
    border-radius: 6px;
    font-size: 13px;
    color: #1b2430;
}

.create-form input:focus {
    outline: none;
    border-color: #2454ff;
    box-shadow: 0 0 0 3px rgba(36, 84, 255, 0.12);
}

.create-form input.is-invalid {
    border-color: #c9432c;
}

.create-form input.is-invalid:focus {
    box-shadow: 0 0 0 3px rgba(201, 67, 44, 0.12);
}

.field-error {
    font-size: 12px;
    color: #c9432c;
    margin-top: 4px;
}

.create-form .hint {
    font-style: normal;
    font-weight: 400;
    color: #9aa1a9;
}

.create-form .domain-readonly {
    font-family: "JetBrains Mono", monospace;
    color: #5b6570;
    background: #fafaf8;
    cursor: default;
}

.create-form .domain-readonly:focus {
    outline: none;
    border-color: #e4e1d8;
    box-shadow: none;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 4px;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(27, 36, 48, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 50;
    font-family: "Inter", system-ui, sans-serif;
}

.modal {
    background: white;
    border-radius: 14px;
    padding: 24px 28px 28px;
    width: 100%;
    max-width: 460px;
    box-shadow: 0 24px 60px rgba(27, 36, 48, 0.25);
    color: #1b2430;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
}

.modal-header h2 {
    font-size: 18px;
    margin: 0;
}

.modal-close {
    border: none;
    background: none;
    font-size: 24px;
    line-height: 1;
    color: #5b6570;
    cursor: pointer;
    padding: 0 4px;
}

.modal-close:hover {
    color: #1b2430;
}

.transactions-card {
    border: 1px solid #e4e1d8;
    border-radius: 12px;
    padding: 24px 28px;
    background: white;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.card-header h2 {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}

.table-wrap {
    overflow-x: auto;
}

.txn-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.txn-table th {
    text-align: left;
    font-size: 11px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #5b6570;
    padding: 8px 20px 8px 0;
    border-bottom: 1px solid #e4e1d8;
    white-space: nowrap;
}

.txn-table td {
    padding: 12px 20px 12px 0;
    border-bottom: 1px solid #e4e1d8;
    color: #1b2430;
    vertical-align: middle;
}

.txn-table th:last-child,
.txn-table td:last-child {
    padding-right: 0;
}

.truncate {
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mono {
    font-family: "JetBrains Mono", monospace;
}

.num {
    width: 1%;
    white-space: nowrap;
    text-align: right;
    padding-right: 16px;
}

.muted {
    color: #5b6570;
}

.status-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
}

.status-pill.is-active {
    background: rgba(31, 157, 85, 0.12);
    color: #1f9d55;
}

.status-pill.is-failed {
    background: rgba(201, 67, 44, 0.12);
    color: #c9432c;
}

.is-credit {
    color: #1f9d55;
}

.is-debit {
    color: #1b2430;
}

.delete-btn {
    border: 1px solid #e4b4aa;
    color: #c9432c;
    background: white;
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 12px;
    cursor: pointer;
}

.delete-btn:hover {
    background: #fbeae6;
}

.empty-state,
.loading-state,
.load-error {
    color: #5b6570;
    font-size: 14px;
    padding: 20px 0;
}

.load-error {
    color: #c9432c;
}

@media (max-width: 720px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
