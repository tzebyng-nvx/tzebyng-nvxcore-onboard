<script setup lang="ts">
import AdminShell from "@/components/AdminShell.vue";
import { useTour } from "@/composables/useTour";
import { adminAuthHeaders } from "@/utils/authHeaders";
import { Head, router } from "@inertiajs/vue3";
import { onMounted, ref } from "vue";

interface GeneralInfo {
    total_payments: number;
    total_pending: number;
    total_successful: number;
    currency: string;
}

const info = ref<GeneralInfo | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);

const floatBalance = ref<string | null>(null);
const floatLoading = ref(true);
const floatError = ref<string | null>(null);

async function loadDashboard() {
    loading.value = true;
    loadError.value = null;

    try {
        const infoRes = await fetch("/api/admin/payments/general-info", {
            headers: adminAuthHeaders(),
        });

        if (infoRes.status === 401) {
            router.visit("/admin/login");
            return;
        }

        if (infoRes.status === 403) {
            loadError.value = "You don't have access to this area.";
            return;
        }

        if (!infoRes.ok) {
            loadError.value = "Could not load admin data right now.";
            return;
        }

        info.value = await infoRes.json();
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
            headers: adminAuthHeaders(),
        });

        if (res.status === 401) {
            router.visit("/admin/login");
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

useTour("admin-dashboard", [
    {
        element: ".sidebar .nav",
        popover: {
            title: "Navigation",
            description: "Jump between Overview, Transactions, Users and Gateway settings from here.",
        },
    },
    {
        element: '[data-tour="float"]',
        popover: {
            title: "Merchant float balance",
            description: "The live balance held with your payment gateway.",
        },
    },
    {
        element: '[data-tour="stats"]',
        popover: {
            title: "At a glance",
            description: "Totals for payments, pending and successful transactions, plus your default currency.",
        },
    },
    {
        element: '[data-tour="txn-link"]',
        popover: {
            title: "Transactions",
            description: "Open the full transactions page to browse and filter every deposit and withdrawal.",
        },
    },
]);

onMounted(() => {
    loadDashboard();
    loadFloatBalance();
});
</script>

<template>

    <Head title="Admin Dashboard" />

    <AdminShell active="overview" eyebrow="Back office" title="Dashboard">
        <template #actions>
            <button class="refresh-btn" :disabled="floatLoading" @click="loadFloatBalance">
                {{ floatLoading ? "Refreshing…" : "↻ Refresh" }}
            </button>
        </template>

        <section class="float-card" data-tour="float">
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
        </section>

        <section class="stats-grid" data-tour="stats">
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

        <p v-if="loadError" class="load-error">{{ loadError }}</p>

        <section class="link-card" data-tour="txn-link" @click="router.visit('/admin/transactions')">
            <div>
                <h2>Transactions</h2>
                <p class="link-note">Browse, filter, and review every deposit and withdrawal.</p>
            </div>
            <span class="link-cta">View all →</span>
        </section>
    </AdminShell>
</template>

<style scoped>
.float-card {
    --ink: #1b2430;
    --slate: #5b6570;
    --paper: #fafaf8;
    --hairline: #e4e1d8;
    --signal: #2454ff;
    --success: #1f9d55;
    --pending: #c98a1c;
    --failed: #c9432c;

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

.float-amount.is-error {
    color: #ff9c88;
    font-size: 22px;
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

.stat-value.is-pending {
    color: #c98a1c;
}

.stat-value.is-success {
    color: #1f9d55;
}

.link-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    border: 1px solid #e4e1d8;
    border-radius: 12px;
    padding: 22px 28px;
    background: white;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.link-card:hover {
    border-color: #2454ff;
    box-shadow: 0 0 0 3px rgba(36, 84, 255, 0.10);
}

.link-card h2 {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 4px;
}

.link-note {
    font-size: 13px;
    color: #5b6570;
    margin: 0;
}

.link-cta {
    font-size: 14px;
    font-weight: 600;
    color: #2454ff;
    white-space: nowrap;
}

.load-error {
    color: #c9432c;
    font-size: 14px;
    padding: 20px 0;
}

@media (max-width: 720px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
