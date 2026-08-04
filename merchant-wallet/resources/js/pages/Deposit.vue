<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";

// Gateway's documented min/max per currency — mirror whatever the
// gateway's docs actually state. Keep this in one place so it's easy
// to update if the sandbox limits change.
const CURRENCY_LIMITS: Record<string, { min: number; max: number }> = {
  MYR: { min: 10, max: 30000 },
  USD: { min: 5, max: 5000 },
  SGD: { min: 5, max: 8000 },
};

const BANKS_BY_CURRENCY: Record<string, { code: string; label: string }[]> = {
  MYR: [
    { code: "maybank2u", label: "Maybank2u" },
    { code: "cimb_clicks", label: "CIMB Clicks" },
    { code: "public_bank", label: "Public Bank" },
  ],
  USD: [{ code: "generic_us", label: "US Bank Transfer" }],
  SGD: [
    { code: "dbs", label: "DBS/POSB" },
    { code: "ocbc", label: "OCBC" },
  ],
};

const currencies = Object.keys(CURRENCY_LIMITS);

const form = reactive({
  amount: "",
  currency: "MYR",
  bank: BANKS_BY_CURRENCY["MYR"][0].code,
});

const submitting = ref(false);
const submitError = ref<string | null>(null);

const limits = computed(() => CURRENCY_LIMITS[form.currency]);
const banks = computed(() => BANKS_BY_CURRENCY[form.currency] ?? []);

// Reset bank selection whenever currency changes, so it never points
// at a bank that doesn't belong to the newly selected currency.
function onCurrencyChange() {
  form.bank = banks.value[0]?.code ?? "";
}

const amountError = computed(() => {
  if (form.amount === "") return null;

  const value = Number(form.amount);
  if (Number.isNaN(value)) return "Enter a valid number.";
  if (value <= 0) return "Amount must be greater than zero.";
  if (value < limits.value.min) {
    return `Minimum deposit is ${form.currency} ${limits.value.min.toFixed(2)}.`;
  }
  if (value > limits.value.max) {
    return `Maximum deposit is ${form.currency} ${limits.value.max.toFixed(2)}.`;
  }
  return null;
});

const isValid = computed(() => {
  return (
    form.amount !== "" &&
    amountError.value === null &&
    form.bank !== "" &&
    banks.value.length > 0
  );
});

function authHeaders(): HeadersInit {
  const token = localStorage.getItem("access_token");
  const tokenType = localStorage.getItem("token_type") ?? "Bearer";
  return {
    "Content-Type": "application/json",
    Accept: "application/json",
    Authorization: `${tokenType} ${token}`,
    "X-Tenant": window.location.hostname.split(".")[0],
  };
}

