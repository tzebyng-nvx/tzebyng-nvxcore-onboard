<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { onMounted } from "vue";

interface Stats {
    tenants: number;
    total_deposit: number;
    total_withdrawal: number;
    recent_transactions: number;
}

const props = withDefaults(
    defineProps<{
        stats?: Stats;
    }>(),
    {
        stats: () => ({
            tenants: 0,
            total_deposit: 0,
            total_withdrawal: 0,
            recent_transactions: 0,
        }),
    }
);

function isTokenExpired(): boolean {
    const expiresAt = localStorage.getItem("expires_at");
    if (!expiresAt) return true;
    return Date.now() >= Number(expiresAt);
}

async function logout() {
    try {
        await fetch("api/platform/logout", {
            method: "POST",
            headers: {
                Accept: "application/json",
                Authorization: `${localStorage.getItem("token_type") ?? "Bearer"} ${localStorage.getItem("access_token") ?? ""}`,
            },
        });
    } catch (e) { }

    localStorage.removeItem("access_token");
    localStorage.removeItem("token_type");
    localStorage.removeItem("expires_at");

    router.visit("/login");
}

onMounted(() => {
    if (isTokenExpired()) {
        router.visit("/login");
    }
});
</script>


<template>
    <main class="content">

        <header class="page-header">
            <div>
                <span class="eyebrow">
                    Central Back Office
                </span>

                <h1>
                    Platform Dashboard
                </h1>

                <p>
                    Monitor tenants, payments and platform activity.
                </p>
            </div>

            <button class="logout-btn" @click="logout">
                Logout
            </button>
        </header>


        <section class="stats-grid">

            <div class="stat-card">
                <span>
                    Total Tenants
                </span>

                <strong>
                    {{ props.stats.tenants }}
                </strong>
            </div>


            <div class="stat-card">
                <span>
                    Total Deposits
                </span>

                <strong>
                    MYR {{ props.stats.total_deposit.toFixed(2) }}
                </strong>
            </div>


            <div class="stat-card">
                <span>
                    Total Withdrawals
                </span>

                <strong>
                    MYR {{ props.stats.total_withdrawal.toFixed(2) }}
                </strong>
            </div>


            <div class="stat-card">
                <span>
                    Recent Activity
                </span>

                <strong>
                    {{ props.stats.recent_transactions }}
                </strong>
            </div>

        </section>


        <section class="quick-actions">

            <h2>
                Management
            </h2>


            <div class="actions">

                <button class="action-btn primary" @click="router.visit('/tenants')">
                    Manage Tenants
                </button>


                <button class="action-btn" @click="router.visit('/transactions')">
                    View Transactions
                </button>

            </div>

        </section>

    </main>
</template>


<style scoped>
.content {
    padding: 40px;
    background: #fafaf8;
    min-height: 100vh;
    color: #1b2430;
}


.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 40px;
}


.eyebrow {
    font-family: monospace;
    font-size: 11px;
    letter-spacing: .15em;
    text-transform: uppercase;
    color: #667085;
}


h1 {
    margin: 8px 0;
    font-size: 28px;
}


p {
    color: #667085;
}


.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}


.stat-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 24px;
}


.stat-card span {
    display: block;
    color: #667085;
    font-size: 13px;
}


.stat-card strong {
    display: block;
    margin-top: 12px;
    font-size: 26px;
}


.quick-actions {
    margin-top: 40px;
}


.actions {
    display: flex;
    gap: 12px;
}


.action-btn {
    padding: 12px 18px;
    border-radius: 6px;
    border: 1px solid #ddd;
    background: white;
    cursor: pointer;
}


.action-btn.primary {
    background: #2454ff;
    color: white;
    border-color: #2454ff;
}


.logout-btn {
    border: none;
    background: none;
    cursor: pointer;
}
</style>