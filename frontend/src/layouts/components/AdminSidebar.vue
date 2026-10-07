<script setup lang="ts">
import { useRoute } from 'vue-router'
import { inject } from 'vue'

const route = useRoute()
const sidebarOpen = inject<{ value: boolean }>('sidebarOpen', { value: true })

const navItems = [
  { name: 'Dashboard',   path: '/admin/dashboard',    icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
  { name: 'Instructors', path: '/admin/instructors',  icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
  { name: 'Students',    path: '/admin/students',     icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
  { name: 'Courses',     path: '/admin/courses',      icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
  { name: 'Exams',       path: '/admin/exams',        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
  { name: 'Exam Control', path: '/admin/exam-control', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
  { name: 'Departments', path: '/admin/departments',  icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
  { name: 'Reports',     path: '/admin/reports',      icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
  { name: 'Active Logs', path: '/admin/activity-logs', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  { name: 'Academic Calendar', path: '/admin/academic-calendar', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { name: 'Settings',    path: '/admin/settings',     icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
]

import { ref, onMounted } from 'vue'
import apiClient from '../../core/api/apiClient'

const logCount = ref(0)

onMounted(async () => {
  try {
    const res = await apiClient.get('/admin/activity-logs/unread-count')
    logCount.value = res.data.count
  } catch (err) {
    console.error('Failed to fetch activity log count', err)
  }
})

// Listen for individual log read events
window.addEventListener('log-read', () => {
  if (logCount.value > 0) logCount.value--
})

// Listen for "mark all read" — reset the count to 0
window.addEventListener('log-count-update', (e: Event) => {
  const detail = (e as CustomEvent).detail
  logCount.value = detail.count ?? 0
})

// Listen for new activities to refresh the count
window.addEventListener('activity-logged', async () => {
  try {
    const res = await apiClient.get('/admin/activity-logs/unread-count')
    logCount.value = res.data.count
  } catch (err) {
    console.error('Failed to update activity log count', err)
  }
})
const closeSidebar = inject<() => void>('closeSidebar', () => { sidebarOpen.value = false })

const handleLinkClick = () => {
  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
    closeSidebar()
  }
}
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
          <span class="text-[10px] text-slate-500 font-medium">System Administration</span>
        </div>
      </div>

      <!-- Close Drawer Button for mobile/tablet screens -->
      <button
        v-if="sidebarOpen"
        @click="closeSidebar"
        type="button"
        class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
        title="Close menu"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-4 py-3 space-y-1.5 overflow-y-auto overflow-x-hidden min-h-0 scrollbar-none">
      <router-link 
        v-for="item in navItems" 
        :key="item.name"
        :to="item.path"
        @click="handleLinkClick"
        class="flex items-center gap-3 py-2.5 rounded-xl transition-all duration-200 group relative"
        :class="[
          route.path.startsWith(item.path) 
            ? 'bg-rose-50 text-rose-600 font-semibold' 
            : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700',
          sidebarOpen ? 'px-3.5' : 'justify-center px-0'
        ]"
        :title="!sidebarOpen ? item.name : undefined"
      >
        <svg 
          class="w-5 h-5 flex-shrink-0 transition-colors duration-200" 
          :class="route.path.startsWith(item.path) ? 'text-rose-600' : 'text-slate-400 group-hover:text-slate-600'"
          fill="none" 
          stroke="currentColor" 
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
        </svg>
        <span v-if="sidebarOpen" class="text-[13px] tracking-wide font-medium whitespace-nowrap flex-1">{{ item.name }}</span>
        
        <span v-if="sidebarOpen && item.name === 'Active Logs' && logCount > 0" class="px-2 py-0.5 bg-rose-500 text-white text-[10px] font-bold rounded-full shadow-sm">
          {{ logCount > 99 ? '99+' : logCount }}
        </span>
        <div v-else-if="!sidebarOpen && item.name === 'Active Logs' && logCount > 0" class="absolute top-2 right-2 w-2 h-2 bg-rose-500 rounded-full border border-white"></div>
      </router-link>
    </nav>

    <!-- Bottom Graphic -->
    <div class="px-4 py-3.5 mt-auto shrink-0 border-t border-slate-50">
      <div class="w-full flex flex-col items-center justify-center opacity-70">
        <div class="w-12 h-12 border-2 border-slate-200 rounded-t-full mb-1.5" :class="!sidebarOpen && 'w-8 h-8 border'"></div>
        <div class="flex gap-1 mb-1.5">
           <div class="w-2.5 h-3.5 border border-slate-200" :class="!sidebarOpen && 'w-1 h-2'"></div>
           <div class="w-2.5 h-3.5 border border-slate-200" :class="!sidebarOpen && 'w-1 h-2'"></div>
           <div class="w-2.5 h-3.5 border border-slate-200" :class="!sidebarOpen && 'w-1 h-2'"></div>
        </div>
        <span v-if="sidebarOpen" class="text-xs font-bold text-[#2b4c7e] tracking-wider uppercase whitespace-nowrap leading-tight">Wollo University</span>
        <span v-if="sidebarOpen" class="text-[9px] text-slate-400 font-medium whitespace-nowrap leading-tight">Super Admin Portal</span>
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
