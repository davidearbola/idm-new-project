<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink, useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/authStore'
import { storeToRefs } from 'pinia'
import logoSrc from '@/assets/images/logo-IDM.png';
import { useToast } from 'vue-toastification';

const authStore = useAuthStore();
const { user } = storeToRefs(authStore);
const router = useRouter();
const route = useRoute();
const toast = useToast();
const togglerButtonRef = ref(null);
const collapseMenuRef = ref(null);

const dashboardLink = computed(() => {
  if (!user.value) return '/login';
  return user.value.role === 'medico' ? '/medico/dashboard' : '/paziente/dashboard';
});

const logout = async () => {
  const response = await authStore.logout();
  if (!response.success) {
    toast.error(response.message);
  }
  toast.success(response.message);
  router.push({ name: 'home' });
};

watch(() => route.path, () => {
  if (collapseMenuRef.value && collapseMenuRef.value.classList.contains('show')) {
    togglerButtonRef.value?.click();
  }
});
</script>

<template>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <RouterLink class="navbar-brand fw-bold" to="/">
              <img :src="logoSrc" alt="Il Dentista Migliore Logo" style="height: 2.5rem;">
            </RouterLink>

            <button ref="togglerButtonRef" class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar" aria-controls="publicNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="publicNavbar" ref="collapseMenuRef">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                  <li class="nav-item">
                      <RouterLink class="nav-link" to="/come-funziona">Come Funziona</RouterLink>
                  </li>
                  <template v-if="!user">
                    <li class="nav-item">
                        <RouterLink class="nav-link" to="/register-medico">Sei un dentista?</RouterLink>
                    </li>
                        <li class="nav-item ms-lg-2">
                            <RouterLink to="/login" class="btn btn-primary btn-sm">Accedi</RouterLink>
                        </li>
                        <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                            <RouterLink to="/register" class="btn btn-accent btn-sm">Registrati</RouterLink>
                        </li>
                    </template>
                    <template v-else>
                         <li class="nav-item ms-lg-2">
                            <RouterLink class="btn btn-accent btn-sm" :to="dashboardLink">Area Personale</RouterLink>
                        </li>
                         <li class="nav-item ms-lg-2">
                            <a href="#" class="btn btn-primary btn-sm" @click="logout">Logout</a>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </nav>
</template>

<style scoped>
.btn {
  padding: 0.5rem 1.25rem;
  font-weight: 500;
  color: white !important;
}
.nav-link.router-link-active,
.nav-link.router-link-exact-active {
  font-weight: 600;
  color: var(--bs-primary) !important;
}

nav {
  height: 10vh;
}

@media (max-width: 991.98px) {
  .navbar-collapse {
    background-color: white;
    padding: 1rem;
    margin-top: 0.5rem;
    border-radius: var(--bs-border-radius);
    border: 1px solid rgba(0, 0, 0, 0.1);
  }
}
</style>
