<script setup>
import { ref, computed } from 'vue';
import DashboardSidebar from '@/components/DashboardSidebar.vue';

const isCollapsed = ref(true);

const sidebarWidth = computed(() => {
  return isCollapsed.value ? '80px' : '250px';
});

const handleToggleSidebar = () => {
  isCollapsed.value = !isCollapsed.value;
};
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