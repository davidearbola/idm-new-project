<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import DashboardSidebar from '@/components/DashboardSidebar.vue';
import { usePazienteStore } from '@/stores/pazienteStore';

const isCollapsed = ref(true);
const pazienteStore = usePazienteStore(); 
let pollingInterval = null;

const sidebarWidth = computed(() => {
  return isCollapsed.value ? '80px' : '250px';
});

const handleToggleSidebar = () => {
  isCollapsed.value = !isCollapsed.value;
};

onMounted(() => {
  pazienteStore.checkForNotifications();
  
  pollingInterval = setInterval(() => {
    pazienteStore.checkForNotifications();
  }, 30000); 
});

onUnmounted(() => {
  clearInterval(pollingInterval);
});
</script>

<template>
  <div class="dashboard-layout" :style="{ '--sidebar-width': sidebarWidth }">
    <DashboardSidebar 
      :is-collapsed="isCollapsed"
      @toggle-sidebar="handleToggleSidebar"
    />
    <div class="main-content">
      <div class="container-fluid p-3 p-md-4">
        <slot />
      </div>
    </div>
  </div>
</template>

<style scoped>
.dashboard-layout { display: flex; background-color: #f4f7f6; }
.main-content { flex-grow: 1; padding-left: var(--sidebar-width); padding-bottom: 80px; min-height: 100vh; transition: padding-left 0.3s ease; }
@media (max-width: 991.98px) { .main-content { padding-left: 0; } }
</style>