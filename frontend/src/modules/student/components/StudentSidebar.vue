<script setup lang="ts">
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../auth/store/authStore'
import type { StudentProfile } from '../types'

defineProps<{
  profile: StudentProfile
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const navigate = (path: string) => {
  router.push(path)
  emit('close')
}

const handleLogout = async () => {
  emit('close')
  await authStore.logout()
}

const isActive = (path: string) => route.path === path
</script>

<template>
  <!-- Full Screen Fixed Overlay Container with Transitions -->
  <Transition
    enter-active-class="transition-opacity duration-300 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition-opacity duration-200 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div v-if="isOpen" class="fixed inset-0 z-50 flex">
      <!-- Semi-transparent backdrop with click outside to dismiss -->
      <div 
        class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"
        @click="emit('close')"
        aria-hidden="true"
      ></div>

      <!-- Drawer Panel — floating, rounded, fits full height without scroll -->
      <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="-translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="-translate-x-full"
      >
        <div 
          v-if="isOpen"
          class="relative z-10 w-[270px] sm:w-[280px] max-w-[calc(100vw-24px)] bg-[#fdfdfd] m-2 sm:m-3 rounded-2xl flex flex-col border border-slate-100 text-slate-900 shadow-2xl overflow-hidden h-[calc(100vh-16px)] sm:h-[calc(100vh-24px)]"
        >

          <!-- Close Button with 44px touch target -->
          <button 
            @click="emit('close')" 
            class="absolute top-3 right-3 rounded-xl p-2.5 min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-400 hover:bg-slate-100 hover:text-slate-900 transition-colors z-10 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            aria-label="Close navigation drawer"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>

          <!-- ── User Profile Section (Left Aligned) ───────────────────────────────── -->
          <div class="px-5 pt-6 pb-4 flex flex-col items-start border-b border-[#E6EBF3]">
            <!-- Avatar -->
            <div class="w-13 h-13 sm:w-14 sm:h-14 rounded-full ring-2 ring-[#4F35F3]/20 overflow-hidden mb-2.5 flex-shrink-0 shadow-2xs">
              <img 
                :src="profile.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(profile.name)}&background=4f35f3&color=fff&size=128`"
                :alt="profile.name"
                class="w-full h-full object-cover"
                referrerpolicy="no-referrer"
              />
            </div>
            <!-- Name -->
            <h2 class="text-sm font-bold text-[#17243A] leading-tight truncate max-w-full">{{ profile.name }}</h2>
            <!-- Department -->
            <p class="text-[11px] text-[#71819B] mt-0.5 truncate max-w-full font-medium">{{ profile.department }}</p>
            <!-- Year Badge -->
            <div class="mt-2.5 bg-[#17243A] text-white px-3 py-1 rounded-md text-[10px] font-bold tracking-wide truncate max-w-full shadow-2xs">
              {{ (profile.academicYear || '').split(' ')[0] }} Year - {{ profile.semester }}
            </div>
          </div>

          <!-- ── Scrollable Nav Area ────────────────────────────────── -->
          <div class="flex-1 overflow-y-auto py-3 px-3 flex flex-col gap-4 scrollbar-hide">

            <!-- MAIN -->
            <div>
              <p class="text-[9px] font-bold text-[#71819B] uppercase tracking-widest mb-1.5 ml-2">Main</p>
              <nav class="space-y-1">
                <button @click="navigate('/student')" :class="['w-full min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-semibold transition-colors focus:outline-none cursor-pointer', isActive('/student') ? 'bg-[#EEF0FF] text-[#4F35F3] font-bold shadow-2xs' : 'text-[#71819B] hover:bg-[#EEF0FF] hover:text-[#4F35F3]']">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                  <span>Dashboard</span>
                </button>
                <button @click="navigate('/student/exams')" :class="['w-full min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-medium transition-colors focus:outline-none cursor-pointer', isActive('/student/exams') ? 'bg-[#EEF0FF] text-[#4F35F3] font-bold shadow-2xs' : 'text-[#71819B] hover:bg-[#EEF0FF] hover:text-[#4F35F3]']">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                  <span>My Exams</span>
                </button>
                <button @click="navigate('/student/results')" :class="['w-full min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-medium transition-colors focus:outline-none cursor-pointer', isActive('/student/results') ? 'bg-[#EEF0FF] text-[#4F35F3] font-bold shadow-2xs' : 'text-[#71819B] hover:bg-[#EEF0FF] hover:text-[#4F35F3]']">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                  <span>Results</span>
                </button>
                <button @click="navigate('/student/academic-calendar')" :class="['w-full min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-medium transition-colors focus:outline-none cursor-pointer', isActive('/student/academic-calendar') ? 'bg-[#EEF0FF] text-[#4F35F3] font-bold shadow-2xs' : 'text-[#71819B] hover:bg-[#EEF0FF] hover:text-[#4F35F3]']">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  <span>Academic Calendar</span>
                </button>
                <button @click="navigate('/student/profile')" :class="['w-full min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-medium transition-colors focus:outline-none cursor-pointer', isActive('/student/profile') ? 'bg-[#EEF0FF] text-[#4F35F3] font-bold shadow-2xs' : 'text-[#71819B] hover:bg-[#EEF0FF] hover:text-[#4F35F3]']">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                  <span>Profile</span>
                </button>
              </nav>
            </div>
            
            <div class="h-px bg-[#E6EBF3] w-full"></div>

            <!-- ACCOUNT -->
            <div>
              <p class="text-[9px] font-bold text-[#71819B] uppercase tracking-widest mb-1.5 ml-2">Account</p>
              <nav class="space-y-1">
                <button @click="navigate('/student/profile')" class="w-full min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-medium text-[#71819B] hover:bg-[#EEF0FF] hover:text-[#4F35F3] transition-colors focus:outline-none cursor-pointer">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                  <span>Settings</span>
                </button>
                <button @click="navigate('/student/profile')" class="w-full min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-medium text-[#71819B] hover:bg-[#EEF0FF] hover:text-[#4F35F3] transition-colors focus:outline-none cursor-pointer">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                  <span>Security</span>
                </button>
                <button @click="handleLogout" class="w-full min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-medium text-rose-600 hover:bg-rose-50 transition-colors focus:outline-none cursor-pointer">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                  <span>Sign Out</span>
                </button>
              </nav>
            </div>
            
            <div class="h-px bg-[#E6EBF3] w-full"></div>

            <!-- SUPPORT -->
            <div>
              <p class="text-[9px] font-bold text-[#71819B] uppercase tracking-widest mb-1.5 ml-2">Support</p>
              <nav class="space-y-1">
                <button @click="navigate('/student')" class="w-full min-h-[44px] flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-medium text-[#71819B] hover:bg-[#EEF0FF] hover:text-[#4F35F3] transition-colors focus:outline-none cursor-pointer">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                  <span>Help Center</span>
                </button>
              </nav>
            </div>
            
            <!-- ── App Promo ───────────────────────────────────── -->
            <div class="mt-auto p-3.5 bg-[#F6F8FC] border border-[#E6EBF3] rounded-xl shadow-2xs">
              <p class="text-[11px] font-black text-[#17243A] leading-tight">Exam Anywhere</p>
              <p class="text-[9px] text-[#71819B] mt-1 mb-2.5 leading-snug font-medium">Access exams on the go with our mobile app.</p>
              <div class="flex gap-2">
                <button class="flex-1 min-h-[36px] bg-[#17243A] text-white py-1.5 px-2 rounded-lg text-[9px] font-bold hover:bg-slate-800 transition-colors flex items-center justify-center gap-1 cursor-pointer">
                  Google Play
                </button>
                <button class="flex-1 min-h-[36px] bg-[#17243A] text-white py-1.5 px-2 rounded-lg text-[9px] font-bold hover:bg-slate-800 transition-colors flex items-center justify-center gap-1 cursor-pointer">
                  App Store
                </button>
              </div>
            </div>

          </div>

        </div>
      </Transition>
    </div>
  </Transition>
</template>

<style scoped>
/* Hide scrollbar for Chrome, Safari and Opera */
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
/* Hide scrollbar for IE, Edge and Firefox */
.scrollbar-hide {
  -ms-overflow-style: none;  /* IE and Edge */
  scrollbar-width: none;  /* Firefox */
}

a, button {
  -webkit-tap-highlight-color: transparent;
}
</style>
