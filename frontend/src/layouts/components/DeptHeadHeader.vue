<script setup lang="ts">
import { useAuthStore } from '../../modules/auth/store/authStore'
import { useSettingsStore } from '../../store/settingsStore'
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()
const route = useRoute()
const router = useRouter()

const toggleSidebar = inject<() => void>('toggleSidebar', () => {})
const sidebarOpen = inject<{ value: boolean }>('sidebarOpen', { value: true })

const isProfileDropdownOpen = ref(false)
const profileDropdownRef = ref<HTMLElement | null>(null)

const isNotificationsOpen = ref(false)
const notificationsRef = ref<HTMLElement | null>(null)

// Department Head notifications
const notifications = ref([
  {
    id: 1,
    title: '3 instructors submitted semester packages',
    subtitle: '2025/2026 First Semester ready for review',
    time: '15m ago',
    unread: true,
    link: '/dept-head/semester-submissions'
  },
  {
    id: 2,
    title: 'New student information submitted',
    subtitle: 'Instructor Abebe submitted student list for approval',
    time: '1h ago',
    unread: true,
    link: '/dept-head/semester-submissions'
  },
  {
    id: 3,
    title: 'Exam schedule published',
    subtitle: 'First semester department examination schedule',
    time: '2h ago',
    unread: true,
    link: '/dept-head/schedule'
  }
])

const unreadCount = computed(() => notifications.value.filter(n => n.unread).length)

const toggleProfileDropdown = () => {
  isProfileDropdownOpen.value = !isProfileDropdownOpen.value
  if (isProfileDropdownOpen.value) {
    isNotificationsOpen.value = false
  }
}

const toggleNotifications = () => {
  isNotificationsOpen.value = !isNotificationsOpen.value
  if (isNotificationsOpen.value) {
    isProfileDropdownOpen.value = false
  }
}

const closeDropdowns = () => {
  isProfileDropdownOpen.value = false
  isNotificationsOpen.value = false
}

const navigateTo = (path: string) => {
  closeDropdowns()
  router.push(path)
}

const handleLogout = async () => {
  closeDropdowns()
  await authStore.logout()
}

const handleClickOutside = (e: MouseEvent) => {
  const target = e.target as Node
  if (profileDropdownRef.value && !profileDropdownRef.value.contains(target)) {
    isProfileDropdownOpen.value = false
  }
  if (notificationsRef.value && !notificationsRef.value.contains(target)) {
    isNotificationsOpen.value = false
  }
}

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    closeDropdowns()
  }
}

onMounted(() => {
  authStore.fetchCurrentUser()
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeyDown)
})

// Map routes to dynamic titles and descriptions
const pageInfo = computed(() => {
  const path = route.path
  if (path.includes('/instructors')) return { title: 'Department Instructors', desc: 'Manage instructors within your department.' }
  if (path.includes('/students')) return { title: 'Department Students', desc: 'View all students enrolled in your department.' }
  if (path.includes('/courses')) return { title: 'Courses Management', desc: 'Manage courses and assign instructors.' }
  if (path.includes('/exams')) return { title: 'Exams Overview', desc: 'Monitor all department exams and pass rates.' }
  if (path.includes('/results')) return { title: 'Exam Results', desc: 'Review exam results and student performances.' }
  if (path.includes('/schedule')) return { title: 'Academic Calendar', desc: 'Department exam schedule and academic dates.' }
  if (path.includes('/reports')) return { title: 'Analytics & Reports', desc: 'Detailed analytics on department performance.' }
  if (path.includes('/settings')) return { title: 'Account Settings', desc: 'Manage your personal profile and preferences.' }
  if (path.includes('/semester-submissions')) return { title: 'Semester Submissions', desc: 'Review and approve semester exam packages.' }
  if (path.includes('/activity-logs')) return { title: 'Activity Logs', desc: 'Audit trail of department actions and submissions.' }
  return { title: 'Department Dashboard', desc: 'Department-wide overview and key metrics.' }
})

const deptInitials = computed(() => {
  const name = authStore.user?.name || 'DH'
  const parts = name.trim().split(' ')
  if (parts.length >= 2) return `${parts[0][0]}${parts[1][0]}`.toUpperCase()
  return name.slice(0, 2).toUpperCase()
})

