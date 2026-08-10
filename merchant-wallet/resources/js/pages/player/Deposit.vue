<script setup lang="ts">
import PlayerShell from "@/components/PlayerShell.vue";
import { useTour } from "@/composables/useTour";
import { Head, router } from "@inertiajs/vue3";
import { computed, onMounted, reactive, ref } from "vue";

interface Bank {
  currency: string;
  bank_name: string;
  id: string;
}

interface CurrencyRate {
  currency: string;
  min: number;
  max: number;
}

const banks = ref<Bank[]>([]);
const currencies = ref<CurrencyRate[]>([]);
const loadError = ref<string | null>(null);
const loading = ref(true);

const limits = reactive({
  min: 0,
  max: 0,
});

const form = reactive({
  currency: "",
  amount: "",
  bank: "",
  payment_method: "online_banking",
});

const submitting = ref(false);
const submitError = ref<string | null>(null);

const filteredBanks = computed(() => {
  return banks.value.filter(
    bank => bank.currency === form.currency
  );
});

const amountError = computed(() => {
  if (form.amount === "") return null;

  const value = Number(form.amount);

  if (Number.isNaN(value)) {
    return "Enter a valid number.";
  }

  if (value <= 0) {
    return "Amount must be greater than zero.";
  }

  if (value < limits.min) {
    return `Minimum deposit is ${form.currency} ${limits.min.toFixed(2)}.`;
  }

  if (value > limits.max) {
    return `Maximum deposit is ${form.currency} ${limits.max.toFixed(2)}.`;
  }

  return null;
});

