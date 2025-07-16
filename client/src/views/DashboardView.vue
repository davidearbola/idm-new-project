<script setup>
import { useAuthStore } from '@/stores/authStore'
import { storeToRefs } from 'pinia';

const authStore = useAuthStore();
const { user } = storeToRefs(authStore);
</script>

<template>
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-header">
          <h3>Dashboard</h3>
        </div>
        <div class="card-body">
          <div v-if="user">
            <h5 class="card-title">Benvenuto, {{ user.name }}!</h5>
            <p class="card-text">Questa è la tua area riservata. Siamo contenti di vederti.</p>
            <ul class="list-group list-group-flush mb-3">
              <li class="list-group-item"><strong>ID Utente:</strong> {{ user.id }}</li>
              <li class="list-group-item"><strong>Email:</strong> {{ user.email }}</li>
            </ul>
          </div>
           <div v-else>
            <p>Caricamento dati utente...</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>