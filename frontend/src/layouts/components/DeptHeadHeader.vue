<script setup lang="ts">
import { useAuthStore } from '../../modules/auth/store/authStore'
import { useSettingsStore } from '../../store/settingsStore'
import { ref, computed, onMounted, onUnmounted } from 'vue'
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
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeyDown)
})

// Map routes to dynamic titles
const pageTitle = computed(() => {
  if (route.path.includes('/instructors')) return 'Department Instructors'
  if (route.path.includes('/students')) return 'Department Students'
  if (route.path.includes('/courses')) return 'Courses Management'
  if (route.path.includes('/exams')) return 'Exams Overview'
  if (route.path.includes('/reports')) return 'Analytics & Reports'
  if (route.path.includes('/settings')) return 'Account Settings'
  if (route.path.includes('/semester-submissions')) return 'Semester Submissions'
  if (route.path.includes('/activity-logs')) return 'Department Activity Logs'
  return 'Department Dashboard'
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
  <header class="h-24 bg-white/80 backdrop-blur-md border-b border-slate-100 flex items-center justify-between px-8 sticky top-0 z-30">
    
    <!-- Left Side: Title & Menu Toggle (Mobile) -->
    <div class="flex items-center gap-4">
      <button class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden transition-colors">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>
      <div class="hidden lg:flex items-center justify-center w-10 h-10 rounded-xl bg-slate-50 text-slate-500 border border-slate-100 mr-2">
         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"></path></svg>
      </div>
      
      <div class="flex flex-col">
        <h1 class="text-xl font-bold text-slate-800">{{ pageTitle }}</h1>
        <div v-if="route.path.includes('/dashboard')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          Department-wide overview and key metrics.
        </div>
        <div v-else-if="route.path.includes('/instructors')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          Manage instructors within your department.
        </div>
        <div v-else-if="route.path.includes('/students')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          View all students enrolled in your department.
        </div>
        <div v-else-if="route.path.includes('/courses')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          Manage courses and assign instructors.
        </div>
        <div v-else-if="route.path.includes('/exams')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          Monitor all department exams and pass rates.
        </div>
        <div v-else-if="route.path.includes('/reports')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          Detailed analytics on department performance.
        </div>
        <div v-else-if="route.path.includes('/settings')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          Manage your personal profile and preferences.
        </div>
        <div v-else-if="route.path.includes('/semester-submissions')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          Review and approve semester exam packages.
        </div>
        <div v-else-if="route.path.includes('/activity-logs')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          Audit trail of department actions and submissions.
        </div>
      </div>
    </div>

    <!-- Center: Semester/Year Badge -->
    <div class="absolute left-1/2 -translate-x-1/2 hidden md:flex items-center">
      <span class="text-[13px] font-bold text-[#5138ed] bg-indigo-50 px-5 py-1.5 rounded-full border border-indigo-100 shadow-sm whitespace-nowrap">
        {{ settingsStore.formattedAcademicTerm }}
      </span>
    </div>

    <!-- Right Side: User Profile & Dropdown (Notification icon removed as requested) -->
    <div class="flex items-center">
      <div class="relative" ref="profileDropdownRef">
        <!-- Trigger Button -->
        <button
          type="button"
          @click="toggleProfileDropdown"
          class="flex items-center gap-3 px-3 py-2 rounded-2xl hover:bg-slate-100/80 transition-all border border-transparent hover:border-slate-200 cursor-pointer group focus:outline-none"
          :class="{ 'bg-slate-100/90 border-slate-200 shadow-sm': isProfileDropdownOpen }"
        >
          <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-600 to-indigo-500 text-white overflow-hidden border-2 border-transparent group-hover:border-[#5138ed] transition-all flex items-center justify-center font-bold text-sm shadow-sm">
            <span>{{ deptInitials }}</span>
          </div>
          <div class="hidden md:flex flex-col text-left">
            <span class="text-sm font-bold text-slate-800 group-hover:text-slate-900 leading-tight">
              {{ authStore.user?.name || 'Dr. Head' }}
            </span>
            <span class="text-[11px] font-semibold text-[#5138ed] leading-tight mt-0.5">
              {{ authStore.user?.department?.code ? `${authStore.user.department.code} Department Head` : 'Department Head' }}
            </span>
          </div>
          <svg
            class="w-4 h-4 text-slate-400 group-hover:text-slate-600 transition-transform duration-200 ml-1"
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
            class="absolute right-0 top-full mt-2 w-72 bg-white/95 backdrop-blur-xl rounded-2xl border border-slate-100 shadow-[0_15px_50px_-10px_rgba(0,0,0,0.15)] py-2 z-50 select-none overflow-hidden"
          >
            <!-- User Info Header -->
            <div class="px-4 py-3 bg-gradient-to-br from-indigo-50/60 via-slate-50/50 to-white border-b border-slate-100 flex items-center gap-3">
              <div class="w-11 h-11 rounded-full bg-gradient-to-tr from-indigo-600 to-indigo-500 text-white flex items-center justify-center font-bold text-sm shadow-sm shrink-0 border-2 border-indigo-200">
                {{ deptInitials }}
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold text-slate-900 truncate">
                  {{ authStore.user?.name || 'Dr. Head' }}
                </p>
                <p class="text-[11px] text-slate-500 truncate">
                  {{ authStore.user?.email || 'head@wollo.edu.et' }}
                </p>
                <div class="mt-1 flex items-center gap-1.5">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-100/80 text-indigo-700 border border-indigo-200/60">
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
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors group"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-slate-800 group-hover:text-[#5138ed]">Account Settings</div>
                  <div class="text-[10px] text-slate-400 font-normal">Department & personal config</div>
                </div>
              </button>

              <!-- Semester Submissions -->
              <button
                type="button"
                @click="navigateTo('/dept-head/semester-submissions')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors group"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-slate-800 group-hover:text-[#5138ed]">Semester Submissions</div>
                  <div class="text-[10px] text-slate-400 font-normal">Review exam submission packages</div>
                </div>
              </button>

              <!-- Activity Logs -->
              <button
                type="button"
                @click="navigateTo('/dept-head/activity-logs')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors group"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-slate-800 group-hover:text-[#5138ed]">Activity Logs</div>
                  <div class="text-[10px] text-slate-400 font-normal">Department action history</div>
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
