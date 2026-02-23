<script setup>
import { Handle, Position } from '@vue-flow/core';

defineProps({
    data: Object,
});
</script>

<template>
    <div class="px-4 py-2 bg-green-100 dark:bg-green-900/30 border-2 border-green-500 rounded-full text-center min-w-[100px]">
        <span class="font-medium text-green-800 dark:text-green-300">{{ data?.label || 'Štart' }}</span>
        <Handle type="source" :position="Position.Bottom" class="!bg-green-500" />
    </div>
</template>
