<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    keycloakLogoutUrl: String,
    homeUrl: String,
});

const step = ref(1); // 1 = odhlasovanie, 2 = hotovo

onMounted(() => {
    // Otvor Keycloak logout v novom okne/tabe
    const logoutWindow = window.open(props.keycloakLogoutUrl, '_blank', 'width=500,height=600');

    // Po 2 sekundách predpokladáme že logout prebehol
    setTimeout(() => {
        step.value = 2;
        if (logoutWindow) {
            logoutWindow.close();
        }
    }, 2000);
});
</script>

<template>
    <Head title="Odhlásenie" />
    <div class="min-h-screen bg-gray-100 flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-lg text-center max-w-md">
            <!-- Step 1: Odhlasovanie -->
            <template v-if="step === 1">
                <svg class="w-12 h-12 mx-auto text-blue-500 mb-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <h1 class="text-xl font-bold text-gray-900 mb-2">Odhlasovanie z SSO...</h1>
                <p class="text-gray-600 mb-4">
                    A new window has opened for SSO logout.
                </p>
            </template>

            <!-- Step 2: Hotovo -->
            <template v-else>
                <svg class="w-16 h-16 mx-auto text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h1 class="text-xl font-bold text-gray-900 mb-2">Boli ste odhlásený</h1>
                <p class="text-gray-600 mb-6">
                    You have been successfully logged out from the application and SSO.
                </p>
                <a
                    :href="homeUrl"
                    class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Prejsť na úvodnú stránku
                </a>
            </template>
        </div>
    </div>
</template>
