<script setup lang="ts">
import { useUiStore } from '@/stores/ui';
import { Sun, Moon } from 'lucide-vue-next';

defineProps<{
    showLabel?: boolean;
    variant?: 'icon' | 'pill';
}>();

const ui = useUiStore();
</script>

<template>
    <!-- Variant: Pill Switch -->
    <button
        v-if="variant === 'pill'"
        @click="ui.toggleDarkMode"
        type="button"
        class="relative inline-flex h-8 w-14 items-center rounded-full p-1 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
        :class="ui.isDarkMode ? 'bg-indigo-950 border border-indigo-700/60 shadow-inner' : 'bg-amber-100 border border-amber-300/80'"
        :title="ui.isDarkMode ? 'Ganti ke Mode Terang' : 'Ganti ke Mode Gelap'"
    >
        <span
            class="flex h-6 w-6 transform items-center justify-center rounded-full transition-transform duration-300 shadow-md"
            :class="ui.isDarkMode ? 'translate-x-6 bg-slate-900 text-amber-300 shadow-indigo-900/50' : 'translate-x-0 bg-white text-amber-500 shadow-amber-400/30'"
        >
            <Moon v-if="ui.isDarkMode" class="w-3.5 h-3.5 transition-transform duration-300 rotate-0 hover:-rotate-12" />
            <Sun v-else class="w-3.5 h-3.5 transition-transform duration-300 rotate-0 hover:rotate-45" />
        </span>
    </button>

    <!-- Variant: Icon Button (Default) -->
    <button
        v-else
        @click="ui.toggleDarkMode"
        type="button"
        class="group relative p-2.5 rounded-2xl text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100/80 dark:bg-slate-800/80 hover:bg-slate-200/80 dark:hover:bg-slate-700/80 border border-slate-200/60 dark:border-slate-700/60 transition-all duration-300 hover:scale-105 active:scale-95 shadow-xs flex items-center gap-2"
        :title="ui.isDarkMode ? 'Ganti ke Mode Terang' : 'Ganti ke Mode Gelap'"
    >
        <div class="relative w-4 h-4 flex items-center justify-center overflow-hidden">
            <!-- Sun Icon (Light Mode) -->
            <Sun
                class="w-4 h-4 text-amber-500 transition-all duration-500 absolute inset-0 m-auto"
                :class="ui.isDarkMode ? 'opacity-0 rotate-90 scale-0' : 'opacity-100 rotate-0 scale-100 group-hover:rotate-45'"
            />
            <!-- Moon Icon (Dark Mode) -->
            <Moon
                class="w-4 h-4 text-blue-400 transition-all duration-500 absolute inset-0 m-auto"
                :class="ui.isDarkMode ? 'opacity-100 rotate-0 scale-100 group-hover:-rotate-12' : 'opacity-0 -rotate-90 scale-0'"
            />
        </div>
        <span v-if="showLabel" class="text-xs font-semibold select-none">
            {{ ui.isDarkMode ? 'Gelap' : 'Terang' }}
        </span>
    </button>
</template>
