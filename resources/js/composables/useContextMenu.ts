import { ref } from 'vue';

const activeMenuId = ref<string | null>(null);
const menuPosition = ref<{ x: number; y: number }>({ x: 0, y: 0 });

let isListenerRegistered = false;

function setupGlobalListeners() {
    if (isListenerRegistered || typeof window === 'undefined') return;
    isListenerRegistered = true;

    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && activeMenuId.value) {
            activeMenuId.value = null;
        }
    });

    window.addEventListener('resize', () => {
        if (activeMenuId.value) {
            activeMenuId.value = null;
        }
    });

    window.addEventListener('scroll', () => {
        if (activeMenuId.value) {
            activeMenuId.value = null;
        }
    }, true);
}

export function useContextMenu() {
    setupGlobalListeners();

    function openMenu(
        id: string,
        x: number,
        y: number,
        estimatedWidth: number = 210,
        estimatedHeight: number = 290
    ) {
        let finalX = x;
        let finalY = y;

        if (typeof window !== 'undefined') {
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            if (finalX + estimatedWidth > viewportWidth - 12) {
                finalX = Math.max(12, viewportWidth - estimatedWidth - 12);
            }

            if (finalY + estimatedHeight > viewportHeight - 12) {
                finalY = Math.max(12, viewportHeight - estimatedHeight - 12);
            }
        }

        menuPosition.value = { x: finalX, y: finalY };
        activeMenuId.value = id;
    }

    function closeMenu() {
        activeMenuId.value = null;
    }

    function isOpen(id: string): boolean {
        return activeMenuId.value === id;
    }

    return {
        activeMenuId,
        menuPosition,
        openMenu,
        closeMenu,
        isOpen,
    };
}