const isValid = computed(() => {
  return (
    form.amount !== "" &&
    amountError.value === null &&
    (
      form.payment_method === "duitnowqr" ||
      form.bank !== ""
    )
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

function onCurrencyChange() {
  const selectedCurrency = currencies.value.find(
    currency => currency.currency === form.currency
  );

  if (selectedCurrency) {
    limits.min = selectedCurrency.min;
    limits.max = selectedCurrency.max;
  }

  const availableBank = banks.value.find(
    bank => bank.currency === form.currency
  );

  form.bank = availableBank?.id ?? "";
}

function onPaymentMethodChange() {
  if (form.payment_method === "duitnowqr") {
    form.bank = "";
    return;
  }

  form.bank = filteredBanks.value[0]?.id ?? "";
}

async function submit() {
  if (!isValid.value) return;

  submitting.value = true;
  submitError.value = null;

  try {
    const response = await fetch("/api/payment/deposit", {
      method: "POST",
      headers: authHeaders(),
      body: JSON.stringify({
        amount: Number(form.amount),
        currency: form.currency,
        bank_id: form.bank || null,
        payment_method: form.payment_method,
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
    if (data.p_url) {
      window.location.href = data.p_url;
    } else {
      router.visit("/dashboard");
    }
  } catch (e) {
    submitError.value = "Something went wrong. Please try again.";
  } finally {
    submitting.value = false;
  }
}

function updateLimits() {
  const selected = currencies.value.find(
    currency => currency.currency === form.currency
  );

  if (!selected) {
    limits.min = 0;
    limits.max = 0;
    return;
  }

  limits.min = selected.min;
  limits.max = selected.max;
}

async function loadData() {
  loading.value = true;
  loadError.value = null;

  try {
    const response = await fetch("/api/payment/general-info?deposit=1", {
      headers: authHeaders(),
    });

    if (response.status === 401) {
      router.visit("/login");
      return;
    }

    if (!response.ok) {
      // Don't touch currencies/banks — leave them as empty arrays so
      // every downstream .filter()/.length call still has something
      // safe to operate on instead of throwing.
      const body = await response.json().catch(() => null);
      loadError.value =
        body?.message ?? "Could not load deposit options. Please try again.";
      return;
    }

    const data = await response.json();

    currencies.value = data.currencies ?? [];
    banks.value = data.banks ?? [];

    if (currencies.value.length > 0) {
      form.currency = currencies.value[0].currency;
      updateLimits();
    }

    if (form.payment_method === "online_banking") {
      form.bank = filteredBanks.value[0]?.id ?? "";
    }
  } catch (error) {
    loadError.value = "Something went wrong loading deposit options.";
  } finally {
    loading.value = false;
  }
}

useTour("player-deposit", [
    {
        element: '[data-tour="deposit-amount"]',
        popover: {
            title: "Enter an amount",
            description: "Choose how much you want to add to your wallet.",
        },
    },
    {
        element: '[data-tour="deposit-submit"]',
        popover: {
            title: "Confirm deposit",
            description: "Submit to be taken to the payment gateway and complete your top-up.",
        },
    },
]);

onMounted(() => {
  loadData();
});
</script>

<template>

  <Head title="Deposit" />

  <PlayerShell active="deposit" eyebrow="Wallet" title="Deposit">
    <div class="layout">
      <section class="panel">
        <div class="panel-head">
          <h2>Add funds</h2>
          <p class="panel-sub muted">Choose a method and amount to top up your wallet.</p>
        </div>

        <p v-if="loadError" class="error load-error">{{ loadError }}</p>

        <form v-else @submit.prevent="submit" novalidate>
          <div class="field">
            <label for="payment_method">Payment Method</label>
            <select id="payment_method" v-model="form.payment_method" :disabled="loading"
              @change="onPaymentMethodChange">
              <option value="online_banking">Online Banking</option>
              <option value="duitnowqr">DuitNow QR</option>
            </select>
          </div>

          <template v-if="form.payment_method === 'online_banking'">
            <div class="field">
              <label for="currency">Currency</label>
              <select id="currency" v-model="form.currency" :disabled="loading" @change="onCurrencyChange">
                <option v-for="currency in currencies" :key="currency.currency" :value="currency.currency">
                  {{ currency.currency }}
                </option>
              </select>
            </div>

            <div class="field">
              <label for="bank">Bank</label>
              <select id="bank" v-model="form.bank" :disabled="loading">
                <option v-for="bank in filteredBanks" :key="bank.id" :value="bank.id">
                  {{ bank.bank_name }}
                </option>
              </select>
            </div>

            <div class="field">
              <label for="amount">Amount</label>
              <div class="amount-input" data-tour="deposit-amount">
                <span class="currency-prefix">{{ form.currency }}</span>
                <input id="amount" v-model="form.amount" type="number" inputmode="decimal" step="0.01"
                  placeholder="0.00" autocomplete="off" :disabled="loading" />
              </div>
              <p class="hint">
                Min {{ limits.min.toFixed(2) }} · Max {{ limits.max.toFixed(2) }}
              </p>
              <p v-if="amountError" class="error">{{ amountError }}</p>
            </div>
          </template>

          <p v-if="submitError" class="error submit-error">{{ submitError }}</p>

          <button type="submit" class="submit-btn" data-tour="deposit-submit"
            :disabled="!isValid || submitting || loading">
            {{ submitting ? "Starting deposit…" : "Continue to payment" }}
          </button>
        </form>
      </section>

      <aside class="side-card">
        <span class="eyebrow muted">Summary</span>
        <div class="side-row">
          <span>Method</span>
          <strong>{{ form.payment_method === 'duitnowqr' ? 'DuitNow QR' : 'Online Banking' }}</strong>
        </div>
        <div class="side-row">
          <span>Currency</span>
          <strong>{{ form.currency || '—' }}</strong>
        </div>
        <div class="side-row">
          <span>Amount</span>
          <strong class="mono">{{ form.amount ? Number(form.amount).toFixed(2) : '0.00' }}</strong>
        </div>
        <div class="side-note">
          You'll be redirected to the gateway to complete payment securely.
        </div>
      </aside>
    </div>
  </PlayerShell>
</template>

<style scoped>
.layout {
  --ink: #1b2430;
  --slate: #5b6570;
  --paper: #fafaf8;
  --hairline: #e4e1d8;
  --signal: #2454ff;
  --failed: #c9432c;

  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 20px;
  align-items: start;
  color: var(--ink);
  font-family: "Inter", system-ui, sans-serif;
}

.panel {
  background: white;
  border: 1px solid var(--hairline);
  border-radius: 14px;
  padding: 26px 28px;
}

.panel-head {
  margin-bottom: 22px;
}

.panel-head h2 {
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 4px;
}

.panel-sub {
  font-size: 13px;
  margin: 0;
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

.mono {
  font-family: "JetBrains Mono", monospace;
}

/* Side summary card */
.side-card {
  background: white;
  border: 1px solid var(--hairline);
  border-radius: 14px;
  padding: 22px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.side-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  color: var(--slate);
}

.side-row strong {
  color: var(--ink);
  font-weight: 600;
}

.side-note {
  margin-top: 6px;
  padding-top: 14px;
  border-top: 1px solid var(--hairline);
  font-size: 12px;
  line-height: 1.5;
  color: var(--slate);
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

select:disabled,
input:disabled {
  background: var(--hairline);
  color: var(--slate);
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

.load-error {
  font-size: 14px;
  margin: 0;
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

@media (max-width: 820px) {
  .layout {
    grid-template-columns: 1fr;
  }
}
</style>