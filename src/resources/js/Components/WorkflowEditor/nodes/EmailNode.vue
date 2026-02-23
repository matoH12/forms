<script setup>
import { Handle, Position } from '@vue-flow/core';

defineProps({
    data: Object,
});
</script>

<template>
    <div class="bg-white dark:bg-gray-800 border-2 border-orange-400 rounded-lg shadow-sm min-w-[150px]">
        <Handle type="target" :position="Position.Top" class="!bg-orange-500" />

        <div class="px-3 py-2">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-lg">📧</span>
                <span class="font-medium text-sm text-gray-900 dark:text-white">{{ data?.label || 'Email' }}</span>
            </div>
            <div v-if="data?.to" class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[200px]">
                {{ data.to }}
            </div>
        </div>

        <Handle type="source" :position="Position.Bottom" class="!bg-orange-500" />
    </div>
</template>
