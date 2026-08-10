<script setup lang="ts">
import { useToast } from '@/composables/useToast';

const { toasts, dismiss } = useToast();
</script>

<template>
    <Teleport to="body">
        <div class="toast-host" aria-live="polite" aria-atomic="true">
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="toast"
                    :class="`is-${toast.type}`"
                    role="status"
                    @click="dismiss(toast.id)"
                >
                    <span class="toast-icon">
                        {{
                            toast.type === 'success'
                                ? '✓'
                                : toast.type === 'error'
                                  ? '!'
                                  : '•'
                        }}
                    </span>
                    <span class="toast-message">{{ toast.message }}</span>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-host {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
    font-family: 'Inter', system-ui, sans-serif;
}

.toast {
    pointer-events: auto;
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 240px;
    max-width: 360px;
    padding: 12px 16px;
    border-radius: 10px;
    background: white;
    border: 1px solid #e4e1d8;
    box-shadow: 0 8px 24px rgba(27, 36, 48, 0.12);
    font-size: 14px;
    color: #1b2430;
    cursor: pointer;
}

.toast-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: white;
    flex-shrink: 0;
}

.toast.is-success {
    border-color: rgba(31, 157, 85, 0.4);
}

.toast.is-success .toast-icon {
    background: #1f9d55;
}

.toast.is-error {
    border-color: rgba(201, 67, 44, 0.4);
}

.toast.is-error .toast-icon {
    background: #c9432c;
}

.toast.is-info .toast-icon {
    background: #2454ff;
}

.toast-message {
    line-height: 1.4;
}

.toast-enter-active,
.toast-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(16px);
}
</style>
