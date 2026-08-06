<script setup lang="ts">
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

interface Pagination {
    current_page: number;
    last_page: number;
    total: number;
}

const transactions = ref<Transaction[]>([]);
const pagination = ref<Pagination | null>(null);

const loading = ref(true);
const loadError = ref<string | null>(null);

const filters = ref({
    type: "",
    status: "",
    page: 1,
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

function applyFilter() {
    filters.value.page = 1;
    loadTransactions();
}

onMounted(() => {
    loadTransactions();
});
</script>

<template>

    <Head title="Transactions" />

    <div class="page">

        <header class="topbar">
            <button class="back-btn" @click="router.visit('/dashboard')">
                ← Dashboard
            </button>
        </header>


        <main class="content">

            <section class="card">

                <div class="card-header">
                    <h1>Transactions</h1>
                </div>


                <div class="filters">

                    <select v-model="filters.type" @change="applyFilter">
                        <option value="">
                            All Types
                        </option>

                        <option value="deposit">
                            Deposit
                        </option>

                        <option value="withdrawal">
                            Withdrawal
                        </option>
                    </select>


                    <select v-model="filters.status" @change="applyFilter">
                        <option value="">
                            All Status
                        </option>

                        <option value="pending">
                            Pending
                        </option>

                        <option value="success">
                            Success
                        </option>

                        <option value="failed">
                            Failed
                        </option>
                    </select>

                </div>


                <p v-if="loadError" class="error">
                    {{ loadError }}
                </p>


                <table v-else-if="!loading && transactions.length" class="txn-table">

                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>


                    <tbody>

                        <tr v-for="txn in transactions" :key="txn.id">

                            <td class="mono">
                                {{ txn.id }}
                            </td>


                            <td class="capitalize">
                                {{ txn.type }}
                            </td>


                            <td class="mono">
                                {{ formatAmount(txn) }}
                            </td>


                            <td>
                                {{ txn.payment_method ?? '-' }}
                            </td>


                            <td>
                                <span class="status-pill" :class="`is-${txn.status}`">
                                    {{ txn.status }}
                                </span>
                            </td>


                            <td class="mono muted">
                                {{ formatDate(txn.created_at) }}
                            </td>

                        </tr>

                    </tbody>

                </table>


                <p v-else-if="!loading" class="empty-state">
                    No transactions found.
                </p>


                <p v-else class="loading-state">
                    Loading transactions...
                </p>


                <div v-if="pagination" class="pagination">

                    <button :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">
                        Previous
                    </button>


                    <span>
                        Page {{ pagination.current_page }}
                        /
                        {{ pagination.last_page }}
                    </span>


                    <button :disabled="pagination.current_page === pagination.last_page"
                        @click="changePage(pagination.current_page + 1)">
                        Next
                    </button>

                </div>


            </section>

        </main>

    </div>
</template>


<style scoped>
.page {
    min-height: 100vh;
    background: #fafaf8;
    color: #1b2430;
    font-family: Inter, system-ui, sans-serif;
}

.topbar {
    padding: 20px 40px;
    border-bottom: 1px solid #e4e1d8;
}

.back-btn {
    background: none;
    border: none;
    cursor: pointer;
}

.content {
    padding: 40px;
}

.card {
    max-width: 1100px;
    margin: auto;
}

.card-header {
    margin-bottom: 20px;
}

.filters {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
}

select {
    padding: 10px;
}

.txn-table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 12px;
    border-bottom: 1px solid #e4e1d8;
    text-align: left;
}

.mono {
    font-family: monospace;
}

.muted {
    color: #5b6570;
}

.capitalize {
    text-transform: capitalize;
}

.status-pill {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 20px;
}

.error {
    color: #c9432c;
}
</style>