<script setup lang="ts">
import { useAuthStore } from '../../modules/auth/store/authStore'
import { useSettingsStore } from '../../store/settingsStore'
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()
const route = useRoute()
const router = useRouter()

const isProfileDropdownOpen = ref(false)
const profileDropdownRef = ref<HTMLElement | null>(null)

const toggleProfileDropdown = () => {
  isProfileDropdownOpen.value = !isProfileDropdownOpen.value
}

const closeDropdown = () => {
  isProfileDropdownOpen.value = false
}

const navigateTo = (path: string) => {
  closeDropdown()
  router.push(path)
}

const handleLogout = async () => {
  closeDropdown()
  await authStore.logout()
}

const handleClickOutside = (e: MouseEvent) => {
  if (profileDropdownRef.value && !profileDropdownRef.value.contains(e.target as Node)) {
    isProfileDropdownOpen.value = false
  }
}

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    isProfileDropdownOpen.value = false
  }
}

onMounted(() => {
  authStore.fetchCurrentUser()
  settingsStore.fetchSettings()
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeyDown)
})

const profilePhotoUrl = computed(() => {
  const pic = authStore.user?.profile_picture_url || authStore.user?.profile_picture
  if (!pic) return 'https://i.pravatar.cc/150?u=admin123'
  if (pic.startsWith('http://') || pic.startsWith('https://') || pic.startsWith('data:')) {
    return pic
  }
  return `http://localhost:8000/storage/${pic}`
})

const toggleSidebar = inject<() => void>('toggleSidebar', () => {})
const sidebarOpen = inject<{ value: boolean }>('sidebarOpen', { value: true })

// Map routes to dynamic titles and descriptions
const pageInfo = computed(() => {
  const path = route.path

  if (path.includes('/dashboard')) return { title: 'Dashboard', desc: 'System-wide overview and key metrics.' }
  if (path.includes('/instructors')) return { title: 'Instructors', desc: 'Manage system instructors and their assignments.' }
  if (path.includes('/students')) return { title: 'Students', desc: 'Manage enrolled students and their profiles.' }
  if (path.includes('/courses')) return { title: 'Courses', desc: 'Manage academic courses and materials.' }
  if (path.includes('/exams')) return { title: 'Exams', desc: 'Manage system-wide examinations and schedules.' }
  if (path.includes('/departments')) return { title: 'Departments', desc: 'Manage departments and organizational structure.' }
  if (path.includes('/reports')) return { title: 'Reports', desc: 'View and generate comprehensive reports about the system.' }
  if (path.includes('/calendar') || path.includes('/academic-calendar')) return { title: 'Academic Calendar', desc: 'Manage academic terms, semesters, and important dates.' }
  if (path.includes('/settings')) return { title: 'Settings', desc: 'Global application settings and configuration.' }
  if (path.includes('/activity-logs')) return { title: 'Activity Logs', desc: 'Track and review all system activities and events.' }
  if (path.includes('/question-banks')) return { title: 'Question Banks', desc: 'Manage centralized pools of examination questions.' }
  if (path.includes('/users')) return { title: 'User Management', desc: 'Manage system users, roles, and permissions.' }

  return { title: 'Super Admin Dashboard', desc: 'System administration and management.' }
})
</script>

