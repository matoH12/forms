<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
    message: String,
    auth: Object,
});
</script>

<template>
    <Head title="Odkaz expiroval" />
    <PublicLayout :auth="auth">
        <div class="max-w-md mx-auto">
            <div class="card text-center py-8">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                    <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                    Platnost odkazu vyprsala
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    {{ message || 'Tento odkaz na schvalenie uz nie je platny.' }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-500">
                    Kontaktujte administratora pre nove vygenerovanie odkazu.
                </p>
            </div>
        </div>
    </PublicLayout>
</template>
