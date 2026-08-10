<script setup lang="ts">
import { computed } from 'vue';
import { PAGE_SIZE_OPTIONS } from '@/config/pagination';

const props = withDefaults(
    defineProps<{
        currentPage: number;
        lastPage: number;
        total?: number;
        perPage?: number;
        perPageOptions?: number[];
    }>(),
    {
        perPageOptions: () => PAGE_SIZE_OPTIONS,
    },
);

const emit = defineEmits<{
    (e: 'change', page: number): void;
    (e: 'perPageChange', perPage: number): void;
}>();

function onPerPageChange(event: Event) {
    const value = Number((event.target as HTMLSelectElement).value);
    emit('perPageChange', value);
}

const rangeLabel = computed(() => {
    if (props.total === undefined || props.perPage === undefined) {
        return null;
    }

    if (props.total === 0) {
        return '0 results';
    }

    const from = (props.currentPage - 1) * props.perPage + 1;
    const to = Math.min(props.currentPage * props.perPage, props.total);

    return `${from}–${to} of ${props.total}`;
});

function go(page: number) {
    if (page < 1 || page > props.lastPage || page === props.currentPage) {
        return;
    }

    emit('change', page);
}
</script>

<template>
    <div class="pagination">
        <div class="page-left">
            <label v-if="perPage !== undefined" class="per-page">
                <span class="muted">Rows</span>
                <select :value="perPage" @change="onPerPageChange">
                    <option
                        v-for="option in perPageOptions"
                        :key="option"
                        :value="option"
                    >
                        {{ option }}
                    </option>
                </select>
            </label>
            <span v-if="rangeLabel" class="page-range muted">{{
                rangeLabel
            }}</span>
        </div>

        <div class="page-controls">
            <button
                class="page-btn"
                :disabled="currentPage === 1"
                @click="go(currentPage - 1)"
            >
                ← Previous
            </button>
            <span class="page-info muted"
                >Page {{ currentPage }} / {{ lastPage }}</span
            >
            <button
                class="page-btn"
                :disabled="currentPage === lastPage"
                @click="go(currentPage + 1)"
            >
                Next →
            </button>
        </div>
    </div>
</template>

<style scoped>
.pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 18px;
    font-family: 'Inter', system-ui, sans-serif;
}

.page-left {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.per-page {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
}

.per-page select {
    padding: 6px 8px;
    border: 1px solid #e4e1d8;
    border-radius: 6px;
    font-size: 13px;
    background: white;
    color: #1b2430;
    cursor: pointer;
}

.per-page select:hover {
    border-color: #2454ff;
}

.page-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-left: auto;
}

.page-btn {
    padding: 7px 14px;
    border: 1px solid #e4e1d8;
    border-radius: 6px;
    background: white;
    font-size: 13px;
    font-weight: 500;
    color: #1b2430;
    cursor: pointer;
}

.page-btn:hover:not(:disabled) {
    border-color: #2454ff;
    color: #2454ff;
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: default;
}

.page-info,
.page-range {
    font-size: 13px;
}

.muted {
    color: #5b6570;
}
</style>