<template>
  <header class="h-16 sm:h-20 lg:h-24 bg-white/80 backdrop-blur-md border-b border-slate-100 flex items-center justify-between px-3 sm:px-6 lg:px-8 sticky top-0 z-30 min-w-0">
    
    <!-- Left Side: Title & Menu Toggle -->
    <div class="flex items-center gap-2.5 sm:gap-4 min-w-0">
      <!-- Hamburger button — always visible, toggles sidebar -->
      <button
        @click="toggleSidebar"
        class="p-2 sm:p-2.5 rounded-xl text-slate-500 hover:bg-slate-100 transition-colors border border-slate-100 shrink-0"
        :title="sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>
      
      <div class="flex flex-col min-w-0">
        <h1 class="text-base sm:text-lg lg:text-xl font-bold text-slate-800 truncate">{{ pageInfo.title }}</h1>
        <div class="hidden sm:block text-[11px] lg:text-[12px] font-medium text-slate-500 truncate max-w-xs md:max-w-md">
          {{ pageInfo.desc }}
        </div>
      </div>
    </div>

    <!-- Center: Semester/Year Badge (Visible on wider desktop to avoid center collision) -->
    <div class="absolute left-1/2 -translate-x-1/2 hidden xl:flex items-center pointer-events-none">
      <span class="text-[13px] font-bold text-[#5138ed] bg-indigo-50 px-5 py-1.5 rounded-full border border-indigo-100 shadow-sm whitespace-nowrap">
        {{ settingsStore.formattedAcademicTerm }}
      </span>
    </div>

    <!-- Right Side: User Profile & Dropdown (Notification icon removed as requested) -->
    <div class="flex items-center shrink-0">
      <div class="relative" ref="profileDropdownRef">
        <!-- Trigger Button -->
        <button
          type="button"
          @click="toggleProfileDropdown"
          class="flex items-center gap-2 sm:gap-3 p-1.5 sm:px-3 sm:py-2 rounded-2xl hover:bg-slate-100/80 transition-all border border-transparent hover:border-slate-200 cursor-pointer group focus:outline-none"
          :class="{ 'bg-slate-100/90 border-slate-200 shadow-sm': isProfileDropdownOpen }"
        >
          <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-slate-200 overflow-hidden border-2 border-transparent group-hover:border-indigo-600 transition-all flex items-center justify-center shadow-sm shrink-0">
            <img :src="profilePhotoUrl" alt="Profile" class="w-full h-full object-cover" />
          </div>
          <div class="hidden md:flex flex-col text-left">
            <span class="text-sm font-bold text-slate-800 group-hover:text-slate-900 leading-tight">
              {{ authStore.user?.name || 'Super Admin' }}
            </span>
            <span class="text-[11px] font-semibold text-indigo-600 leading-tight mt-0.5">Administrator</span>
          </div>
          <svg
            class="w-4 h-4 text-slate-400 group-hover:text-slate-600 transition-transform duration-200 ml-0.5 sm:ml-1"
            :class="{ 'rotate-180 text-indigo-600': isProfileDropdownOpen }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
          </svg>
        </button>

        <!-- Modern Super Admin Dropdown Menu -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="transform scale-95 opacity-0 -translate-y-1"
          enter-to-class="transform scale-100 opacity-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="transform scale-100 opacity-100 translate-y-0"
          leave-to-class="transform scale-95 opacity-0 -translate-y-1"
        >
          <div
            v-if="isProfileDropdownOpen"
            class="absolute right-0 top-full mt-2 w-[calc(100vw-2rem)] max-w-xs sm:w-72 bg-white/95 backdrop-blur-xl rounded-2xl border border-slate-100 shadow-[0_15px_50px_-10px_rgba(0,0,0,0.15)] py-2 z-50 select-none overflow-hidden"
          >
            <!-- User Info Header -->
            <div class="px-4 py-3 bg-gradient-to-br from-indigo-50/60 via-slate-50/50 to-white border-b border-slate-100 flex items-center gap-3">
              <div class="relative w-11 h-11 rounded-full overflow-hidden border-2 border-indigo-400/60 shadow-sm shrink-0">
                <img :src="profilePhotoUrl" alt="Profile" class="w-full h-full object-cover" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold text-slate-900 truncate">
                  {{ authStore.user?.name || 'Super Admin' }}
                </p>
                <p class="text-[11px] text-slate-500 truncate">
                  {{ authStore.user?.email || 'admin@wollo.edu.et' }}
                </p>
                <div class="mt-1 flex items-center gap-1.5">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-100/80 text-indigo-700 border border-indigo-200/60">
                    Super Admin
                  </span>
                  <span class="inline-flex items-center gap-1 text-[10px] text-slate-400 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                  </span>
                </div>
              </div>
            </div>

            <!-- Quick Action Links -->
            <div class="p-1.5 space-y-0.5">
              <!-- System Settings -->
              <button
                type="button"
                @click="navigateTo('/admin/settings')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-indigo-700 transition-colors group"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-slate-800 group-hover:text-indigo-700">System Settings</div>
                  <div class="text-[10px] text-slate-400 font-normal">Global system preferences</div>
                </div>
              </button>

              <!-- Activity Logs -->
              <button
                type="button"
                @click="navigateTo('/admin/activity-logs')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-indigo-700 transition-colors group"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-slate-800 group-hover:text-indigo-700">Activity Logs</div>
                  <div class="text-[10px] text-slate-400 font-normal">Audit trail & system events</div>
                </div>
              </button>

              <!-- User Management -->
              <button
                type="button"
                @click="navigateTo('/admin/instructors')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-indigo-700 transition-colors group"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-slate-800 group-hover:text-indigo-700">Faculty & Users</div>
                  <div class="text-[10px] text-slate-400 font-normal">Manage instructors & staff</div>
                </div>
              </button>
            </div>

            <!-- Divider -->
            <div class="h-px bg-slate-100 my-1 mx-2"></div>

            <!-- Logout Option -->
            <div class="p-1.5 pt-0.5">
              <button
                type="button"
                @click="handleLogout"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-rose-600 hover:bg-rose-50 transition-colors group"
              >
                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 group-hover:bg-rose-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-rose-600">Sign Out</div>
                  <div class="text-[10px] text-rose-400 font-normal">End admin session</div>
                </div>
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>
