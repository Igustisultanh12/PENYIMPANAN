<script setup lang="ts">
import Sidebar from '@/components/Sidebar.vue';
import TopHeader from '@/components/TopHeader.vue';
import UploadManagerModal from '@/components/UploadManagerModal.vue';
import ToastContainer from '@/components/ToastContainer.vue';
</script>

<template>
    <div class="h-screen w-screen flex bg-slate-50 dark:bg-slate-950 overflow-hidden text-slate-900 dark:text-slate-100">
        <!-- Persistent Sidebar -->
        <Sidebar />

        <!-- Main Workspace Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <TopHeader />

            <main class="flex-1 overflow-hidden relative">
                <router-view v-slot="{ Component }">
                    <transition name="fade" mode="out-in">
                        <component :is="Component" />
                    </transition>
                </router-view>
            </main>
        </div>

        <!-- Floating Upload Manager & Toasts -->
        <UploadManagerModal />
        <ToastContainer />
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
