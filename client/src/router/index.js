import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('../views/HomeView.vue'),
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/Auth/LoginView.vue'),
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/Auth/RegisterView.vue'),
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../views/DashboardView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('../views/Auth/ForgotPasswordView.vue'),
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('../views/Auth/ResetPasswordView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/verify-email',
      name: 'verify-email',
      component: () => import('../views/Auth/VerifyEmailView.vue'),
    },
    {
      path: '/resend-verification',
      name: 'resend-verification',
      component: () => import('../views/Auth/ResendVerificationView.vue'),
    },
  ],
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  if (!authStore.isAuthCheckCompleted) {
    await authStore.getUser()
  }

  const guestRoutes = ['login', 'register', 'forgot-password', 'reset-password']

  if (authStore.isAuthenticated && guestRoutes.includes(to.name)) {
    next({ name: 'dashboard' })
  } else if (!authStore.isAuthenticated && to.meta.requiresAuth) {
    next({ name: 'login' })
  } else {
    next()
  }
})

export default router
