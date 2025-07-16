<script setup>
import HeaderPublic from './components/HeaderPublic.vue'
import FooterPublic from './components/FooterPublic.vue'

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