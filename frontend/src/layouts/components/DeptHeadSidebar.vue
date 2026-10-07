<script setup lang="ts">
import { useRoute } from 'vue-router'
import { ref, computed, onMounted, inject } from 'vue'
import apiClient from '../../core/api/apiClient'

const route = useRoute()
const sidebarOpen = inject<{ value: boolean }>('sidebarOpen', { value: true })
const closeSidebar = inject<() => void>('closeSidebar', () => { sidebarOpen.value = false })
const pendingCount = ref<number | null>(null)

const fetchPendingCount = async () => {
  try {
    const res = await apiClient.get('/dept-head/dashboard-stats')
    const submissionsKpi = res.data?.data?.stats?.find((s: any) => s.id === 'submissions')
    if (submissionsKpi && Number(submissionsKpi.value) > 0) {
      pendingCount.value = Number(submissionsKpi.value)
    } else {
      pendingCount.value = null
    }
  } catch {
    pendingCount.value = null
  }
}

onMounted(() => {
  fetchPendingCount()
})

const handleLinkClick = () => {
  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
    closeSidebar()
  }
}

const navItems = computed(() => [
  { name: 'Dashboard',   path: '/dept-head/dashboard',    icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
  { name: 'Instructors', path: '/dept-head/instructors',  icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
  { name: 'Students',    path: '/dept-head/students',     icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
  { name: 'Courses',     path: '/dept-head/courses',      icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
  { name: 'Exams',       path: '/dept-head/exams',        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
  { name: 'Results',     path: '/dept-head/results',      icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
  { name: 'Academic Calendar', path: '/dept-head/schedule', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { name: 'Reports',     path: '/dept-head/reports',      icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { name: 'Semester Submissions', path: '/dept-head/semester-submissions', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', badge: pendingCount.value },
  { name: 'Active Logs', path: '/dept-head/activity-logs', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  { name: 'Settings',    path: '/dept-head/settings',     icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
])
</script>

<template>
  <aside 
    class="bg-white border-r border-slate-100 flex flex-col h-screen fixed left-0 top-0 overflow-hidden transition-all duration-300 z-50 lg:z-30"
    :class="[
      sidebarOpen 
        ? 'translate-x-0 w-64 lg:w-56 shadow-2xl lg:shadow-none' 
        : '-translate-x-full lg:translate-x-0 lg:w-20'
    ]"
  >
    
    <!-- Logo Area -->
    <div class="flex items-center justify-between px-4 sm:px-5 pt-5 pb-4 border-b border-slate-50 shrink-0" :class="!sidebarOpen && 'lg:justify-center'">
      <div class="flex items-center gap-2.5 min-w-0">
        <img src="../../assets/images/logo.png" alt="Wollo University" class="w-9 h-9 object-contain rounded-full shadow-sm shrink-0" />
        <div v-if="sidebarOpen" class="flex flex-col whitespace-nowrap">
          <span class="text-[14px] font-bold text-slate-900 leading-tight">Wollo University</span>
          <span class="text-[10px] text-slate-500 font-medium">Department Head</span>
        </div>
      </div>

      <!-- Close Drawer Button for mobile/tablet screens -->
      <button
        v-if="sidebarOpen"
        @click="closeSidebar"
        type="button"
        class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
        title="Close menu"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-3 sm:px-4 py-3 space-y-1.5 overflow-y-auto overflow-x-hidden min-h-0 scrollbar-none">
      <router-link 
        v-for="item in navItems" 
        :key="item.name"
        :to="item.path"
        @click="handleLinkClick"
        class="flex items-center rounded-xl transition-all duration-200 group relative min-h-[44px]"
        :class="[
          sidebarOpen ? 'px-3.5 py-2.5 justify-between' : 'lg:justify-center p-2.5',
          route.path.startsWith(item.path) 
            ? 'bg-indigo-50 text-[#5138ed] font-semibold shadow-xs' 
            : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'
        ]"
        :title="!sidebarOpen ? item.name : undefined"
      >
        <div class="flex items-center gap-3 min-w-0">
          <svg 
            class="w-5 h-5 shrink-0 transition-colors duration-200" 
            :class="route.path.startsWith(item.path) ? 'text-[#5138ed]' : 'text-slate-400 group-hover:text-slate-600'"
            fill="none" 
            stroke="currentColor" 
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
          </svg>
          <span v-if="sidebarOpen" class="text-[13px] tracking-wide font-medium truncate">{{ item.name }}</span>
        </div>
        
        <!-- Notification Badge -->
        <span 
          v-if="item.badge" 
          class="flex items-center justify-center text-[10px] font-bold text-white bg-rose-500 rounded-full shadow-sm shrink-0"
          :class="sidebarOpen ? 'w-5 h-5 ml-1' : 'w-2 h-2 absolute top-1.5 right-1.5 ring-2 ring-white'"
        >
          <span v-if="sidebarOpen">{{ item.badge }}</span>
        </span>
      </router-link>
    </nav>

    <!-- Bottom Graphic: only when sidebar is open -->
    <div v-if="sidebarOpen" class="p-4 sm:p-5 mt-auto border-t border-slate-50 shrink-0">
      <div class="w-full flex flex-col items-center justify-center opacity-60">
        <div class="w-12 h-12 border-2 border-slate-200 rounded-t-full mb-1 flex items-center justify-center">
          <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
        </div>
        <span class="text-[11px] font-bold text-slate-500 tracking-wide uppercase">Department</span>
        <span class="text-[9px] text-slate-400 font-medium">Head Portal</span>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
