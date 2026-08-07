<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { onMounted, ref } from "vue";

interface GeneralInfo {
    total_payments: number;
    total_pending: number;
    total_successful: number;
    currency: string;
}

interface AdminTransaction {
    id: number;
    merchant_order_id: string;
    user_email: string;
    type: "deposit" | "withdrawal";
    amount: string;
    status: "pending" | "success" | "failed" | "cancelled";
    created_at: string;
}

const info = ref<GeneralInfo | null>(null);
const transactions = ref<AdminTransaction[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);

const statusFilter = ref<string>("");
const typeFilter = ref<string>("");

const floatBalance = ref<string | null>(null);
const floatLoading = ref(true);
const floatError = ref<string | null>(null);

function authHeaders(): HeadersInit {
    const token = localStorage.getItem("admin_access_token");
    const tokenType = localStorage.getItem("admin_token_type") ?? "Bearer";
    return {
        Accept: "application/json",
        Authorization: `${tokenType} ${token}`,
        "X-Tenant": window.location.hostname.split(".")[0],
    };
}

async function logout() {
    try {
        const response = await fetch("/api/admin/logout", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                Authorization: `${localStorage.getItem("admin_token_type") ?? "Bearer"} ${localStorage.getItem("admin_access_token") ?? ""
                    }`,
                "X-Tenant": window.location.hostname.split(".")[0],
            },
        });

        if (response.ok) {
            localStorage.removeItem("admin_access_token");
            localStorage.removeItem("admin_token_type");
            localStorage.removeItem("admin_expires_at");

            router.visit("/admin/login");
        }
    } catch (e) { }
}

async function loadDashboard() {
    loading.value = true;
    loadError.value = null;

    try {
        const params = new URLSearchParams();
        if (statusFilter.value) params.set("status", statusFilter.value);
        if (typeFilter.value) params.set("type", typeFilter.value);
        params.set("per_page", "20");

        const [infoRes, txnRes] = await Promise.all([
            fetch("/api/admin/payments/general-info", { headers: authHeaders() }),
            fetch(`/api/admin/transactions?${params.toString()}`, { headers: authHeaders() }),
        ]);

        if (infoRes.status === 401 || txnRes.status === 401) {
            logout();
            return;
        }

        if (infoRes.status === 403 || txnRes.status === 403) {
            loadError.value = "You don't have access to this area.";
            return;
        }

        if (!infoRes.ok || !txnRes.ok) {
            loadError.value = "Could not load admin data right now.";
            return;
        }

        info.value = await infoRes.json();
        const txnPage = await txnRes.json();
        transactions.value = txnPage.data ?? txnPage;
    } catch (e) {
        loadError.value = "Something went wrong loading the admin dashboard.";
    } finally {
        loading.value = false;
    }
}

async function loadFloatBalance() {
    floatLoading.value = true;
    floatError.value = null;

    try {
        const res = await fetch("/api/admin/payments/float-balance", {
            headers: authHeaders(),
        });

        if (res.status === 401) {
            logout();
            return;
        }

        if (!res.ok) {
            floatError.value = "Unavailable";
            return;
        }

        const data = await res.json();
        floatBalance.value = data.balance ?? null;
    } catch (e) {
        floatError.value = "Unavailable";
    } finally {
        floatLoading.value = false;
    }
}

function formatAmount(txn: AdminTransaction): string {
    const sign = txn.type === "deposit" ? "+" : "-";
    return `${sign}${Number(txn.amount).toFixed(2)}`;
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: "medium",
        timeStyle: "short",
    });
}

onMounted(() => {
    loadDashboard();
    loadFloatBalance();
});
</script>

