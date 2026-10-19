<script setup lang="ts">
import { ref, provide, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from './components/Sidebar.vue'
import Header from './components/Header.vue'
import { useSemesterLockStore } from '../modules/instructor/store/semesterLockStore'
import SemesterLockedNoticeModal from '../modules/instructor/components/SemesterLockedNoticeModal.vue'

const lockStore = useSemesterLockStore()
const route = useRoute()

// Desktop (>= 1024px) defaults to expanded (true).
// Mobile / Tablet (< 1024px) defaults to closed drawer (false).
const isLargeScreen = () => typeof window !== 'undefined' && window.innerWidth >= 1024
const sidebarOpen = ref(isLargeScreen())

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value
}

const closeSidebar = () => {
  sidebarOpen.value = false
}

provide('sidebarOpen', sidebarOpen)
provide('toggleSidebar', toggleSidebar)
provide('closeSidebar', closeSidebar)

// Close sidebar drawer automatically on navigation on mobile/tablet
watch(() => route.path, () => {
  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
    sidebarOpen.value = false
  }
})

// Prevent background body scrolling when mobile drawer is open
watch(sidebarOpen, (open) => {
  if (typeof window !== 'undefined') {
    if (window.innerWidth < 1024) {
      document.body.style.overflow = open ? 'hidden' : ''
    } else {
      document.body.style.overflow = ''
    }
  }
})

// Close mobile drawer on Escape key
const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && sidebarOpen.value && typeof window !== 'undefined' && window.innerWidth < 1024) {
    sidebarOpen.value = false
  }
}

const handleResize = () => {
  if (typeof window !== 'undefined' && window.innerWidth >= 1024) {
    document.body.style.overflow = ''
  }
}

let lockPollTimer: any = null

const checkLockAndAutoSignOut = async () => {
  try {
    await lockStore.fetchLockStatus(true)
    if (lockStore.isLocked) {
      const lockMsg = lockStore.lockMessage || 'Access Locked: The Department Head has closed access to your semester workspace. Please contact your Department Head if you need access restored.'
      localStorage.setItem('semester_locked_notice', lockMsg)
      sessionStorage.setItem('semester_locked_notice', lockMsg)
      if (lockStore.courseTitle) {
        localStorage.setItem('semester_locked_course', lockStore.courseTitle)
        sessionStorage.setItem('semester_locked_course', lockStore.courseTitle)
      }
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
      localStorage.removeItem('instructor_context')
      window.location.href = '/access-locked'
    }
  } catch (err: any) {
    if (err?.response?.status === 401 || (err?.response?.status === 403 && err?.response?.data?.locked)) {
      const lockMsg = err?.response?.data?.message || 'Access Locked: The Department Head has closed access to your semester workspace. Please contact your Department Head if you need access restored.'
      localStorage.setItem('semester_locked_notice', lockMsg)
      sessionStorage.setItem('semester_locked_notice', lockMsg)
      if (err?.response?.data?.course_title) {
        localStorage.setItem('semester_locked_course', err.response.data.course_title)
        sessionStorage.setItem('semester_locked_course', err.response.data.course_title)
      }
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
      localStorage.removeItem('instructor_context')
      window.location.href = '/access-locked'
    }
  }
}

onMounted(() => {
  checkLockAndAutoSignOut()
  lockPollTimer = setInterval(checkLockAndAutoSignOut, 3000)
  window.addEventListener('keydown', handleKeyDown)
  window.addEventListener('resize', handleResize)
  window.addEventListener('focus', checkLockAndAutoSignOut)
})

onUnmounted(() => {
  if (lockPollTimer) clearInterval(lockPollTimer)
  window.removeEventListener('keydown', handleKeyDown)
  window.removeEventListener('resize', handleResize)
  window.removeEventListener('focus', checkLockAndAutoSignOut)
  if (typeof window !== 'undefined') {
    document.body.style.overflow = ''
  }
})
</script>

<template>
  <div class="min-h-screen bg-[#F7F9FC] font-sans flex flex-col lg:flex-row min-w-0 max-w-full overflow-x-hidden relative">
    
    <!-- Backdrop Overlay on Mobile & Tablet (<1024px) -->
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
        class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-40 lg:hidden cursor-pointer"
        aria-label="Close Navigation Drawer"
      ></div>
    </Transition>

    <!-- Sidebar (Drawer on mobile/tablet, Collapsible Fixed Rail on desktop) -->
    <Sidebar />

    <!-- Main Content Area: smoothly adjusts left padding on desktop (lg:) -->
    <div 
      class="flex-1 flex flex-col min-w-0 max-w-full overflow-x-hidden transition-all duration-300 ease-in-out pl-0"
      :class="sidebarOpen ? 'lg:pl-[272px]' : 'lg:pl-20'"
    >
      
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
            Your semester academic submission is completed. Modifications are disabled in read-only mode.
          </span>
        </div>
        <router-link
          to="/instructor/semester-submission"
          class="underline font-bold hover:text-emerald-200 transition-colors shrink-0 text-left sm:text-right"
        >
          View Details
        </router-link>
      </div>

      <!-- Page Content with Responsive Padding -->
      <main class="flex-1 p-4 sm:p-6 lg:p-7 min-w-0 max-w-full overflow-x-hidden">
        <router-view :key="$route.path" />
      </main>

    </div>

    <!-- Reusable Semester Locked Modal -->
    <SemesterLockedNoticeModal />
  </div>
</template>

<style scoped>
</style>
