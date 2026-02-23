<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    images: {
        type: Array,
        default: () => [],
    },
    initialIndex: {
        type: Number,
        default: 0,
    },
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const currentIndex = ref(props.initialIndex);
const isZoomed = ref(false);

watch(() => props.initialIndex, (newIndex) => {
    currentIndex.value = newIndex;
});

watch(() => props.show, (newShow) => {
    if (newShow) {
        isZoomed.value = false;
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});

const currentImage = () => {
    return props.images[currentIndex.value] || null;
};

const next = () => {
    if (currentIndex.value < props.images.length - 1) {
        currentIndex.value++;
        isZoomed.value = false;
    }
};

const prev = () => {
    if (currentIndex.value > 0) {
        currentIndex.value--;
        isZoomed.value = false;
    }
};

const close = () => {
    emit('close');
};

const toggleZoom = () => {
    isZoomed.value = !isZoomed.value;
};

const handleKeydown = (e) => {
    if (!props.show) return;

    switch (e.key) {
        case 'Escape':
            close();
            break;
        case 'ArrowRight':
            next();
            break;
        case 'ArrowLeft':
            prev();
            break;
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition name="lightbox">
            <div
                v-if="show && images.length > 0"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/95"
                @click.self="close"
            >
                <!-- Close button -->
                <button
                    @click="close"
                    class="absolute top-4 right-4 z-10 p-2 text-white/70 hover:text-white bg-black/30 hover:bg-black/50 rounded-full transition-colors"
                >
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Image counter -->
                <div v-if="images.length > 1" class="absolute top-4 left-4 px-3 py-1 bg-black/50 text-white rounded-full text-sm">
                    {{ currentIndex + 1 }} / {{ images.length }}
                </div>

                <!-- Previous button -->
                <button
                    v-if="images.length > 1 && currentIndex > 0"
                    @click="prev"
                    class="absolute left-4 top-1/2 -translate-y-1/2 p-3 text-white/70 hover:text-white bg-black/30 hover:bg-black/50 rounded-full transition-colors"
                >
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <!-- Next button -->
                <button
                    v-if="images.length > 1 && currentIndex < images.length - 1"
                    @click="next"
                    class="absolute right-4 top-1/2 -translate-y-1/2 p-3 text-white/70 hover:text-white bg-black/30 hover:bg-black/50 rounded-full transition-colors"
                >
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <!-- Image container -->
                <div
                    class="max-w-[90vw] max-h-[90vh] flex items-center justify-center"
                    :class="{ 'cursor-zoom-in': !isZoomed, 'cursor-zoom-out': isZoomed }"
                    @click="toggleZoom"
                >
                    <img
                        :src="currentImage()?.url"
                        :alt="currentImage()?.original_name"
                        class="max-h-[90vh] transition-transform duration-300"
                        :class="isZoomed ? 'scale-150' : 'scale-100'"
                    />
                </div>

                <!-- Image info -->
                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 px-4 py-2 bg-black/50 text-white rounded-lg text-center max-w-[90vw]">
                    <p class="font-medium truncate">{{ currentImage()?.original_name }}</p>
                </div>

                <!-- Thumbnails -->
                <div v-if="images.length > 1" class="absolute bottom-16 left-1/2 -translate-x-1/2 flex gap-2 p-2 bg-black/50 rounded-lg max-w-[90vw] overflow-x-auto">
                    <button
                        v-for="(image, index) in images"
                        :key="index"
                        @click.stop="currentIndex = index; isZoomed = false"
                        class="flex-shrink-0 w-12 h-12 rounded overflow-hidden border-2 transition-colors"
                        :class="index === currentIndex ? 'border-white' : 'border-transparent hover:border-white/50'"
                    >
                        <img :src="image.url" :alt="image.original_name" class="w-full h-full object-cover" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.lightbox-enter-active,
.lightbox-leave-active {
    transition: opacity 0.3s ease;
}

.lightbox-enter-from,
.lightbox-leave-to {
    opacity: 0;
}
</style>
