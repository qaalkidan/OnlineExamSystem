<script setup lang="ts">
import { onMounted } from 'vue'
import Sidebar from './components/Sidebar.vue'
import Header from './components/Header.vue'
import { useSemesterLockStore } from '../modules/instructor/store/semesterLockStore'
import SemesterLockedNoticeModal from '../modules/instructor/components/SemesterLockedNoticeModal.vue'

const lockStore = useSemesterLockStore()

onMounted(() => {
  lockStore.fetchLockStatus()
})
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] font-sans flex">
    
    <!-- Sidebar -->
    <Sidebar />

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col pl-56 min-w-0">
      
      <!-- Header -->
      <Header />

      <!-- Persistent Non-Intrusive Semester Lock Banner -->
      <div
        v-if="lockStore.isLocked"
        class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white px-8 py-2.5 shadow-sm flex items-center justify-between text-xs"
      >
        <div class="flex items-center gap-2.5">
          <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse"></span>
          <span class="font-bold flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            Semester Locked (Read-Only Mode)
          </span>
          <span class="text-emerald-100 hidden sm:inline">—</span>
          <span class="text-emerald-100 hidden sm:inline">
            Your semester academic submission is completed. Modifications are disabled. Contact Department Head if corrections are needed.
          </span>
        </div>
        <button
          @click="lockStore.promptLockedNotice('Academic Actions Overview')"
          class="underline font-semibold hover:text-emerald-200 transition-colors shrink-0"
        >
          View Details
        </button>
      </div>

      <!-- Page Content -->
      <main class="flex-1 p-8">
        <router-view :key="$route.path" />
      </main>

    </div>

    <!-- Reusable Semester Locked Modal -->
    <SemesterLockedNoticeModal />
  </div>
</template>

<style scoped>
</style>
