<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../auth/store/authStore'
import type { StudentProfile, Announcement } from '../types'

const props = defineProps<{
  profile: StudentProfile
  announcements: Announcement[]
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

const handleOpenProfile = () => {
  isProfileDropdownOpen.value = false
  emit('open-profile')
  router.push('/student/profile')
}

const handleLogout = () => {
  isProfileDropdownOpen.value = false
  authStore.logout()
}

const handleClickOutside = (event: MouseEvent) => {
  if (profileDropdownRef.value && !profileDropdownRef.value.contains(event.target as Node)) {
    isProfileDropdownOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <header class="sticky top-0 z-40 w-full bg-white/95 text-gray-800 border-b border-slate-100 backdrop-blur-sm"
           style="box-shadow: 0 8px 30px rgba(15,23,42,.06);">
    <div class="mx-auto flex h-[72px] w-full items-center justify-between px-8">
      
      <!-- Left: Hamburger & Brand -->
      <div class="flex items-center gap-5">
        <!-- Hamburger Menu -->
        <button 
          @click="emit('toggle-sidebar')"
          class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-all duration-200 focus:outline-none focus:ring-0"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>

        <!-- Brand/Logo Section -->
        <div class="flex items-center gap-3">
          <img src="../../../assets/images/logo.png" alt="Wollo University Logo" class="w-9 h-9 object-contain rounded-full shadow-sm" />
          <div class="hidden sm:block">
            <div class="font-serif text-[13px] font-bold tracking-wide text-gray-800 uppercase leading-tight">Wollo University</div>
            <div class="text-[10px] font-medium text-slate-400">Online Examination System</div>
          </div>
        </div>
      </div>

      <!-- Center: Search Bar (Hidden on small screens) -->
      <div class="hidden md:flex flex-1 max-w-md mx-8 relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        <input 
          type="text" 
          v-model="searchQuery"
          placeholder="Search anything..." 
          class="block w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 placeholder:text-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition-all duration-200"
        />
      </div>

      <!-- Right: Action Items -->
      <div class="flex items-center gap-4 lg:gap-6">
        
        <div class="flex items-center gap-2">
          <!-- Notification Bell -->
          <button
            @click="emit('open-notifications')"
            class="relative rounded-full p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-gray-800"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            <span class="absolute top-1.5 right-1.5 flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500 text-[8px] font-bold text-white items-center justify-center">3</span>
            </span>
          </button>

          <!-- Messages (Mock) -->
          <button class="relative rounded-full p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-gray-800">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
          </button>
        </div>

        <!-- Divider -->
        <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

        <!-- Student Profile Container with Dropdown -->
        <div class="relative" ref="profileDropdownRef">
          <button
            @click.stop="toggleProfileDropdown"
            class="flex items-center gap-3 text-left focus:outline-none group rounded-xl p-1.5 hover:bg-slate-100/80 transition-colors border border-transparent hover:border-slate-200"
          >
            <img
              :src="profile.avatar"
              :alt="profile.name"
              class="h-9 w-9 rounded-full object-cover ring-2 ring-transparent group-hover:ring-indigo-500 transition-all"
              referrerpolicy="no-referrer"
            />
            <div class="hidden lg:block">
              <p class="text-[13px] font-bold text-slate-900 leading-tight">{{ profile.name }}</p>
              <p class="text-[10px] text-slate-400 leading-tight mt-0.5">{{ profile.department }}</p>
            </div>
            <svg
              class="hidden lg:block h-4 w-4 text-slate-400 transition-transform duration-200"
              :class="{ 'rotate-180': isProfileDropdownOpen }"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>

          <!-- Profile Dropdown Menu -->
          <div
            v-if="isProfileDropdownOpen"
            class="absolute right-0 mt-2 w-64 rounded-2xl bg-white border border-slate-100 shadow-xl py-2 z-50 animate-in fade-in zoom-in-95 duration-150"
          >
            <!-- User Info Header -->
            <div class="px-4 py-3 border-b border-slate-100 flex items-center gap-3">
              <img
                :src="profile.avatar"
                :alt="profile.name"
                class="h-10 w-10 rounded-full object-cover ring-2 ring-indigo-100 flex-shrink-0"
              />
              <div class="min-w-0">
                <p class="text-xs font-bold text-slate-900 truncate">{{ profile.name }}</p>
                <p class="text-[10px] text-slate-400 truncate">{{ profile.email }}</p>
                <span class="inline-block mt-1 text-[9px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">
                  {{ profile.department }}
                </span>
              </div>
            </div>

            <!-- Dropdown Options -->
            <div class="p-1 space-y-0.5">
              <button
                @click="handleOpenProfile"
                class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors text-left"
              >
                <div class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                </div>
                <div>
                  <p class="font-bold text-slate-900">Profile</p>
                  <p class="text-[10px] text-slate-400 font-normal">View & edit academic profile</p>
                </div>
              </button>

              <button
                @click="handleLogout"
                class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-rose-600 hover:bg-rose-50 transition-colors text-left"
              >
                <div class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                  </svg>
                </div>
                <div>
                  <p class="font-bold text-rose-600">Logout</p>
                  <p class="text-[10px] text-rose-400 font-normal">Sign out of your account</p>
                </div>
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </header>
</template>
