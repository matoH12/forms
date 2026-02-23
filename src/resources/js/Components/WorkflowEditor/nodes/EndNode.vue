<script setup>
import { Handle, Position } from '@vue-flow/core';

defineProps({
    data: Object,
});
</script>

<template>
    <div class="px-4 py-2 bg-red-100 dark:bg-red-900/30 border-2 border-red-500 rounded-full text-center min-w-[100px]">
        <Handle type="target" :position="Position.Top" class="!bg-red-500" />
        <span class="font-medium text-red-800 dark:text-red-300">{{ data?.label || 'Koniec' }}</span>
    </div>
</template>
