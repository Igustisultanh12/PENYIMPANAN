<script setup lang="ts">
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const ui = useUiStore();

onMounted(() => {
    ui.initTheme();
    auth.fetchUser();
});
</script>

<template>
    <div class="h-full w-full">
        <router-view />
    </div>
</template>