const deptNameOrCode = computed(() => {
  return authStore.user?.department?.name || authStore.user?.department?.code || 'Department'
})
</script>

<template>
  <header class="h-16 sm:h-20 lg:h-24 bg-white/80 backdrop-blur-md border-b border-slate-100 flex items-center justify-between px-3 sm:px-6 lg:px-8 sticky top-0 z-30 min-w-0">
    
    <!-- Left Side: Title & Menu Toggle (Mobile & Desktop) -->
    <div class="flex items-center gap-2.5 sm:gap-4 min-w-0">
      <!-- Hamburger button — always responsive, toggles sidebar -->
      <button
        @click="toggleSidebar"
        type="button"
        class="p-2 sm:p-2.5 rounded-xl text-slate-500 hover:bg-slate-100 transition-colors border border-slate-100 shrink-0 min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer"
        :title="sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'"
        aria-label="Toggle Navigation Menu"
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

    <!-- Center: Semester/Year Badge (Hidden on mobile/tablet to avoid overflow) -->
    <div class="absolute left-1/2 -translate-x-1/2 hidden xl:flex items-center pointer-events-none">
      <span class="text-[13px] font-bold text-[#5138ed] bg-indigo-50 px-5 py-1.5 rounded-full border border-indigo-100 shadow-xs whitespace-nowrap">
        {{ settingsStore.formattedAcademicTerm }}
      </span>
    </div>

    <!-- Right Side: Notifications & User Profile -->
    <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
      
      <!-- Notifications Bell Button & Panel -->
      <div class="relative" ref="notificationsRef">
        <button
          type="button"
          @click="toggleNotifications"
          class="relative p-2 sm:p-2.5 rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors border border-transparent hover:border-slate-200 cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center"
          :class="{ 'bg-slate-100 border-slate-200 text-[#5138ed]': isNotificationsOpen }"
          title="Notifications"
          aria-label="View notifications"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          <span 
            v-if="unreadCount > 0"
            class="absolute top-1.5 right-1.5 flex items-center justify-center w-4 h-4 text-[10px] font-bold text-white bg-rose-500 rounded-full ring-2 ring-white shadow-xs"
          >
            {{ unreadCount }}
          </span>
        </button>

        <!-- Responsive Notification Panel -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="transform scale-95 opacity-0 -translate-y-1"
          enter-to-class="transform scale-100 opacity-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="transform scale-100 opacity-100 translate-y-0"
          leave-to-class="transform scale-95 opacity-0 -translate-y-1"
        >
          <div
            v-if="isNotificationsOpen"
            class="absolute right-0 top-full mt-2 w-[calc(100vw-1.5rem)] max-w-sm bg-white/95 backdrop-blur-xl rounded-2xl border border-slate-100 shadow-[0_15px_50px_-10px_rgba(0,0,0,0.15)] py-2 z-50 select-none overflow-hidden"
          >
            <!-- Panel Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50/60">
              <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-slate-800">Notifications</span>
                <span class="text-[10px] font-semibold bg-indigo-100 text-[#5138ed] px-2 py-0.5 rounded-full">
                  {{ unreadCount }} new
                </span>
              </div>
              <button
                type="button"
                @click="isNotificationsOpen = false"
                class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                title="Close notifications"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Notification Items -->
            <div class="max-h-72 overflow-y-auto divide-y divide-slate-50">
              <div 
                v-for="item in notifications" 
                :key="item.id"
                @click="navigateTo(item.link)"
                class="p-3.5 hover:bg-indigo-50/40 transition-colors cursor-pointer flex items-start gap-3"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 mt-0.5">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-[12px] font-semibold text-slate-800 leading-tight">
                    {{ item.title }}
                  </p>
                  <p class="text-[11px] text-slate-500 leading-normal mt-0.5">
                    {{ item.subtitle }}
                  </p>
                  <span class="text-[10px] text-slate-400 font-medium mt-1 block">
                    {{ item.time }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Panel Footer -->
            <div class="p-2 border-t border-slate-100 bg-slate-50/40 text-center">
              <button
                type="button"
                @click="navigateTo('/dept-head/semester-submissions')"
                class="w-full py-1.5 text-xs font-semibold text-[#5138ed] hover:text-indigo-700 transition-colors"
              >
                View all semester submissions →
              </button>
            </div>
          </div>
        </Transition>
      </div>

      <!-- User Profile & Dropdown -->
      <div class="relative" ref="profileDropdownRef">
        <!-- Trigger Button -->
        <button
          type="button"
          @click="toggleProfileDropdown"
          class="flex items-center gap-2 sm:gap-3 p-1 sm:px-3 sm:py-2 rounded-2xl hover:bg-slate-100/80 transition-all border border-transparent hover:border-slate-200 cursor-pointer group focus:outline-none min-h-[44px]"
          :class="{ 'bg-slate-100/90 border-slate-200 shadow-xs': isProfileDropdownOpen }"
          aria-label="User Profile Menu"
        >
          <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-gradient-to-tr from-indigo-600 to-indigo-500 text-white overflow-hidden border-2 border-transparent group-hover:border-[#5138ed] transition-all flex items-center justify-center font-bold text-xs sm:text-sm shadow-xs shrink-0">
            <span>{{ deptInitials }}</span>
          </div>
          <div class="hidden md:flex flex-col text-left min-w-0">
            <span class="text-sm font-bold text-slate-800 group-hover:text-slate-900 leading-tight truncate">
              {{ authStore.user?.name || 'Dr. Head' }}
            </span>
            <span class="text-[11px] font-semibold text-[#5138ed] leading-tight mt-0.5 truncate">
              {{ authStore.user?.department?.code ? `${authStore.user.department.code} Department Head` : 'Department Head' }}
            </span>
          </div>
          <svg
            class="w-4 h-4 text-slate-400 group-hover:text-slate-600 transition-transform duration-200 ml-0.5 sm:ml-1 shrink-0"
            :class="{ 'rotate-180 text-[#5138ed]': isProfileDropdownOpen }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
          </svg>
        </button>

        <!-- Modern Department Head Dropdown Menu -->
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
              <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-600 to-indigo-500 text-white flex items-center justify-center font-bold text-xs sm:text-sm shadow-xs shrink-0 border-2 border-indigo-200">
                {{ deptInitials }}
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold text-slate-900 truncate">
                  {{ authStore.user?.name || 'Dr. Head' }}
                </p>
                <p class="text-[11px] text-slate-500 truncate">
                  {{ authStore.user?.email || 'head@wollo.edu.et' }}
                </p>
                <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-100/80 text-indigo-700 border border-indigo-200/60 truncate max-w-[130px]">
                    {{ deptNameOrCode }}
                  </span>
                  <span class="inline-flex items-center gap-1 text-[10px] text-slate-400 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Online
                  </span>
                </div>
              </div>
            </div>

            <!-- Quick Action Links -->
            <div class="p-1.5 space-y-0.5">
              <!-- Department Settings -->
              <button
                type="button"
                @click="navigateTo('/dept-head/settings')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors group min-h-[44px]"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-bold text-slate-800 group-hover:text-[#5138ed] truncate">Account Settings</div>
                  <div class="text-[10px] text-slate-400 font-normal truncate">Department & personal config</div>
                </div>
              </button>

              <!-- Semester Submissions -->
              <button
                type="button"
                @click="navigateTo('/dept-head/semester-submissions')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors group min-h-[44px]"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-bold text-slate-800 group-hover:text-[#5138ed] truncate">Semester Submissions</div>
                  <div class="text-[10px] text-slate-400 font-normal truncate">Review exam submission packages</div>
                </div>
              </button>

              <!-- Activity Logs -->
              <button
                type="button"
                @click="navigateTo('/dept-head/activity-logs')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors group min-h-[44px]"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-bold text-slate-800 group-hover:text-[#5138ed] truncate">Activity Logs</div>
                  <div class="text-[10px] text-slate-400 font-normal truncate">Department action history</div>
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
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-rose-600 hover:bg-rose-50 transition-colors group min-h-[44px]"
              >
                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 group-hover:bg-rose-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-bold text-rose-600">Sign Out</div>
                  <div class="text-[10px] text-rose-400 font-normal">End department head session</div>
                </div>
              </button>
            </div>
          </div>
        </Transition>
      </div>

    </div>
  </header>
</template>