async function submit() {
  if (!isValid.value) return;

  submitting.value = true;
  submitError.value = null;

  try {
    const response = await fetch("/api/deposit", {
      method: "POST",
      headers: authHeaders(),
      body: JSON.stringify({
        amount: Number(form.amount),
        currency: form.currency,
        bank: form.bank,
      }),
    });

    if (response.status === 401) {
      router.visit("/login");
      return;
    }

    if (!response.ok) {
      const body = await response.json().catch(() => null);
      submitError.value =
        body?.message ?? "Could not start this deposit. Please try again.";
      return;
    }

    // Backend creates the pending transaction, calls the gateway,
    // and returns the payment URL to redirect the user to.
    const data = await response.json();
    if (data.payment_url) {
      window.location.href = data.payment_url;
    } else {
      router.visit("/dashboard");
    }
  } catch (e) {
    submitError.value = "Something went wrong. Please try again.";
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <Head title="Deposit" />

  <div class="page">
    <header class="topbar">
      <button class="back-btn" @click="router.visit('/dashboard')">← Dashboard</button>
    </header>

    <main class="content">
      <div class="form-card">
        <span class="eyebrow">New deposit</span>
        <h1>How much are you adding?</h1>

        <form @submit.prevent="submit" novalidate>
          <div class="field">
            <label for="currency">Currency</label>
            <select id="currency" v-model="form.currency" @change="onCurrencyChange">
              <option v-for="code in currencies" :key="code" :value="code">
                {{ code }}
              </option>
            </select>
          </div>

          <div class="field">
            <label for="amount">Amount</label>
            <div class="amount-input">
              <span class="currency-prefix">{{ form.currency }}</span>
              <input
                id="amount"
                v-model="form.amount"
                type="number"
                inputmode="decimal"
                step="0.01"
                placeholder="0.00"
                autocomplete="off"
              />
            </div>
            <p class="hint">
              Min {{ limits.min.toFixed(2) }} · Max {{ limits.max.toFixed(2) }}
            </p>
            <p v-if="amountError" class="error">{{ amountError }}</p>
          </div>

          <div class="field">
            <label for="bank">Bank</label>
            <select id="bank" v-model="form.bank">
              <option v-for="bank in banks" :key="bank.code" :value="bank.code">
                {{ bank.label }}
              </option>
            </select>
          </div>

          <p v-if="submitError" class="error submit-error">{{ submitError }}</p>

          <button type="submit" class="submit-btn" :disabled="!isValid || submitting">
            {{ submitting ? "Starting deposit…" : "Continue to payment" }}
          </button>
        </form>
      </div>
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
  --failed: #c9432c;

  min-height: 100vh;
  background: var(--paper);
  color: var(--ink);
  font-family: "Inter", system-ui, sans-serif;
}

.topbar {
  padding: 20px 40px;
  border-bottom: 1px solid var(--hairline);
}

.back-btn {
  background: transparent;
  border: none;
  color: var(--slate);
  font-size: 13px;
  cursor: pointer;
  padding: 0;
}

.back-btn:hover {
  color: var(--ink);
}

.content {
  display: flex;
  justify-content: center;
  padding: 60px 24px;
}

.form-card {
  width: 100%;
  max-width: 420px;
}

.eyebrow {
  font-family: "JetBrains Mono", monospace;
  font-size: 11px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--slate);
}

h1 {
  font-size: 26px;
  font-weight: 600;
  letter-spacing: -0.01em;
  margin: 8px 0 32px;
}

.field {
  margin-bottom: 20px;
}

label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 6px;
}

select,
input[type="number"] {
  width: 100%;
  padding: 11px 12px;
  border: 1px solid var(--hairline);
  border-radius: 6px;
  font-size: 14px;
  font-family: inherit;
  background: white;
  color: var(--ink);
}

select:focus,
input:focus {
  outline: 2px solid var(--signal);
  outline-offset: 1px;
  border-color: var(--signal);
}

.amount-input {
  display: flex;
  align-items: center;
  border: 1px solid var(--hairline);
  border-radius: 6px;
  overflow: hidden;
}

.amount-input:focus-within {
  outline: 2px solid var(--signal);
  outline-offset: 1px;
  border-color: var(--signal);
}

.currency-prefix {
  padding: 0 12px;
  font-family: "JetBrains Mono", monospace;
  font-size: 13px;
  color: var(--slate);
  border-right: 1px solid var(--hairline);
  align-self: stretch;
  display: flex;
  align-items: center;
}

.amount-input input {
  border: none;
  border-radius: 0;
}

.amount-input input:focus {
  outline: none;
}

.hint {
  font-size: 12px;
  color: var(--slate);
  margin: 6px 0 0;
}

.error {
  color: var(--failed);
  font-size: 12px;
  margin: 6px 0 0;
}

.submit-error {
  margin: 0 0 16px;
}

.submit-btn {
  width: 100%;
  padding: 12px;
  background: var(--signal);
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.submit-btn:hover:not(:disabled) {
  background: #1c40cc;
}

.submit-btn:disabled {
  opacity: 0.5;
  cursor: default;
}
</style>
