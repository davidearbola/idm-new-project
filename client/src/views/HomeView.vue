<script setup>
import { useAuthStore } from '@/stores/authStore'
import { storeToRefs } from 'pinia'

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
</script>

<template>
  <div class="p-5 mb-4 bg-light rounded-3 shadow-sm">
    <div class="container-fluid py-5">
      <h1 class="display-5 fw-bold">Benvenuto in AuthApp</h1>
      <p class="col-md-8 fs-4">
        Questa è un'applicazione dimostrativa costruita con Vue.js 3, Laravel 11 e Bootstrap 5.
        Mostra un flusso di autenticazione completo, sicuro e moderno tramite Laravel Sanctum.
      </p>
        <div v-if="!user">
          <RouterLink to="/login" class="btn btn-primary btn-lg" type="button">Accedi</RouterLink>
          <RouterLink to="/register" class="btn btn-secondary btn-lg ms-2" type="button">Registrati</RouterLink>
        </div>
         <div v-else>
          <RouterLink to="/dashboard" class="btn btn-success btn-lg" type="button">Vai alla tua Dashboard</RouterLink>
        </div>
    </div>
  </div>
</template>