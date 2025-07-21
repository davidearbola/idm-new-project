<script setup>
import { useAuthStore } from '@/stores/authStore';
import { storeToRefs } from 'pinia';
import { computed } from 'vue';
import OnboardingMedico from '@/components/OnBoardingMedico.vue'; // 1. Importa il nuovo componente

const authStore = useAuthStore();
const { user } = storeToRefs(authStore);

// 2. Controlla se tutti gli step sono completati
const isOnboardingCompleted = computed(() => {
    const anagrafica = user.value?.anagrafica_medico;
    if (!anagrafica) return false;
    return anagrafica.step_listino_completed_at && 
           anagrafica.step_profilo_completed_at && 
           anagrafica.step_staff_completed_at;
});
</script>

<template>
  <div>
    <OnboardingMedico 
      v-if="user?.role === 'medico' && user.anagrafica_medico && !isOnboardingCompleted"
      :anagrafica="user.anagrafica_medico"
    />

    <h1 class="display-5 fw-bold">Dashboard Principale</h1>
    <p class="lead text-muted">Benvenuto nella tua area personale, {{ user?.name }}.</p>
    <hr class="my-4">

    <div v-if="isOnboardingCompleted">
        <p>Il tuo profilo è completo! Presto inizierai a ricevere le proposte dei pazienti.</p>
    </div>
  </div>
</template>