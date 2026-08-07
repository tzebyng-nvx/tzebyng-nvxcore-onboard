<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { onMounted, ref } from "vue";

interface Transaction {
  id: string;
  type: string;
  amount: string;
  currency: string;
  status: string;
  created_at: string;
}

interface WalletResponse {
  balance: string;
  currency: string;
}

const balance = ref<string | null>(null);
const currency = ref("MYR");
const transactions = ref<Transaction[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);

function authHeaders(): HeadersInit {
  const token = localStorage.getItem("access_token");
  const tokenType = localStorage.getItem("token_type") ?? "Bearer";
  return {
    Accept: "application/json",
    Authorization: `${tokenType} ${token}`,
    "X-Tenant": window.location.hostname.split(".")[0],
  };
}

function isTokenExpired(): boolean {
  const expiresAt = localStorage.getItem("expires_at");
  if (!expiresAt) return true;
  return Date.now() >= Number(expiresAt);
}

async function logout() {
  try {
    const response = await fetch("api/logout", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `${localStorage.getItem("token_type") ?? "Bearer"} ${localStorage.getItem("access_token") ?? ""
          }`,
        "X-Tenant": window.location.hostname.split(".")[0],
      },
    });

    if (response.ok) {
      localStorage.removeItem("access_token");
      localStorage.removeItem("token_type");
      localStorage.removeItem("expires_at");

      router.visit("/login");
    }
  } catch (e) { }
}

async function loadDashboard() {
  if (isTokenExpired()) {
    logout();
    return;
  }

  loading.value = true;
  loadError.value = null;

  try {
    const [
      walletRes,
      txnRes] = await Promise.all([
        fetch("/api/wallet", { headers: authHeaders() }),
        fetch("/api/transactions?per_page=5", { headers: authHeaders() }),
      ]);

    if (
      walletRes.status === 401 ||
      txnRes.status === 401) {
      logout();
      return;
    }

    if (
      !walletRes.ok ||
      !txnRes.ok) {
      loadError.value = "Could not load your wallet right now.";
      return;
    }

    const wallet: WalletResponse = await walletRes.json();
    const txnPage = await txnRes.json();

    balance.value = wallet.balance;
    currency.value = wallet.currency;
    transactions.value = txnPage.data ?? txnPage;
  } catch (e: any) {
    loadError.value = e.message ?? "Something went wrong loading your dashboard.";
  } finally {
    loading.value = false;
  }
}

function formatAmount(txn: Transaction): string {
  const sign = txn.type === "deposit" ? "+" : "-";
  return `${sign}${Number(txn.amount).toFixed(2)}`;
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  });
}

onMounted(loadDashboard);
</script>

<template>

  <Head title="Dashboard" />

  <div class="page">
    <header class="topbar">
      <div class="brand">
        <span class="brand-mark">◆</span>
        <span class="brand-name">Ledger</span>
      </div>
      <button class="logout-btn" @click="logout">Log out</button>
    </header>

    <main class="content">
      <section class="balance-card">
        <span class="eyebrow">Available balance</span>
        <div v-if="loading" class="balance-amount is-loading">—</div>
        <div v-else-if="balance !== null" class="balance-amount">
          {{ currency }} {{ Number(balance).toFixed(2) }}
        </div>
        <div v-else class="balance-amount is-error">Unavailable</div>

        <div class="actions">
          <button class="action-btn is-primary" @click="router.visit('/deposit')">
            Deposit
          </button>
          <button class="action-btn" @click="router.visit('/withdraw')">Withdraw</button>
          <button class="action-btn" @click="router.visit('/transaction')">
            Transactions
          </button>
        </div>

      </section>

      <section class="transactions-card">
        <div class="card-header">
          <h2>Recent transactions</h2>
          <button class="refresh-btn" :disabled="loading" @click="loadDashboard">
            {{ loading ? "Refreshing…" : "Refresh" }}
          </button>
        </div>

        <p v-if="loadError" class="load-error">{{ loadError }}</p>

        <table v-else-if="!loading && transactions.length" class="txn-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="txn in transactions" :key="txn.id">
              <td class="mono">{{ txn.id }}</td>
              <td class="capitalize">{{ txn.type }}</td>
              <td class="mono" :class="txn.type === 'deposit' ? 'is-credit' : 'is-debit'">
                {{ formatAmount(txn) }}
              </td>
              <td>
                <span class="status-pill" :class="`is-${txn.status}`">{{
                  txn.status
                  }}</span>
              </td>
              <td class="mono muted">{{ formatDate(txn.created_at) }}</td>
            </tr>
          </tbody>
        </table>

        <p v-else-if="!loading" class="empty-state">
          No transactions yet. Make your first deposit to get started.
        </p>

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

.logout-btn {
  background: transparent;
  border: 1px solid var(--hairline);
  border-radius: 6px;
  padding: 8px 14px;
  font-size: 13px;
  color: var(--slate);
  cursor: pointer;
}

.logout-btn:hover {
  border-color: var(--slate);
  color: var(--ink);
}

.content {
  max-width: 880px;
  margin: 0 auto;
  padding: 40px;
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.balance-card {
  background: var(--ink);
  color: var(--paper);
  border-radius: 12px;
  padding: 32px;
}

.eyebrow {
  font-family: "JetBrains Mono", monospace;
  font-size: 11px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(250, 250, 248, 0.55);
}

.balance-amount {
  font-family: "JetBrains Mono", monospace;
  font-size: 40px;
  font-weight: 600;
  margin: 10px 0 24px;
}

.balance-amount.is-loading {
  color: rgba(250, 250, 248, 0.4);
}

.balance-amount.is-error {
  color: var(--failed);
  font-size: 20px;
}

.actions {
  display: flex;
  gap: 12px;
}

.action-btn {
  padding: 10px 20px;
  border-radius: 6px;
  border: 1px solid rgba(250, 250, 248, 0.25);
  background: transparent;
  color: var(--paper);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.action-btn.is-primary {
  background: var(--signal);
  border-color: var(--signal);
}

.action-btn:hover {
  opacity: 0.9;
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

.refresh-btn {
  background: transparent;
  border: 1px solid var(--hairline);
  border-radius: 6px;
  padding: 6px 12px;
  font-size: 13px;
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
</style>
