<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AdminShell from '@/components/AdminShell.vue';
import Pagination from '@/components/Pagination.vue';
import { useTour } from '@/composables/useTour';
import { DEFAULT_PAGE_SIZE } from '@/config/pagination';
import { adminAuthHeaders } from '@/utils/authHeaders';

interface AdminTransaction {
    id: number;
    merchant_order_id: string;
    user_email: string;
    type: 'deposit' | 'withdrawal';
    amount: string;
    status: 'pending' | 'success' | 'failed' | 'cancelled';
    created_at: string;
}

interface PageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const transactions = ref<AdminTransaction[]>([]);
const meta = ref<PageMeta | null>(null);
const page = ref(1);
const perPage = ref(DEFAULT_PAGE_SIZE);
const loading = ref(true);
const loadError = ref<string | null>(null);

const statusFilter = ref<string>('');
const typeFilter = ref<string>('');

async function loadTransactions() {
    loading.value = true;
    loadError.value = null;

    try {
        const params = new URLSearchParams();

        if (statusFilter.value) {
            params.set('status', statusFilter.value);
        }

        if (typeFilter.value) {
            params.set('type', typeFilter.value);
        }

        params.set('per_page', String(perPage.value));
        params.set('page', String(page.value));

        const res = await fetch(
            `/api/admin/transactions?${params.toString()}`,
            {
                headers: adminAuthHeaders(),
            },
        );

        if (res.status === 401) {
            router.visit('/admin/login');

            return;
        }

        if (res.status === 403) {
            loadError.value = "You don't have access to this area.";

            return;
        }

        if (!res.ok) {
            loadError.value = 'Could not load transactions right now.';

            return;
        }

        const body = await res.json();
        transactions.value = body.data ?? body;
        meta.value = body.meta ?? null;
    } catch {
        loadError.value = 'Something went wrong loading transactions.';
    } finally {
        loading.value = false;
    }
}

function changePage(next: number) {
    page.value = next;
    loadTransactions();
}

function changePerPage(next: number) {
    perPage.value = next;
    page.value = 1;
    loadTransactions();
}

function applyFilter() {
    page.value = 1;
    loadTransactions();
}

function formatAmount(txn: AdminTransaction): string {
    const sign = txn.type === 'deposit' ? '+' : '-';

    return `${sign}${Number(txn.amount).toFixed(2)}`;
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

useTour('admin-transactions', [
    {
        element: '[data-tour="filters"]',
        popover: {
            title: 'Filter transactions',
            description:
                'Narrow the list by type (deposit/withdrawal) and status.',
        },
    },
    {
        element: '[data-tour="txn-table"]',
        popover: {
            title: 'Transaction history',
            description:
                'Every transaction with its reference, user, amount, status and date.',
        },
    },
]);

onMounted(() => {
    loadTransactions();
});
</script>

<template>
    <Head title="Transactions" />

    <AdminShell
        active="transactions"
        eyebrow="Back office"
        title="Transactions"
    >
        <template #actions>
            <button
                class="refresh-btn"
                :disabled="loading"
                @click="loadTransactions"
            >
                {{ loading ? 'Refreshing…' : '↻ Refresh' }}
            </button>
        </template>

        <section class="transactions-card">
            <div class="card-header">
                <h2>All transactions</h2>
                <div class="filters" data-tour="filters">
                    <select v-model="typeFilter" @change="applyFilter">
                        <option value="">All types</option>
                        <option value="deposit">Deposit</option>
                        <option value="withdrawal">Withdrawal</option>
                    </select>
                    <select v-model="statusFilter" @change="applyFilter">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="success">Success</option>
                        <option value="failed">Failed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <p v-if="loadError" class="load-error">{{ loadError }}</p>

            <div
                v-else-if="!loading && transactions.length"
                class="table-wrap"
                data-tour="txn-table"
            >
                <table class="txn-table">
                    <thead>
                        <tr>
                            <th class="num">#</th>
                            <th>Reference</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(txn, i) in transactions" :key="txn.id">
                            <td class="mono muted num">
                                {{
                                    ((meta?.current_page ?? 1) - 1) *
                                        (meta?.per_page ?? perPage) +
                                    i +
                                    1
                                }}
                            </td>
                            <td
                                class="mono truncate"
                                :title="txn.merchant_order_id"
                            >
                                {{ txn.merchant_order_id }}
                            </td>
                            <td class="truncate" :title="txn.user_email">
                                {{ txn.user_email }}
                            </td>
                            <td class="capitalize">{{ txn.type }}</td>
                            <td
                                class="mono"
                                :class="
                                    txn.type === 'deposit'
                                        ? 'is-credit'
                                        : 'is-debit'
                                "
                            >
                                {{ formatAmount(txn) }}
                            </td>
                            <td>
                                <span
                                    class="status-pill"
                                    :class="`is-${txn.status}`"
                                    >{{ txn.status }}</span
                                >
                            </td>
                            <td class="mono muted">
                                {{ formatDate(txn.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else-if="!loading" class="empty-state">
                No transactions match these filters.
            </p>
            <p v-else class="loading-state">Loading transactions…</p>

            <Pagination
                v-if="meta"
                :current-page="meta.current_page"
                :last-page="meta.last_page"
                :total="meta.total"
                :per-page="meta.per_page"
                @change="changePage"
                @per-page-change="changePerPage"
            />
        </section>
    </AdminShell>
</template>

<style scoped>
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

.transactions-card {
    border: 1px solid #e4e1d8;
    border-radius: 12px;
    padding: 24px 28px;
    background: white;
    font-family: 'Inter', system-ui, sans-serif;
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
    border: 1px solid #e4e1d8;
    border-radius: 6px;
    font-size: 12px;
    background: white;
    color: #1b2430;
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

.num {
    width: 1%;
    white-space: nowrap;
    text-align: right;
    padding-right: 16px;
}

.mono {
    font-family: 'JetBrains Mono', monospace;
}

.muted {
    color: #5b6570;
}

.capitalize {
    text-transform: capitalize;
}

.is-credit {
    color: #1f9d55;
}

.is-debit {
    color: #1b2430;
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
    color: #1f9d55;
}

.status-pill.is-pending {
    background: rgba(201, 138, 28, 0.12);
    color: #c98a1c;
}

.status-pill.is-failed,
.status-pill.is-cancelled {
    background: rgba(201, 67, 44, 0.12);
    color: #c9432c;
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
</style>
