<script setup>
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import { storeToRefs } from 'pinia'
import { useToast } from 'vue-toastification';
import HeaderPublic from './components/HeaderPublic.vue'
import FooterPublic from './components/FooterPublic.vue'

const toast = useToast();
const router = useRouter()
const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const handleLogout = async () => {
  const response = await authStore.logout();
  
  if (response.success) {
    toast.success(response.message);
    router.push({ name: 'login' });
  } else {
    toast.error(response.message);
  }
};

</script>

<template>
  <HeaderPublic/>
  <main>
    <RouterView />
  </main>
  <FooterPublic/>
</template>

<style>
body {
  background-color: #f8f9fa;
}
</style>