<template>

    <Head title="Admin Dashboard" />

    <div class="page">
        <header class="topbar">
            <div class="brand">
                <span class="brand-mark">◆</span>
                <span class="brand-name">Ledger · Admin</span>
            </div>
            <div class="topbar-actions">
                <button class="settings-btn" @click="router.visit('/admin/gateway-settings')">
                    ⚙ Payment gateway settings
                </button>
                <button class="logout-btn" @click="logout">Log out</button>
            </div>
        </header>

        <main class="content">
            <section class="float-card">
                <div class="float-info">
                    <span class="eyebrow">Gateway merchant float balance</span>
                    <div v-if="floatLoading" class="float-amount is-loading">—</div>
                    <div v-else-if="floatBalance !== null" class="float-amount">
                        {{ info?.currency ?? "MYR" }} {{ Number(floatBalance).toFixed(2) }}
                    </div>
                    <div v-else class="float-amount is-error">
                        {{ floatError ?? "Unavailable" }}
                    </div>
                    <span class="float-note">Live balance held with the payment gateway.</span>
                </div>
                <button class="refresh-btn" :disabled="floatLoading" @click="loadFloatBalance">
                    {{ floatLoading ? "Refreshing…" : "↻ Refresh" }}
                </button>
            </section>

            <section class="stats-grid">
                <div class="stat-card">
                    <span class="eyebrow">Total transactions</span>
                    <div class="stat-value" :class="{ 'is-loading': loading }">
                        {{ loading ? "—" : info?.total_payments ?? 0 }}
                    </div>
                </div>
                <div class="stat-card">
                    <span class="eyebrow">Pending</span>
                    <div class="stat-value is-pending" :class="{ 'is-loading': loading }">
                        {{ loading ? "—" : info?.total_pending ?? 0 }}
                    </div>
                </div>
                <div class="stat-card">
                    <span class="eyebrow">Successful</span>
                    <div class="stat-value is-success" :class="{ 'is-loading': loading }">
                        {{ loading ? "—" : info?.total_successful ?? 0 }}
                    </div>
                </div>
                <div class="stat-card">
                    <span class="eyebrow">Default currency</span>
                    <div class="stat-value mono" :class="{ 'is-loading': loading }">
                        {{ loading ? "—" : info?.currency ?? "—" }}
                    </div>
                </div>
            </section>

            <section class="transactions-card">
                <div class="card-header">
                    <h2>All transactions</h2>
                    <div class="filters">
                        <select v-model="typeFilter" @change="loadDashboard">
                            <option value="">All types</option>
                            <option value="deposit">Deposit</option>
                            <option value="withdrawal">Withdrawal</option>
                        </select>
                        <select v-model="statusFilter" @change="loadDashboard">
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="success">Success</option>
                            <option value="failed">Failed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <p v-if="loadError" class="load-error">{{ loadError }}</p>

                <table v-else-if="!loading && transactions.length" class="txn-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="txn in transactions" :key="txn.id">
                            <td class="mono">{{ txn.merchant_order_id }}</td>
                            <td>{{ txn.user_email }}</td>
                            <td class="capitalize">{{ txn.type }}</td>
                            <td class="mono" :class="txn.type === 'deposit' ? 'is-credit' : 'is-debit'">
                                {{ formatAmount(txn) }}
                            </td>
                            <td>
                                <span class="status-pill" :class="`is-${txn.status}`">{{ txn.status }}</span>
                            </td>
                            <td class="mono muted">{{ formatDate(txn.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>

                <p v-else-if="!loading" class="empty-state">No transactions match these filters.</p>
                <p v-else class="loading-state">Loading transactions…</p>
            </section>
        </main>
    </div>
</template>

<style scoped>
.page {
    --ink: #1b2430;
    --slate: #5b6570;
    --paper: #fafaf8;
    --hairline: #e4e1d8;
    --signal: #2454ff;
    --success: #1f9d55;
    --pending: #c98a1c;
    --failed: #c9432c;

    min-height: 100vh;
    background: var(--paper);
    color: var(--ink);
    font-family: "Inter", system-ui, sans-serif;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 40px;
    border-bottom: 1px solid var(--hairline);
}

.brand {
    display: flex;
    align-items: center;
    gap: 8px;
}

.brand-mark {
    color: var(--signal);
    font-size: 14px;
}

.brand-name {
    font-family: "JetBrains Mono", monospace;
    font-size: 13px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--slate);
}

.topbar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.settings-btn,
.logout-btn {
    background: transparent;
    border: 1px solid var(--hairline);
    border-radius: 6px;
    padding: 8px 14px;
    font-size: 13px;
    color: var(--slate);
    cursor: pointer;
}

.settings-btn:hover,
.logout-btn:hover {
    border-color: var(--slate);
    color: var(--ink);
}

.content {
    max-width: 1100px;
    margin: 0 auto;
    padding: 40px;
    display: flex;
    flex-direction: column;
    gap: 28px;
}

.float-card {
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

.float-amount.is-error {
    color: #ff9c88;
    font-size: 22px;
}

.float-note {
    font-size: 12px;
    color: rgba(250, 250, 248, 0.55);
}

.float-card .refresh-btn {
    flex-shrink: 0;
    background: rgba(250, 250, 248, 0.06);
    border: 1px solid rgba(250, 250, 248, 0.22);
    border-radius: 8px;
    padding: 9px 16px;
    font-size: 13px;
    font-weight: 500;
    color: var(--paper);
    cursor: pointer;
    z-index: 1;
}

.float-card .refresh-btn:hover:not(:disabled) {
    background: rgba(250, 250, 248, 0.12);
}

.float-card .refresh-btn:disabled {
    opacity: 0.6;
    cursor: default;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.stat-card {
    border: 1px solid var(--hairline);
    border-radius: 12px;
    padding: 20px;
}

.eyebrow {
    font-family: "JetBrains Mono", monospace;
    font-size: 11px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--slate);
}

.stat-value {
    font-family: "JetBrains Mono", monospace;
    font-size: 28px;
    font-weight: 600;
    margin-top: 8px;
}

.stat-value.is-loading {
    color: var(--slate);
}

.stat-value.is-pending {
    color: var(--pending);
}

.stat-value.is-success {
    color: var(--success);
}

.transactions-card {
    border: 1px solid var(--hairline);
    border-radius: 12px;
    padding: 24px 28px;
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

.filters {
    display: flex;
    gap: 8px;
}

.filters select {
    padding: 6px 10px;
    border: 1px solid var(--hairline);
    border-radius: 6px;
    font-size: 12px;
    background: white;
    color: var(--ink);
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
    color: var(--slate);
    padding: 8px 0;
    border-bottom: 1px solid var(--hairline);
}

.txn-table td {
    padding: 12px 0;
    border-bottom: 1px solid var(--hairline);
}

.mono {
    font-family: "JetBrains Mono", monospace;
}

.muted {
    color: var(--slate);
}

.capitalize {
    text-transform: capitalize;
}

.is-credit {
    color: var(--success);
}

.is-debit {
    color: var(--ink);
}

.status-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    text-transform: capitalize;
}

.status-pill.is-success {
    background: rgba(31, 157, 85, 0.12);
    color: var(--success);
}

.status-pill.is-pending {
    background: rgba(201, 138, 28, 0.12);
    color: var(--pending);
}

.status-pill.is-failed,
.status-pill.is-cancelled {
    background: rgba(201, 67, 44, 0.12);
    color: var(--failed);
}

.empty-state,
.loading-state,
.load-error {
    color: var(--slate);
    font-size: 14px;
    padding: 20px 0;
}

.load-error {
    color: var(--failed);
}

@media (max-width: 720px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>