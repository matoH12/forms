<script setup>
import { Handle, Position } from '@vue-flow/core';

defineProps({
    data: Object,
});

const formatDelay = (seconds) => {
    if (!seconds) return '';
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    const hours = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    return `${hours}h ${mins}m`;
};
</script>

<template>
    <div class="bg-white dark:bg-gray-800 border-2 border-orange-400 rounded-lg shadow-sm min-w-[150px]">
        <Handle type="target" :position="Position.Top" class="!bg-orange-500" />

        <div class="px-3 py-2">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-lg">⏱️</span>
                <span class="font-medium text-sm text-gray-900 dark:text-white">{{ data?.label || 'Cakanie' }}</span>
            </div>
            <div v-if="data?.delay_seconds" class="text-xs text-gray-500 dark:text-gray-400">
                {{ formatDelay(data.delay_seconds) }}
            </div>
        </div>

        <Handle type="source" :position="Position.Bottom" class="!bg-orange-500" />
    </div>
</template>
