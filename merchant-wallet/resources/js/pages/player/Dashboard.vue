<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { useTour } from '@/composables/useTour';

interface Transaction {
    id: string;
    type: string;
    amount: string;
    currency: string;
    status: string;
    created_at: string;
}

interface SummaryResponse {
    balance: string;
    currency: string;
    total_in: string;
    total_out: string;
}

interface PlayerProfile {
    name: string;
    email: string;
    phone_number: string;
}

const profile = ref<PlayerProfile | null>(null);
const balance = ref<string | null>(null);
const currency = ref('MYR');
const totalIn = ref<string>('0');
const totalOut = ref<string>('0');
const transactions = ref<Transaction[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);

function authHeaders(): HeadersInit {
    const token = localStorage.getItem('access_token');
    const tokenType = localStorage.getItem('token_type') ?? 'Bearer';

    return {
        Accept: 'application/json',
        Authorization: `${tokenType} ${token}`,
        'X-Tenant': window.location.hostname.split('.')[0],
    };
}

function isTokenExpired(): boolean {
    const expiresAt = localStorage.getItem('expires_at');

    if (!expiresAt) {
        return true;
    }

    return Date.now() >= Number(expiresAt);
}

async function logout() {
    try {
        const response = await fetch('api/logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                Authorization: `${localStorage.getItem('token_type') ?? 'Bearer'} ${
                    localStorage.getItem('access_token') ?? ''
                }`,
                'X-Tenant': window.location.hostname.split('.')[0],
            },
        });

        if (response.ok) {
            localStorage.removeItem('access_token');
            localStorage.removeItem('token_type');
            localStorage.removeItem('expires_at');

            router.visit('/login');
        }
    } catch {}
}

async function loadDashboard() {
    if (isTokenExpired()) {
        logout();

        return;
    }

    loading.value = true;
    loadError.value = null;

    try {
        const [walletRes, txnRes, meRes] = await Promise.all([
            fetch('/api/wallet/summary', { headers: authHeaders() }),
            fetch('/api/transactions?per_page=5', { headers: authHeaders() }),
            fetch('/api/me', { method: 'POST', headers: authHeaders() }),
        ]);

        if (
            walletRes.status === 401 ||
            txnRes.status === 401 ||
            meRes.status === 401
        ) {
            logout();

            return;
        }

        if (!walletRes.ok || !txnRes.ok || !meRes.ok) {
            loadError.value = 'Could not load your wallet right now.';

            return;
        }

        const summary: SummaryResponse = await walletRes.json();
        const txnPage = await txnRes.json();
        profile.value = await meRes.json();

        balance.value = summary.balance;
        currency.value = summary.currency;
        totalIn.value = summary.total_in;
        totalOut.value = summary.total_out;
        transactions.value = txnPage.data ?? txnPage;
    } catch (e: any) {
        loadError.value =
            e.message ?? 'Something went wrong loading your dashboard.';
    } finally {
        loading.value = false;
    }
}

function formatAmount(txn: Transaction): string {
    const sign = txn.type === 'deposit' ? '+' : '-';

    return `${sign}${Number(txn.amount).toFixed(2)}`;
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

const { start: startTour } = useTour('player-dashboard', [
    {
        element: '.sidebar .nav',
        popover: {
            title: 'Your wallet menu',
            description:
                'Move between your Overview, Deposit, Withdraw and Transactions here.',
        },
    },
    {
        element: '[data-tour="balance"]',
        popover: {
            title: 'Your balance',
            description: 'This is your available wallet balance.',
        },
    },
    {
        element: '[data-tour="quick-actions"]',
        popover: {
            title: 'Quick actions',
            description: 'Add funds or withdraw straight from your dashboard.',
        },
    },
    {
        element: '[data-tour="recent"]',
        popover: {
            title: 'Recent activity',
            description:
                'Your most recent transactions. Open Transactions for the full history.',
        },
    },
]);

onMounted(loadDashboard);
</script>

<template>
    <Head title="Dashboard" />

    <div class="app">
        <!-- ───────── Sidebar ───────── -->
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">◆</span>
                <span class="brand-name">Ledger</span>
            </div>

            <nav class="nav">
                <span class="nav-label">Menu</span>
                <button class="nav-item is-active">
                    <span class="nav-icon">▣</span> Overview
                </button>
                <button class="nav-item" @click="router.visit('/deposit')">
                    <span class="nav-icon">↓</span> Deposit
                </button>
                <button class="nav-item" @click="router.visit('/withdraw')">
                    <span class="nav-icon">↑</span> Withdraw
                </button>
                <button class="nav-item" @click="router.visit('/transaction')">
                    <span class="nav-icon">≣</span> Transactions
                </button>
            </nav>

            <button class="logout-btn" @click="logout">
                <span class="nav-icon">⏻</span> Log out
            </button>
        </aside>

        <!-- ───────── Main workspace ───────── -->
        <main class="workspace">
            <header class="workspace-header">
                <div>
                    <span class="eyebrow muted">Wallet</span>
                    <h1>Overview</h1>
                </div>
                <div class="header-actions">
                    <button
                        class="refresh-btn"
                        title="Take a guided tour of this page"
                        @click="startTour"
                    >
                        <span class="nav-icon">?</span> Tour
                    </button>
                    <button
                        class="refresh-btn"
                        :disabled="loading"
                        @click="loadDashboard"
                    >
                        <span class="nav-icon">↻</span>
                        {{ loading ? 'Refreshing…' : 'Refresh' }}
                    </button>
                </div>
            </header>

            <div class="grid">
                <!-- Balance hero -->
                <section class="balance-card" data-tour="balance">
                    <div class="balance-top">
                        <div v-if="profile" class="profile">
                            <span class="profile-avatar">{{
                                profile.name.charAt(0).toUpperCase()
                            }}</span>
                            <div class="profile-meta">
                                <span class="profile-name">{{
                                    profile.name
                                }}</span>
                                <span class="profile-line"
                                    >{{ profile.email }} ·
                                    {{ profile.phone_number }}</span
                                >
                            </div>
                        </div>
                        <span class="currency-tag">{{ currency }}</span>
                    </div>

                    <div class="balance-main">
                        <div class="balance-left">
                            <span class="eyebrow">Available balance</span>
                            <div
                                v-if="loading"
                                class="balance-amount is-loading"
                            >
                                —
                            </div>
                            <div
                                v-else-if="balance !== null"
                                class="balance-amount"
                            >
                                <span class="balance-currency">{{
                                    currency
                                }}</span>
                                {{ Number(balance).toFixed(2) }}
                            </div>
                            <div v-else class="balance-amount is-error">
                                Unavailable
                            </div>
                        </div>

                        <div class="actions" data-tour="quick-actions">
                            <button
                                class="action-btn is-primary"
                                @click="router.visit('/deposit')"
                            >
                                <span class="nav-icon">↓</span> Deposit
                            </button>
                            <button
                                class="action-btn"
                                @click="router.visit('/withdraw')"
                            >
                                <span class="nav-icon">↑</span> Withdraw
                            </button>
                        </div>
                    </div>
                </section>

                <!-- KPIs -->
                <section class="kpi kpi-in">
                    <div class="kpi-head">
                        <span class="eyebrow muted">Total in</span>
                        <span class="kpi-badge is-credit">↓</span>
                    </div>
                    <div class="kpi-amount is-credit">
                        {{ currency }} {{ Number(totalIn).toFixed(2) }}
                    </div>
                    <span class="kpi-note">Lifetime deposits</span>
                </section>

                <section class="kpi kpi-out">
                    <div class="kpi-head">
                        <span class="eyebrow muted">Total out</span>
                        <span class="kpi-badge is-debit">↑</span>
                    </div>
                    <div class="kpi-amount is-debit">
                        {{ currency }} {{ Number(totalOut).toFixed(2) }}
                    </div>
                    <span class="kpi-note">Lifetime withdrawals</span>
                </section>

                <section class="kpi kpi-net">
                    <div class="kpi-head">
                        <span class="eyebrow muted">Net flow</span>
                        <span class="kpi-badge">≈</span>
                    </div>
                    <div class="kpi-amount">
                        {{ currency }}
                        {{ (Number(totalIn) - Number(totalOut)).toFixed(2) }}
                    </div>
                    <span class="kpi-note">In minus out</span>
                </section>
            </div>

            <!-- Recent transactions -->
            <section class="transactions-card" data-tour="recent">
                <div class="card-header">
                    <div>
                        <h2>Recent transactions</h2>
                        <span class="card-sub muted"
                            >Your latest 5 movements</span
                        >
                    </div>
                </div>

                <p v-if="loadError" class="load-error">{{ loadError }}</p>

                <div
                    v-else-if="!loading && transactions.length"
                    class="table-wrap"
                >
                    <table class="txn-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Type</th>
                                <th class="right">Amount</th>
                                <th>Status</th>
                                <th class="right">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="txn in transactions" :key="txn.id">
                                <td class="mono muted ref-cell">
                                    {{ txn.id }}
                                </td>
                                <td>
                                    <span
                                        class="type-chip"
                                        :class="
                                            txn.type === 'deposit'
                                                ? 'is-credit'
                                                : 'is-debit'
                                        "
                                    >
                                        {{ txn.type }}
                                    </span>
                                </td>
                                <td
                                    class="mono right"
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
                                <td class="mono muted right">
                                    {{ formatDate(txn.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else-if="!loading" class="empty-state">
                    <span class="empty-mark">◆</span>
                    <p>No transactions yet.</p>
                    <button
                        class="action-btn is-primary"
                        @click="router.visit('/deposit')"
                    >
                        Make your first deposit
                    </button>
                </div>

                <p v-else class="loading-state">Loading transactions…</p>
            </section>
        </main>
    </div>
</template>

<style scoped>
.app {
    --ink: #1b2430;
    --slate: #5b6570;
    --paper: #fafaf8;
    --hairline: #e4e1d8;
    --signal: #2454ff;
    --success: #1f9d55;
    --pending: #c98a1c;
    --failed: #c9432c;

    display: grid;
    grid-template-columns: 248px 1fr;
    min-height: 100vh;
    background: var(--paper);
    color: var(--ink);
    font-family: 'Inter', system-ui, sans-serif;
}

/* ───────── Sidebar ───────── */
.sidebar {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 24px 16px;
    background: var(--ink);
    color: var(--paper);
    position: sticky;
    top: 0;
    height: 100vh;
}

.brand {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px 20px;
}

.brand-mark {
    color: var(--signal);
    font-size: 16px;
}

.brand-name {
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--paper);
}

.nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
}

.nav-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(250, 250, 248, 0.35);
    padding: 4px 12px 10px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 12px;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: rgba(250, 250, 248, 0.7);
    font-size: 14px;
    font-weight: 500;
    text-align: left;
    cursor: pointer;
    transition:
        background 0.15s ease,
        color 0.15s ease;
}

.nav-item:hover {
    background: rgba(250, 250, 248, 0.06);
    color: var(--paper);
}

.nav-item.is-active {
    background: rgba(36, 84, 255, 0.16);
    color: var(--paper);
    box-shadow: inset 2px 0 0 var(--signal);
}

.nav-icon {
    font-size: 13px;
    width: 16px;
    text-align: center;
    opacity: 0.9;
}

.logout-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid rgba(250, 250, 248, 0.14);
    border-radius: 8px;
    background: transparent;
    color: rgba(250, 250, 248, 0.7);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
}

.logout-btn:hover {
    border-color: rgba(250, 250, 248, 0.35);
    color: var(--paper);
}

/* ───────── Workspace ───────── */
.workspace {
    padding: 32px 40px 48px;
    max-width: 1100px;
    width: 100%;
}

.workspace-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 24px;
}

.workspace-header h1 {
    font-size: 24px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 4px 0 0;
}

.eyebrow {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(250, 250, 248, 0.55);
}

.muted {
    color: var(--slate);
}

/* ───────── Grid: hero + KPIs ───────── */
.grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.balance-card {
    grid-column: 1 / -1;
    background: linear-gradient(135deg, #1b2430 0%, #232f3e 100%);
    color: var(--paper);
    border-radius: 16px;
    padding: 20px 26px;
    position: relative;
    overflow: hidden;
}

.balance-card::after {
    content: '◆';
    position: absolute;
    right: -20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 150px;
    color: rgba(36, 84, 255, 0.1);
    pointer-events: none;
}

.balance-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 16px;
    margin-bottom: 16px;
    border-bottom: 1px solid rgba(250, 250, 248, 0.08);
}

.currency-tag {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.08em;
    color: rgba(250, 250, 248, 0.7);
    border: 1px solid rgba(250, 250, 248, 0.2);
    border-radius: 999px;
    padding: 3px 10px;
}

.profile {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.profile-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: rgba(36, 84, 255, 0.22);
    color: var(--paper);
    font-weight: 600;
    font-size: 15px;
    flex-shrink: 0;
}

.profile-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.profile-name {
    font-size: 14px;
    font-weight: 600;
}

.profile-line {
    font-size: 12px;
    color: rgba(250, 250, 248, 0.6);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.balance-main {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
}

.balance-amount {
    font-family: 'JetBrains Mono', monospace;
    font-size: 34px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 6px 0 0;
    line-height: 1.1;
}

.balance-currency {
    font-size: 16px;
    color: rgba(250, 250, 248, 0.55);
    margin-right: 6px;
}

.balance-amount.is-loading {
    color: rgba(250, 250, 248, 0.4);
}

.balance-amount.is-error {
    color: #ff9c88;
    font-size: 20px;
}

.actions {
    display: flex;
    gap: 10px;
    flex-shrink: 0;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    border: 1px solid rgba(250, 250, 248, 0.22);
    background: rgba(250, 250, 248, 0.04);
    color: var(--paper);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition:
        transform 0.1s ease,
        opacity 0.15s ease;
}

.action-btn.is-primary {
    background: var(--signal);
    border-color: var(--signal);
}

.action-btn:hover {
    opacity: 0.92;
    transform: translateY(-1px);
}

/* ───────── KPI cards ───────── */
.kpi {
    background: white;
    border: 1px solid var(--hairline);
    border-radius: 14px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.kpi-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.kpi-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 8px;
    font-size: 13px;
    background: rgba(27, 36, 48, 0.06);
    color: var(--slate);
}

.kpi-badge.is-credit {
    background: rgba(31, 157, 85, 0.12);
    color: var(--success);
}

.kpi-badge.is-debit {
    background: rgba(201, 67, 44, 0.12);
    color: var(--failed);
}

.kpi-amount {
    font-family: 'JetBrains Mono', monospace;
    font-size: 22px;
    font-weight: 600;
}

.kpi-amount.is-credit {
    color: var(--success);
}

.kpi-amount.is-debit {
    color: var(--failed);
}

.kpi-note {
    font-size: 12px;
    color: var(--slate);
}

/* ───────── Transactions ───────── */
.transactions-card {
    background: white;
    border: 1px solid var(--hairline);
    border-radius: 14px;
    padding: 22px 26px;
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

.card-sub {
    font-size: 12px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.refresh-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: white;
    border: 1px solid var(--hairline);
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 500;
    color: var(--slate);
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        color 0.15s ease;
}

.refresh-btn:hover:not(:disabled) {
    border-color: var(--slate);
    color: var(--ink);
}

.refresh-btn:disabled {
    opacity: 0.6;
    cursor: default;
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
    color: var(--slate);
    padding: 10px 8px;
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

.ref-cell {
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mono {
    font-family: 'JetBrains Mono', monospace;
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

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 40px 0;
    color: var(--slate);
    font-size: 14px;
}

.empty-mark {
    font-size: 28px;
    color: var(--hairline);
}

.empty-state .action-btn {
    color: white;
}

.loading-state,
.load-error {
    color: var(--slate);
    font-size: 14px;
    padding: 20px 0;
}

.load-error {
    color: var(--failed);
}

/* ───────── Responsive ───────── */
@media (max-width: 900px) {
    .app {
        grid-template-columns: 1fr;
    }

    .sidebar {
        flex-direction: row;
        align-items: center;
        height: auto;
        position: static;
        padding: 12px 16px;
        gap: 6px;
        overflow-x: auto;
    }

    .brand {
        padding: 0 12px 0 4px;
    }

    .nav {
        flex-direction: row;
        flex: 1;
    }

    .nav-label {
        display: none;
    }

    .nav-item.is-active {
        box-shadow: inset 0 -2px 0 var(--signal);
    }

    .workspace {
        padding: 24px 20px 40px;
    }

    .grid {
        grid-template-columns: 1fr;
    }

    .balance-main {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    .actions {
        width: 100%;
    }

    .actions .action-btn {
        flex: 1;
        justify-content: center;
    }
}
</style>
