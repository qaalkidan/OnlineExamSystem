<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../auth/store/authStore'
import type { StudentProfile, Announcement } from '../types'

const props = defineProps<{
  profile: StudentProfile
  announcements?: Announcement[]
}>()

const emit = defineEmits<{
  (e: 'open-profile'): void
  (e: 'open-notifications'): void
  (e: 'toggle-sidebar'): void
}>()

const router = useRouter()
const authStore = useAuthStore()
const searchQuery = ref('')
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
  if (path === '/student/profile') {
    emit('open-profile')
  }
  router.push(path)
}

const handleLogout = async () => {
  closeDropdown()
  await authStore.logout()
}

const handleClickOutside = (event: MouseEvent) => {
  if (profileDropdownRef.value && !profileDropdownRef.value.contains(event.target as Node)) {
    isProfileDropdownOpen.value = false
  }
}

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    isProfileDropdownOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
  <header class="sticky top-0 z-40 w-full bg-white/95 text-gray-800 border-b border-slate-100 backdrop-blur-sm"
           style="box-shadow: 0 8px 30px rgba(15,23,42,.06);">
    <div class="mx-auto flex h-[68px] sm:h-[72px] w-full items-center justify-between px-3 sm:px-6 lg:px-8">
      
      <!-- Left: Hamburger & Brand -->
      <div class="flex items-center gap-2.5 sm:gap-4 lg:gap-5 min-w-0">
        <!-- Hamburger Menu -->
        <button 
          @click="emit('toggle-sidebar')"
          class="flex h-10 w-10 sm:h-9 sm:w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 shrink-0"
          aria-label="Toggle navigation menu"
          title="Toggle Menu"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>

        <!-- Brand/Logo Section -->
        <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
          <img src="../../../assets/images/logo.png" alt="Wollo University Logo" class="w-8 h-8 sm:w-9 sm:h-9 object-contain rounded-full shadow-xs shrink-0" />
          <div class="min-w-0">
            <div class="font-serif text-xs sm:text-[13px] font-bold tracking-wide text-gray-800 uppercase leading-tight truncate">Wollo University</div>
            <div class="text-[9px] sm:text-[10px] font-medium text-slate-400 hidden xs:block truncate">Online Examination System</div>
          </div>
        </div>
      </div>

      <!-- Center: Search Bar (Hidden on small screens) -->
      <div class="hidden md:flex flex-1 max-w-xs lg:max-w-md mx-3 lg:mx-8 relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        <input 
          type="text" 
          v-model="searchQuery"
          placeholder="Search courses, exams, results..." 
          class="block w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 placeholder:text-slate-400 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition-all duration-200"
        />
      </div>

      <!-- Right: Academic Badges, Notifications & Profile -->
      <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
        
        <!-- Desktop Academic Year & Semester Badge -->
        <div class="hidden xl:flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-50 text-slate-700 text-xs font-semibold border border-slate-200/80 shadow-2xs">
          <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
          <span>{{ profile.academicYear || '2025/2026' }}</span>
          <span class="text-slate-300">•</span>
          <span class="text-indigo-600 font-bold">{{ profile.semester || 'First Semester' }}</span>
        </div>

        <!-- Tablet Compact Academic Badge -->
        <div class="hidden md:flex xl:hidden items-center px-2.5 py-1 rounded-full bg-slate-50 text-slate-600 text-[11px] font-semibold border border-slate-200">
          <span>{{ (profile.academicYear || '2025/2026').split(' ')[0] }} • {{ (profile.semester || 'First').split(' ')[0] }}</span>
        </div>

        <!-- Notification Bell Icon Button -->
        <button
          @click="emit('open-notifications')"
          class="relative flex h-10 w-10 sm:h-9 sm:w-9 items-center justify-center rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          aria-label="View notifications"
          title="Notifications"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span v-if="(announcements || []).length > 0" class="absolute top-2 right-2 flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-600"></span>
          </span>
        </button>

        <!-- Student Profile Container with Dropdown -->
        <div class="relative" ref="profileDropdownRef">
          <button
            @click.stop="toggleProfileDropdown"
            class="flex items-center gap-2 sm:gap-3 text-left focus:outline-none group rounded-2xl p-1 sm:px-3 sm:py-1.5 hover:bg-slate-100/80 transition-all border border-transparent hover:border-slate-200 min-h-[44px]"
            :class="{ 'bg-slate-100/90 border-slate-200 shadow-xs': isProfileDropdownOpen }"
            aria-label="Student profile menu"
          >
            <img
              :src="profile.avatar"
              :alt="profile.name"
              class="h-8 w-8 sm:h-9 sm:w-9 rounded-full object-cover ring-2 ring-transparent group-hover:ring-indigo-500 transition-all shadow-xs shrink-0"
              referrerpolicy="no-referrer"
            />
            <div class="hidden lg:block max-w-[140px] truncate">
              <p class="text-[13px] font-bold text-slate-900 leading-tight truncate">{{ profile.name }}</p>
              <p class="text-[10px] text-slate-400 leading-tight mt-0.5 truncate">{{ profile.department }}</p>
            </div>
            <svg
              class="hidden lg:block h-4 w-4 text-slate-400 group-hover:text-slate-600 transition-transform duration-200 shrink-0"
              :class="{ 'rotate-180 text-indigo-600': isProfileDropdownOpen }"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>

          <!-- Modern Student Profile Dropdown Menu -->
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
              class="absolute right-0 top-full mt-2 w-72 max-w-[calc(100vw-24px)] bg-white/95 backdrop-blur-xl rounded-2xl border border-slate-100 shadow-[0_15px_50px_-10px_rgba(0,0,0,0.15)] py-2 z-50 select-none overflow-hidden"
            >
              <!-- User Info Header -->
              <div class="px-4 py-3 bg-gradient-to-br from-indigo-50/60 via-slate-50/50 to-white border-b border-slate-100 flex items-center gap-3">
                <img
                  :src="profile.avatar"
                  :alt="profile.name"
                  class="h-11 w-11 rounded-full object-cover ring-2 ring-indigo-200 shadow-sm shrink-0"
                  referrerpolicy="no-referrer"
                />
                <div class="min-w-0 flex-1">
                  <p class="text-[13px] font-bold text-slate-900 truncate">{{ profile.name }}</p>
                  <p class="text-[11px] text-slate-500 truncate">{{ profile.email || profile.id }}</p>
                  <div class="mt-1 flex items-center gap-1.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-100/80 text-indigo-700 border border-indigo-200/60 truncate max-w-[140px]">
                      {{ profile.department }}
                    </span>
                    <span class="inline-flex items-center gap-1 text-[10px] text-slate-400 font-medium">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Student
                    </span>
                  </div>
                </div>
              </div>

              <!-- Quick Action Links -->
              <div class="p-1.5 space-y-0.5">
                <!-- My Profile -->
                <button
                  type="button"
                  @click="navigateTo('/student/profile')"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-indigo-600 transition-colors group"
                >
                  <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-slate-800 group-hover:text-indigo-600">My Profile</div>
                    <div class="text-[10px] text-slate-400 font-normal">View & edit academic details</div>
                  </div>
                </button>

                <!-- My Exams -->
                <button
                  type="button"
                  @click="navigateTo('/student/exams')"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-indigo-600 transition-colors group"
                >
                  <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-slate-800 group-hover:text-indigo-600">My Exams</div>
                    <div class="text-[10px] text-slate-400 font-normal">Active & scheduled examinations</div>
                  </div>
                </button>

                <!-- Exam Results -->
                <button
                  type="button"
                  @click="navigateTo('/student/results')"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-indigo-600 transition-colors group"
                >
                  <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-slate-800 group-hover:text-indigo-600">Exam Results</div>
                    <div class="text-[10px] text-slate-400 font-normal">Scores, performance & transcripts</div>
                  </div>
                </button>

                <!-- Academic Calendar -->
                <button
                  type="button"
                  @click="navigateTo('/student/academic-calendar')"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-indigo-600 transition-colors group"
                >
                  <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-slate-800 group-hover:text-indigo-600">Academic Calendar</div>
                    <div class="text-[10px] text-slate-400 font-normal">Semester events & milestones</div>
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
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-rose-600">Sign Out</div>
                    <div class="text-[10px] text-rose-400 font-normal">Sign out of student portal</div>
                  </div>
                </button>
              </div>
            </div>
          </Transition>
        </div>

      </div>
    </div>
  </header>
</template>
