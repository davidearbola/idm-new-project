import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // **** ROTTE PUBBLICHE ****
    {
      path: '/',
      name: 'home',
      component: () => import('../views/HomeView.vue'),
      meta: { layout: 'PublicLayout', showHeader: true, showFooter: true },
    },
    {
      path: '/come-funziona',
      name: 'come-funziona',
      component: () => import('../views/ComeFunzionaView.vue'),
      meta: { layout: 'PublicLayout', showHeader: true, showFooter: true },
    },
    // **** ROTTE AUTH ****
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/Auth/LoginView.vue'),
      meta: { layout: 'AuthLayout', requiresGuest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/Auth/RegisterView.vue'),
      meta: { layout: 'AuthLayout', requiresGuest: true },
    },
    {
      path: '/register-medico',
      name: 'register-medico',
      component: () => import('../views/Auth/RegisterMedicoView.vue'),
      meta: { layout: 'AuthLayout', requiresGuest: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('../views/Auth/ForgotPasswordView.vue'),
      meta: { layout: 'AuthLayout', requiresGuest: true },
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
      meta: { layout: 'AuthLayout', requiresGuest: true },
    },
    {
      path: '/resend-verification',
      name: 'resend-verification',
      component: () => import('../views/Auth/ResendVerificationView.vue'),
      meta: { layout: 'AuthLayout', requiresGuest: true },
    },
    // **** ROTTE DASHBOARD ****
    {
      path: '/dashboard',
      meta: {
        layout: 'DashboardLayout',
        requiresAuth: true,
      },
      children: [
        // Rotte Comuni
        {
          path: '',
          name: 'dashboard-home',
          component: () => import('../views/Dashboard/DashboardHomeView.vue'),
        },
        {
          path: 'impostazioni',
          name: 'dashboard-impostazioni',
          component: () => import('../views/Dashboard/ImpostazioniView.vue'),
        },
        // Rotte Paziente
        {
          path: 'proposte',
          name: 'dashboard-proposte',
          component: () => import('../views/Dashboard/Paziente/ProposteView.vue'),
          meta: { roles: ['paziente'] },
        },
        {
          path: 'carica-preventivo',
          name: 'dashboard-carica-preventivo',
          component: () => import('../views/Dashboard/Paziente/CaricaPreventivoView.vue'),
          meta: { roles: ['paziente'] },
        },

        // Rotte Medico
        {
          path: 'preventivi-accettati',
          name: 'dashboard-preventivi-accettati',
          component: () => import('../views/Dashboard/Medico/PreventiviAccettatiView.vue'),
          meta: { roles: ['medico'] },
        },
        {
          path: 'profilo',
          name: 'dashboard-profilo',
          component: () => import('../views/Dashboard/Medico/ProfiloStudioView.vue'),
          meta: { roles: ['medico'] },
        },
        {
          path: 'listino',
          name: 'dashboard-listino',
          component: () => import('../views/Dashboard/Medico/ListinoView.vue'),
          meta: { roles: ['medico'] },
        },
      ],
    },
  ],
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    } else {
      return { top: 0 }
    }
  },
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  if (!authStore.isAuthCheckCompleted) {
    await authStore.getUser()
  }

  const userRole = authStore.user?.role
  const requiredRoles = to.meta.roles

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login' })
  }

  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    return next({ path: '/dashboard' })
  }

  if (requiredRoles && requiredRoles.length > 0) {
    if (!userRole || !requiredRoles.includes(userRole)) {
      return next({ path: '/dashboard' })
    }
  }

  next()
})

export default router
