<script setup lang="ts">
import { useUiStore } from '@/stores/ui';
import { CheckCircle2, AlertTriangle, AlertCircle, Info, X } from 'lucide-vue-next';

const ui = useUiStore();
</script>

<template>
    <div class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none">
        <transition-group
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in ui.toasts"
                :key="toast.id"
                class="pointer-events-auto flex items-start gap-3 p-4 rounded-xl shadow-lg border backdrop-blur-md transition-all"
                :class="{
                    'bg-emerald-50/95 dark:bg-emerald-950/90 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-100': toast.type === 'success',
                    'bg-rose-50/95 dark:bg-rose-950/90 border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-100': toast.type === 'error',
                    'bg-amber-50/95 dark:bg-amber-950/90 border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-100': toast.type === 'warning',
                    'bg-sky-50/95 dark:bg-sky-950/90 border-sky-200 dark:border-sky-800 text-sky-900 dark:text-sky-100': toast.type === 'info',
                }"
            >
                <div class="shrink-0 mt-0.5">
                    <CheckCircle2 v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    <AlertCircle v-else-if="toast.type === 'error'" class="w-5 h-5 text-rose-600 dark:text-rose-400" />
                    <AlertTriangle v-else-if="toast.type === 'warning'" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    <Info v-else class="w-5 h-5 text-sky-600 dark:text-sky-400" />
                </div>
                <div class="flex-1 text-sm">
                    <p v-if="toast.title" class="font-semibold mb-0.5">{{ toast.title }}</p>
                    <p class="text-slate-700 dark:text-slate-300 leading-snug">{{ toast.message }}</p>
                </div>
                <button
                    @click="ui.removeToast(toast.id)"
                    class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>
        </transition-group>
    </div>
</template>
