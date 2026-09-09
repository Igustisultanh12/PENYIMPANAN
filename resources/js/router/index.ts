import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import AppLayout from '@/layouts/AppLayout.vue';
import LandingPage from '@/pages/LandingPage.vue';
import LoginPage from '@/pages/Auth/LoginPage.vue';
import RegisterPage from '@/pages/Auth/RegisterPage.vue';
import VerifyEmailPage from '@/pages/Auth/VerifyEmailPage.vue';
import PublicSharePage from '@/pages/PublicSharePage.vue';
import DrivePage from '@/pages/DrivePage.vue';
import RecentPage from '@/pages/RecentPage.vue';
import StarredPage from '@/pages/StarredPage.vue';
import SharedPage from '@/pages/SharedPage.vue';
import TrashPage from '@/pages/TrashPage.vue';
import StoragePage from '@/pages/StoragePage.vue';
import SecurityPage from '@/pages/SecurityPage.vue';
import SettingsPage from '@/pages/SettingsPage.vue';

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        name: 'landing',
        component: LandingPage,
        meta: { guestOnly: false },
    },
    {
        path: '/login',
        name: 'login',
        component: LoginPage,
        meta: { guestOnly: true },
    },
    {
        path: '/register',
        name: 'register',
        component: RegisterPage,
        meta: { guestOnly: true },
    },
    {
        path: '/verify-email/:token?',
        name: 'verify-email',
        component: VerifyEmailPage,
    },
    {
        path: '/s/:token',
        name: 'public-share',
        component: PublicSharePage,
    },
    {
        path: '/',
        component: AppLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: 'drive',
                name: 'drive',
                component: DrivePage,
            },
            {
                path: 'recent',
                name: 'recent',
                component: RecentPage,
            },
            {
                path: 'starred',
                name: 'starred',
                component: StarredPage,
            },
            {
                path: 'shared',
                name: 'shared',
                component: SharedPage,
            },
            {
                path: 'trash',
                name: 'trash',
                component: TrashPage,
            },
            {
                path: 'storage',
                name: 'storage',
                component: StoragePage,
            },
            {
                path: 'security',
                name: 'security',
                component: SecurityPage,
            },
            {
                path: 'settings',
                name: 'settings',
                component: SettingsPage,
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('mystorage_token');

    if (to.meta.requiresAuth && !token) {
        return next({ name: 'login' });
    }

    if (to.meta.guestOnly && token) {
        return next({ name: 'drive' });
    }

    next();
});

export default router;
