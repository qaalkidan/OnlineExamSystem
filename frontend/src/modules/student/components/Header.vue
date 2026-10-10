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
  <header class="sticky top-0 z-40 w-full bg-white border-b border-[#E6EBF3] shadow-2xs backdrop-blur-md">
    <div class="mx-auto flex h-[68px] sm:h-[72px] w-full items-center justify-between px-3.5 sm:px-6 lg:px-8 max-w-[1600px]">
      
      <!-- Left: Hamburger & Brand -->
      <div class="flex items-center gap-2.5 sm:gap-4 lg:gap-5 min-w-0">
        <!-- Hamburger Menu -->
        <button 
          @click="emit('toggle-sidebar')"
          class="flex h-10 w-10 sm:h-9 sm:w-9 items-center justify-center rounded-xl border border-[#E6EBF3] text-[#71819B] hover:text-[#17243A] hover:bg-[#F6F8FC] transition-all focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 shrink-0 cursor-pointer shadow-2xs"
          aria-label="Toggle navigation menu"
          title="Toggle Menu"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
        </button>

        <!-- Brand/Logo Section -->
        <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
          <img src="../../../assets/images/logo.png" alt="Wollo University Logo" class="w-8 h-8 sm:w-9 sm:h-9 object-contain rounded-full shadow-2xs shrink-0" />
          <div class="min-w-0">
            <div class="font-serif text-xs sm:text-[13px] font-black tracking-wider text-[#17243A] uppercase leading-tight truncate">Wollo University</div>
            <div class="text-[9px] sm:text-[10px] font-semibold text-[#71819B] tracking-wide hidden xs:block truncate">Online Examination System</div>
          </div>
        </div>
      </div>

      <!-- Center: Search Bar (Hidden on small screens) -->
      <div class="hidden md:flex flex-1 max-w-xs lg:max-w-md mx-3 lg:mx-8 relative">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
          <svg class="h-4 w-4 text-[#71819B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
        </div>
        <input 
          type="text" 
          v-model="searchQuery"
          placeholder="Search courses, exams, results..." 
          class="block w-full pl-10 pr-4 py-2.5 rounded-xl bg-[#F6F8FC] border border-[#E6EBF3] text-[#17243A] placeholder-[#71819B] text-xs sm:text-[13px] focus:outline-none focus:ring-1 focus:ring-[#4F35F3] focus:border-[#4F35F3] transition-all shadow-2xs"
        />
      </div>

      <!-- Right: Academic Badges, Notifications & Profile -->
      <div class="flex items-center gap-2 sm:gap-3 shrink-0">
        
        <!-- Desktop Academic Year & Semester Badge -->
        <div class="hidden xl:flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#EEF0FF]/80 text-[#17243A] text-xs font-bold border border-[#4F35F3]/20 shadow-2xs">
          <span class="w-2 h-2 rounded-full bg-[#4F35F3] shrink-0"></span>
          <span>{{ profile.academicYear || '2025/2026 Academic Year' }}</span>
          <span class="text-slate-300">•</span>
          <span class="text-[#4F35F3] font-black">{{ profile.semester || 'First Semester' }}</span>
        </div>

        <!-- Tablet Compact Academic Badge -->
        <div class="hidden md:flex xl:hidden items-center px-3 py-1.5 rounded-full bg-[#EEF0FF]/80 text-[#17243A] text-[11px] font-bold border border-[#4F35F3]/20">
          <span>{{ (profile.academicYear || '2025/2026').split(' ')[0] }} • {{ (profile.semester || 'First').split(' ')[0] }}</span>
        </div>

        <!-- Notification Bell Icon Button -->
        <button
          @click="emit('open-notifications')"
          class="relative flex h-10 w-10 sm:h-9 sm:w-9 items-center justify-center rounded-xl border border-[#E6EBF3] text-[#71819B] hover:text-[#17243A] hover:bg-[#F6F8FC] transition-colors focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 cursor-pointer shadow-2xs"
          aria-label="View notifications"
          title="Notifications"
        >
          <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span v-if="(announcements || []).length > 0" class="absolute top-2 right-2 flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#4F35F3] opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-[#4F35F3]"></span>
          </span>
        </button>

        <!-- Student Profile Container with Dropdown -->
        <div class="relative" ref="profileDropdownRef">
          <button
            @click.stop="toggleProfileDropdown"
            class="flex items-center gap-2 sm:gap-2.5 text-left focus:outline-none group rounded-2xl p-1 sm:px-2.5 sm:py-1.5 hover:bg-[#F6F8FC] transition-all border border-transparent hover:border-[#E6EBF3] min-h-[44px] cursor-pointer"
            :class="{ 'bg-[#F6F8FC] border-[#E6EBF3] shadow-2xs': isProfileDropdownOpen }"
            aria-label="Student profile menu"
          >
            <img
              :src="profile.avatar"
              :alt="profile.name"
              class="h-8 w-8 sm:h-9 sm:w-9 rounded-full object-cover ring-2 ring-transparent group-hover:ring-[#4F35F3]/30 transition-all shadow-2xs shrink-0"
              referrerpolicy="no-referrer"
            />
            <div class="hidden lg:block max-w-[140px] truncate">
              <p class="text-[12px] font-bold text-[#17243A] leading-tight truncate">{{ profile.name }}</p>
              <p class="text-[10px] text-[#71819B] font-medium leading-tight mt-0.5 truncate">{{ profile.department }}</p>
            </div>
            <svg
              class="hidden lg:block h-3.5 w-3.5 text-[#71819B] group-hover:text-[#17243A] transition-transform duration-200 shrink-0"
              :class="{ 'rotate-180 text-[#4F35F3]': isProfileDropdownOpen }"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
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
              class="absolute right-0 top-full mt-2 w-72 max-w-[calc(100vw-24px)] bg-white rounded-2xl border border-[#E6EBF3] shadow-xl py-2 z-50 select-none overflow-hidden"
            >
              <!-- User Info Header -->
              <div class="px-4 py-3 bg-gradient-to-br from-[#EEF0FF]/70 via-slate-50/40 to-white border-b border-[#E6EBF3] flex items-center gap-3">
                <img
                  :src="profile.avatar"
                  :alt="profile.name"
                  class="h-11 w-11 rounded-full object-cover ring-2 ring-[#4F35F3]/30 shadow-xs shrink-0"
                  referrerpolicy="no-referrer"
                />
                <div class="min-w-0 flex-1">
                  <p class="text-[13px] font-bold text-[#17243A] truncate">{{ profile.name }}</p>
                  <p class="text-[11px] text-[#71819B] truncate">{{ profile.email || profile.id }}</p>
                  <div class="mt-1 flex items-center gap-1.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#EEF0FF] text-[#4F35F3] border border-[#4F35F3]/20 truncate max-w-[140px]">
                      {{ profile.department }}
                    </span>
                    <span class="inline-flex items-center gap-1 text-[10px] text-[#71819B] font-medium">
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
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-[#EEF0FF] hover:text-[#4F35F3] transition-colors group cursor-pointer"
                >
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-[#17243A] group-hover:text-[#4F35F3]">My Profile</div>
                    <div class="text-[10px] text-[#71819B] font-normal">View & edit academic details</div>
                  </div>
                </button>

                <!-- My Exams -->
                <button
                  type="button"
                  @click="navigateTo('/student/exams')"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-[#EEF0FF] hover:text-[#4F35F3] transition-colors group cursor-pointer"
                >
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-[#17243A] group-hover:text-[#4F35F3]">My Exams</div>
                    <div class="text-[10px] text-[#71819B] font-normal">Active & scheduled examinations</div>
                  </div>
                </button>

                <!-- Exam Results -->
                <button
                  type="button"
                  @click="navigateTo('/student/results')"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-[#EEF0FF] hover:text-[#4F35F3] transition-colors group cursor-pointer"
                >
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-[#17243A] group-hover:text-[#4F35F3]">Exam Results</div>
                    <div class="text-[10px] text-[#71819B] font-normal">Scores, performance & transcripts</div>
                  </div>
                </button>

                <!-- Academic Calendar -->
                <button
                  type="button"
                  @click="navigateTo('/student/academic-calendar')"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-[#EEF0FF] hover:text-[#4F35F3] transition-colors group cursor-pointer"
                >
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-[#17243A] group-hover:text-[#4F35F3]">Academic Calendar</div>
                    <div class="text-[10px] text-[#71819B] font-normal">Semester events & milestones</div>
                  </div>
                </button>
              </div>

              <!-- Divider -->
              <div class="h-px bg-[#E6EBF3] my-1 mx-2"></div>

              <!-- Logout Option -->
              <div class="p-1.5 pt-0.5">
                <button
                  type="button"
                  @click="handleLogout"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-rose-600 hover:bg-rose-50 transition-colors group cursor-pointer"
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
