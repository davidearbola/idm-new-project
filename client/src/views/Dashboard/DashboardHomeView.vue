<template>
    <div>
      <h1 class="display-5 fw-bold">Dashboard Principale</h1>
      <p class="lead text-muted">Benvenuto nella tua area personale, {{ user?.name }}.</p>
      <hr class="my-4">
      <p>
        Questa è la pagina principale della tua dashboard. Qui verranno visualizzate le statistiche 
        e le informazioni più importanti a colpo d'occhio.
      </p>
    </div>
  </template>
  
  <script setup>
  import { useAuthStore } from '@/stores/authStore';
  import { storeToRefs } from 'pinia';
  
  const authStore = useAuthStore();
  const { user } = storeToRefs(authStore);
  </script>