<script setup lang="ts">
import PlayerShell from "@/components/PlayerShell.vue";
import Pagination from "@/components/Pagination.vue";
import { DEFAULT_PAGE_SIZE } from "@/config/pagination";
import { useTour } from "@/composables/useTour";
import { Head, router } from "@inertiajs/vue3";
import { onMounted, ref } from "vue";

interface Transaction {
    id: string;
    type: string;
    amount: string;
    currency: string;
    status: string;
    payment_method: string | null;
    created_at: string;
}

interface PageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const transactions = ref<Transaction[]>([]);
const pagination = ref<PageMeta | null>(null);

const loading = ref(true);
const loadError = ref<string | null>(null);

const filters = ref({
    type: "",
    status: "",
    page: 1,
    per_page: DEFAULT_PAGE_SIZE,
});

function authHeaders(): HeadersInit {
    const token = localStorage.getItem("access_token");
    const tokenType = localStorage.getItem("token_type") ?? "Bearer";

    return {
        Accept: "application/json",
        Authorization: `${tokenType} ${token}`,
        "X-Tenant": window.location.hostname.split(".")[0],
    };
}

function formatAmount(transaction: Transaction) {
    return `${transaction.currency} ${Number(transaction.amount).toFixed(2)}`;
}

function formatDate(date: string) {
    return new Date(date).toLocaleString();
}

async function loadTransactions() {
    loading.value = true;
    loadError.value = null;

    try {
        const params = new URLSearchParams();

        params.append("page", String(filters.value.page));
        params.append("per_page", String(filters.value.per_page));

        if (filters.value.type) {
            params.append("type", filters.value.type);
        }

        if (filters.value.status) {
            params.append("status", filters.value.status);
        }

        const response = await fetch(
            `/api/transactions?${params.toString()}`,
            {
                headers: authHeaders(),
            }
        );

        if (response.status === 401) {
            router.visit("/login");
            return;
        }

        if (!response.ok) {
            throw new Error();
        }

        const data = await response.json();

        transactions.value = data.data;

        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page,
            total: data.total,
        };
    } catch {
        loadError.value =
            "Unable to load transactions. Please try again.";
    } finally {
        loading.value = false;
    }
}

function changePage(page: number) {
    if (!pagination.value) return;

    if (
        page < 1 ||
        page > pagination.value.last_page
    ) {
        return;
    }

    filters.value.page = page;

    loadTransactions();
}

function changePerPage(perPage: number) {
    filters.value.per_page = perPage;
    filters.value.page = 1;
    loadTransactions();
}

function applyFilter() {
    filters.value.page = 1;
    loadTransactions();
}

useTour("player-transaction", [
    {
        element: '[data-tour="txn-filters"]',
        popover: {
            title: "Filter your history",
            description: "Filter your transactions by type and status.",
        },
    },
    {
        element: '[data-tour="txn-list"]',
        popover: {
            title: "Your transactions",
            description: "A full record of your deposits and withdrawals.",
        },
    },
]);

onMounted(() => {
    loadTransactions();
});
</script>

