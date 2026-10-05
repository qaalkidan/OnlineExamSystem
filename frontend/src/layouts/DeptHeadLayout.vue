<script setup lang="ts">
import DeptHeadSidebar from './components/DeptHeadSidebar.vue'
import DeptHeadHeader from './components/DeptHeadHeader.vue'
import { ref, provide, watch } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

// On mobile (< 1024px), sidebar defaults to closed (false).
// On desktop (>= 1024px), sidebar defaults to open (true).
const isLargeScreen = () => typeof window !== 'undefined' && window.innerWidth >= 1024
const sidebarOpen = ref(isLargeScreen())

const toggleSidebar = () => { sidebarOpen.value = !sidebarOpen.value }
const closeSidebar = () => { sidebarOpen.value = false }

// Auto-close mobile drawer when route changes
watch(() => route.path, () => {
  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
    sidebarOpen.value = false
  }
})

provide('sidebarOpen', sidebarOpen)
provide('toggleSidebar', toggleSidebar)
provide('closeSidebar', closeSidebar)
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] font-sans flex overflow-x-hidden relative">
    
    <!-- Sidebar: handles mobile slide-in and desktop collapse -->
    <DeptHeadSidebar />

    <!-- Overlay backdrop for mobile/tablet when sidebar is open (< 1024px) -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-40 lg:hidden transition-opacity"
      @click="sidebarOpen = false"
    />

    <!-- Main Content Area: adjusts left padding ONLY on desktop (lg:) -->
    <div
      class="flex-1 flex flex-col min-w-0 w-full min-h-screen overflow-y-auto overflow-x-hidden scrollbar-hide transition-all duration-300 pl-0"
      :class="sidebarOpen ? 'lg:pl-56' : 'lg:pl-20'"
    >
      
      <!-- Header -->
      <DeptHeadHeader />

      <!-- Page Content -->
      <main class="flex-1 p-3.5 sm:p-5 md:p-6 lg:p-8 min-w-0 max-w-full overflow-x-hidden">
        <router-view :key="$route.fullPath" />
      </main>

    </div>
  </div>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
