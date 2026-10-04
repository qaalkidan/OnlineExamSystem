<script setup lang="ts">
import Sidebar from './components/Sidebar.vue'
import Header from './components/Header.vue'
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] font-sans flex">
    
    <!-- Sidebar -->
    <Sidebar />

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col pl-56 min-w-0">
      
      <!-- Header -->
      <Header />

      <!-- Page Content -->
      <main class="flex-1 p-8">
        <router-view :key="$route.path" />
      </main>

    </div>
  </div>
</template>

<style scoped>
</style>