<template>

    <Head title="Transactions" />

    <PlayerShell active="transaction" eyebrow="Wallet" title="Transactions">
        <template #actions>
            <button class="refresh-btn" :disabled="loading" @click="loadTransactions">
                {{ loading ? "Refreshing…" : "↻ Refresh" }}
            </button>
        </template>

        <section class="card">
            <div class="toolbar" data-tour="txn-filters">
                <div class="filter-group">
                    <span class="filter-label">Type</span>
                    <select v-model="filters.type" @change="applyFilter">
                        <option value="">All types</option>
                        <option value="deposit">Deposit</option>
                        <option value="withdrawal">Withdrawal</option>
                    </select>
                </div>

                <div class="filter-group">
                    <span class="filter-label">Status</span>
                    <select v-model="filters.status" @change="applyFilter">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="success">Success</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <span v-if="pagination" class="result-count muted">
                    {{ pagination.total }} result{{ pagination.total === 1 ? '' : 's' }}
                </span>
            </div>

            <p v-if="loadError" class="error">{{ loadError }}</p>

            <div v-else-if="!loading && transactions.length" class="table-wrap" data-tour="txn-list">
                <table class="txn-table">
                    <thead>
                        <tr>
                            <th class="num">#</th>
                            <th>Reference</th>
                            <th>Type</th>
                            <th class="right">Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th class="right">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(txn, i) in transactions" :key="txn.id">
                            <td class="mono muted num">
                                {{ ((pagination?.current_page ?? 1) - 1) * (pagination?.per_page ?? DEFAULT_PAGE_SIZE) + i + 1 }}
                            </td>
                            <td class="mono muted ref-cell">{{ txn.id }}</td>
                            <td>
                                <span class="type-chip" :class="txn.type === 'deposit' ? 'is-credit' : 'is-debit'">
                                    {{ txn.type }}
                                </span>
                            </td>
                            <td class="mono right" :class="txn.type === 'deposit' ? 'is-credit' : 'is-debit'">
                                {{ formatAmount(txn) }}
                            </td>
                            <td class="muted">{{ txn.payment_method ?? '—' }}</td>
                            <td>
                                <span class="status-pill" :class="`is-${txn.status}`">{{ txn.status }}</span>
                            </td>
                            <td class="mono muted right">{{ formatDate(txn.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else-if="!loading" class="empty-state">
                <span class="empty-mark">◆</span>
                <p>No transactions found.</p>
            </div>

            <p v-else class="loading-state">Loading transactions…</p>

            <Pagination v-if="pagination" :current-page="pagination.current_page"
                :last-page="pagination.last_page" :total="pagination.total"
                :per-page="pagination.per_page" @change="changePage"
                @per-page-change="changePerPage" />
        </section>
    </PlayerShell>
</template>


<style scoped>
.card {
    --ink: #1b2430;
    --slate: #5b6570;
    --paper: #fafaf8;
    --hairline: #e4e1d8;
    --signal: #2454ff;
    --success: #1f9d55;
    --pending: #c98a1c;
    --failed: #c9432c;

    background: white;
    border: 1px solid var(--hairline);
    border-radius: 14px;
    padding: 22px 26px;
    font-family: "Inter", system-ui, sans-serif;
    color: var(--ink);
}

.refresh-btn {
    background: white;
    border: 1px solid var(--hairline);
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 500;
    color: var(--slate);
    cursor: pointer;
}

.refresh-btn:hover:not(:disabled) {
    border-color: var(--slate);
    color: var(--ink);
}

.refresh-btn:disabled {
    opacity: 0.6;
    cursor: default;
}

/* Toolbar / filters */
.toolbar {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    padding-bottom: 18px;
    margin-bottom: 6px;
    border-bottom: 1px solid var(--hairline);
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-label {
    font-family: "JetBrains Mono", monospace;
    font-size: 10px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--slate);
}

select {
    padding: 9px 12px;
    border: 1px solid var(--hairline);
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    background: white;
    color: var(--ink);
    min-width: 150px;
}

select:focus {
    outline: 2px solid var(--signal);
    outline-offset: 1px;
    border-color: var(--signal);
}

.result-count {
    margin-left: auto;
    font-size: 13px;
}

.muted {
    color: var(--slate);
}

.mono {
    font-family: "JetBrains Mono", monospace;
}

/* Table */
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
    color: var(--slate);
    padding: 12px 8px;
    border-bottom: 1px solid var(--hairline);
    white-space: nowrap;
}

.txn-table td {
    padding: 14px 8px;
    border-bottom: 1px solid var(--hairline);
}

.txn-table tbody tr:last-child td {
    border-bottom: none;
}

.txn-table tbody tr:hover {
    background: rgba(27, 36, 48, 0.02);
}

.right {
    text-align: right;
}

.num {
    width: 1%;
    white-space: nowrap;
    text-align: right;
    padding-right: 16px;
}

.ref-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.is-credit {
    color: var(--success);
}

.is-debit {
    color: var(--failed);
}

.type-chip {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    text-transform: capitalize;
}

.type-chip.is-credit {
    background: rgba(31, 157, 85, 0.1);
    color: var(--success);
}

.type-chip.is-debit {
    background: rgba(201, 67, 44, 0.1);
    color: var(--failed);
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

/* States */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 48px 0;
    color: var(--slate);
    font-size: 14px;
}

.empty-mark {
    font-size: 28px;
    color: var(--hairline);
}

.loading-state {
    color: var(--slate);
    font-size: 14px;
    padding: 32px 0;
    text-align: center;
}

.error {
    color: var(--failed);
    font-size: 14px;
    padding: 20px 0;
}

/* Pagination */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid var(--hairline);
}

.page-btn {
    background: white;
    border: 1px solid var(--hairline);
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 500;
    color: var(--ink);
    cursor: pointer;
}

.page-btn:hover:not(:disabled) {
    border-color: var(--slate);
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: default;
}

.page-info {
    font-size: 13px;
}

@media (max-width: 640px) {
    .toolbar {
        flex-wrap: wrap;
    }

    .result-count {
        margin-left: 0;
    }
}
</style>