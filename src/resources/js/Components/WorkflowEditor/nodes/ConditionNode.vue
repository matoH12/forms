<script setup>
import { Handle, Position } from '@vue-flow/core';

defineProps({
    data: Object,
});
</script>

<template>
    <div class="bg-white dark:bg-gray-800 border-2 border-purple-400 rounded-lg shadow-sm min-w-[150px]">
        <Handle type="target" :position="Position.Top" class="!bg-purple-500" />

        <div class="px-3 py-2">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-lg">❓</span>
                <span class="font-medium text-sm text-gray-900 dark:text-white">{{ data?.label || 'Podmienka' }}</span>
            </div>
            <div v-if="data?.field" class="text-xs text-gray-500 dark:text-gray-400">
                {{ data.field }} {{ data.operator }} {{ data.value }}
            </div>
        </div>

        <div class="flex justify-between px-2 pb-2 text-xs">
            <div class="flex flex-col items-center">
                <Handle
                    id="true"
                    type="source"
                    :position="Position.Bottom"
                    class="!bg-green-500 !relative !transform-none !left-auto !top-auto"
                    style="position: relative !important;"
                />
                <span class="text-green-600 dark:text-green-400 mt-1">Áno</span>
            </div>
            <div class="flex flex-col items-center">
                <Handle
                    id="false"
                    type="source"
                    :position="Position.Bottom"
                    class="!bg-red-500 !relative !transform-none !left-auto !top-auto"
                    style="position: relative !important;"
                />
                <span class="text-red-600 dark:text-red-400 mt-1">Nie</span>
            </div>
        </div>
    </div>
</template>
