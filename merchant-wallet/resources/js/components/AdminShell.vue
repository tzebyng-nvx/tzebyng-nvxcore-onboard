<script setup lang="ts">
import ToastHost from "@/components/ToastHost.vue";
import { activeTour } from "@/composables/tourController";
import { adminAuthHeaders } from "@/utils/authHeaders";
import { router } from "@inertiajs/vue3";
import { onMounted } from "vue";

defineProps<{
  active: "overview" | "transactions" | "users" | "gateway";
  eyebrow?: string;
  title: string;
}>();

function isTokenExpired(): boolean {
  const expiresAt = localStorage.getItem("admin_expires_at");
  if (!expiresAt) return true;
  return Date.now() >= Number(expiresAt);
}

async function logout() {
  try {
    await fetch("/api/admin/logout", {
      method: "POST",
      headers: adminAuthHeaders(),
    });
  } catch (e) { }

  localStorage.removeItem("admin_access_token");
  localStorage.removeItem("admin_token_type");
  localStorage.removeItem("admin_expires_at");

  router.visit("/admin/login");
}

onMounted(() => {
  if (isTokenExpired()) {
    router.visit("/admin/login");
  }
});
</script>

<template>
  <div class="app">
    <ToastHost />

    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="brand">
        <span class="brand-mark">◆</span>
        <span class="brand-name">Ledger · Admin</span>
      </div>

      <nav class="nav">
        <span class="nav-label">Menu</span>
        <button class="nav-item" :class="{ 'is-active': active === 'overview' }" @click="router.visit('/admin/dashboard')">
          <span class="nav-icon">▣</span> Overview
        </button>
        <button class="nav-item" :class="{ 'is-active': active === 'transactions' }"
          @click="router.visit('/admin/transactions')">
          <span class="nav-icon">≣</span> Transactions
        </button>
        <button class="nav-item" :class="{ 'is-active': active === 'users' }" @click="router.visit('/admin/users')">
          <span class="nav-icon">◦</span> Users
        </button>
        <button class="nav-item" :class="{ 'is-active': active === 'gateway' }"
          @click="router.visit('/admin/gateway-settings')">
          <span class="nav-icon">⚙</span> Gateway
        </button>
      </nav>

      <button class="logout-btn" @click="logout">
        <span class="nav-icon">⏻</span> Log out
      </button>
    </aside>

    <!-- Workspace -->
    <main class="workspace">
      <header class="workspace-header">
        <div>
          <span class="eyebrow muted">{{ eyebrow ?? 'Admin' }}</span>
          <h1>{{ title }}</h1>
        </div>
        <div class="header-actions">
          <button v-if="activeTour" class="tour-btn" title="Take a guided tour of this page"
            @click="activeTour()">
            <span class="nav-icon">?</span> Tour
          </button>
          <slot name="actions" />
        </div>
      </header>

      <slot />
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
  font-family: "Inter", system-ui, sans-serif;
}

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
  font-family: "JetBrains Mono", monospace;
  font-size: 13px;
  letter-spacing: 0.06em;
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
  font-family: "JetBrains Mono", monospace;
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
  transition: background 0.15s ease, color 0.15s ease;
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

.workspace {
  padding: 32px 40px 48px;
  max-width: 1100px;
  width: 100%;
}

.workspace-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
}

.workspace-header h1 {
  font-size: 24px;
  font-weight: 600;
  letter-spacing: -0.01em;
  margin: 4px 0 0;
}

.eyebrow {
  font-family: "JetBrains Mono", monospace;
  font-size: 11px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.muted {
  color: var(--slate);
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.tour-btn {
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
}

.tour-btn:hover {
  border-color: var(--signal);
  color: var(--signal);
}

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
}
</style>
