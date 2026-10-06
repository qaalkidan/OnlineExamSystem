<script setup lang="ts">
import { ref, provide, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from './components/Sidebar.vue'
import Header from './components/Header.vue'
import { useSemesterLockStore } from '../modules/instructor/store/semesterLockStore'
import SemesterLockedNoticeModal from '../modules/instructor/components/SemesterLockedNoticeModal.vue'

const lockStore = useSemesterLockStore()
const route = useRoute()

// Mobile / Tablet Drawer State
const sidebarOpen = ref(false)

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value
}

const closeSidebar = () => {
  sidebarOpen.value = false
}

provide('sidebarOpen', sidebarOpen)
provide('toggleSidebar', toggleSidebar)
provide('closeSidebar', closeSidebar)

// Close sidebar drawer automatically on navigation
watch(() => route.path, () => {
  sidebarOpen.value = false
})

onMounted(() => {
  lockStore.fetchLockStatus()
})
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] font-sans flex flex-col lg:flex-row min-w-0 max-w-full overflow-x-hidden relative">
    
    <!-- Backdrop Overlay on Mobile & Tablet -->
    <Transition
      enter-active-class="transition-opacity duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="sidebarOpen" 
        @click="closeSidebar" 
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40 lg:hidden cursor-pointer"
        aria-label="Close Navigation Drawer"
      ></div>
    </Transition>

    <!-- Sidebar (Drawer on mobile/tablet, Fixed on desktop) -->
    <Sidebar />

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col lg:pl-56 min-w-0 max-w-full overflow-x-hidden">
      
      <!-- Header -->
      <Header />

      <!-- Persistent Non-Intrusive Semester Lock Banner -->
      <div
        v-if="lockStore.isLocked"
        class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white px-4 sm:px-6 lg:px-8 py-2.5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs"
      >
        <div class="flex items-center gap-2.5 min-w-0">
          <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse shrink-0"></span>
          <span class="font-bold flex items-center gap-1.5 shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            Semester Locked
          </span>
          <span class="text-emerald-100 hidden md:inline">—</span>
          <span class="text-emerald-100 hidden md:inline truncate">
            Your semester academic submission is completed. Modifications are disabled. Contact Department Head if corrections are needed.
          </span>
        </div>
        <router-link
          to="/instructor/semester-submission"
          class="underline font-semibold hover:text-emerald-200 transition-colors shrink-0 text-left sm:text-right"
        >
          View Details
        </router-link>
      </div>

      <!-- Page Content with Responsive Padding -->
      <main class="flex-1 p-3.5 sm:p-5 lg:p-7 min-w-0 max-w-full overflow-x-hidden">
        <router-view :key="$route.path" />
      </main>

    </div>

    <!-- Reusable Semester Locked Modal -->
    <SemesterLockedNoticeModal />
  </div>
</template>

<style scoped>
</style>
