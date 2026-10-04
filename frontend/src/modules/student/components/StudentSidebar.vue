<script setup lang="ts">
import { useRouter, useRoute } from 'vue-router'
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

const navigate = (path: string) => {
  router.push(path)
  emit('close')
}

const isActive = (path: string) => route.path === path
</script>

<template>
  <!-- Full Screen Fixed Overlay Container -->
  <div v-if="isOpen" class="fixed inset-0 z-50 flex">
    <!-- Semi-transparent backdrop without blur -->
    <div 
      class="fixed inset-0 bg-slate-900/20"
      @click="emit('close')"
    ></div>

    <!-- Drawer Panel — floating, rounded, fits full height without scroll -->
    <div class="relative z-10 w-[240px] bg-[#fdfdfd] m-3 rounded-2xl flex flex-col border border-slate-100 text-slate-900 shadow-2xl overflow-hidden h-[calc(100vh-24px)]">

      <!-- Close Button -->
      <button 
        @click="emit('close')" 
        class="absolute top-4 right-4 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-900 transition-colors z-10"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>

      <!-- ── User Profile Section (Left Aligned) ───────────────────────────────── -->
      <div class="px-5 pt-6 pb-5 flex flex-col items-start border-b border-slate-100">
        <!-- Avatar -->
        <div class="w-14 h-14 rounded-full ring-2 ring-indigo-100 overflow-hidden mb-3 flex-shrink-0">
          <img 
            :src="profile.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(profile.name)}&background=6366f1&color=fff&size=128`"
            :alt="profile.name"
            class="w-full h-full object-cover"
            referrerpolicy="no-referrer"
          />
        </div>
        <!-- Name -->
        <h2 class="text-sm font-bold text-slate-900 leading-tight">{{ profile.name }}</h2>
        <!-- Department -->
        <p class="text-[11px] text-slate-500 mt-1">{{ profile.department }}</p>
        <!-- Year Badge -->
        <div class="mt-3 bg-gray-500 text-white px-3 py-1.5 rounded-md text-[10px] font-bold tracking-wide">
          {{ profile.academicYear.split(' ')[0] }} Year - {{ profile.semester }}
        </div>
      </div>

      <!-- ── Scrollable Nav Area ────────────────────────────────── -->
      <div class="flex-1 overflow-y-auto py-4 px-4 flex flex-col gap-5 scrollbar-hide">

        <!-- MAIN -->
        <div>
          <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-2">Main</p>
          <nav class="space-y-0.5">
            <button @click="navigate('/student')" :class="['w-full flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-semibold transition-colors focus:outline-none', isActive('/student') ? 'bg-indigo-50 text-indigo-600' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600']">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
              <span>Dashboard</span>
            </button>
            <button @click="navigate('/student/exams')" :class="['w-full flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-colors focus:outline-none', isActive('/student/exams') ? 'bg-indigo-50 text-indigo-600' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600']">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
              <span>My Exams</span>
            </button>
            <button @click="navigate('/student/results')" :class="['w-full flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-colors focus:outline-none', isActive('/student/results') ? 'bg-indigo-50 text-indigo-600' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600']">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
              <span>Results</span>
            </button>
            <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition-colors focus:outline-none focus:bg-indigo-50 focus:text-indigo-600">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
              <span>Transcript</span>
            </a>
            <button @click="navigate('/student/academic-calendar')" :class="['w-full flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-colors focus:outline-none', isActive('/student/academic-calendar') ? 'bg-indigo-50 text-indigo-600' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600']">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              <span>Academic Calendar</span>
            </button>
            <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition-colors focus:outline-none focus:bg-indigo-50 focus:text-indigo-600">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>Schedule</span>
            </a>
            <button @click="navigate('/student/profile')" :class="['w-full flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-colors focus:outline-none', isActive('/student/profile') ? 'bg-indigo-50 text-indigo-600 font-semibold' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600']">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              <span>Profile</span>
            </button>
          </nav>
        </div>
        
        <div class="h-px bg-slate-100 w-full"></div>

        <!-- ACCOUNT -->
        <div>
          <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-2">Account</p>
          <nav class="space-y-0.5">
            <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition-colors focus:outline-none focus:bg-indigo-50 focus:text-indigo-600">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
              <span>Settings</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition-colors focus:outline-none focus:bg-indigo-50 focus:text-indigo-600">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              <span>Security</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition-colors focus:outline-none focus:bg-indigo-50 focus:text-indigo-600">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
              <span>Logout</span>
            </a>
          </nav>
        </div>
        
        <div class="h-px bg-slate-100 w-full"></div>

        <!-- SUPPORT -->
        <div>
          <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-2">Support</p>
          <nav class="space-y-0.5">
            <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition-colors focus:outline-none focus:bg-indigo-50 focus:text-indigo-600">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
              <span>Help Center</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition-colors focus:outline-none focus:bg-indigo-50 focus:text-indigo-600">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
              <span>Contact Support</span>
            </a>
          </nav>
        </div>
        
        <!-- ── App Promo ───────────────────────────────────── -->
        <div class="mt-2 p-4 bg-slate-50 border border-slate-100 rounded-xl">
          <p class="text-[11px] font-bold text-slate-900 leading-tight">Exam Anywhere, Anytime</p>
          <p class="text-[9px] text-slate-500 mt-1 mb-3 leading-snug">Access your exams on the go with our mobile app.</p>
          <div class="flex gap-2">
            <button class="flex-1 bg-black text-white py-1.5 rounded text-[9px] font-bold border border-slate-700 hover:bg-slate-800 transition-colors flex items-center justify-center gap-1">
              <img src="https://upload.wikimedia.org/wikipedia/commons/d/d0/Google_Play_Arrow_logo.svg" class="w-2.5 h-2.5" alt="Play"/> Google Play
            </button>
            <button class="flex-1 bg-black text-white py-1.5 rounded text-[9px] font-bold border border-slate-700 hover:bg-slate-800 transition-colors flex items-center justify-center gap-1">
              <svg class="w-2.5 h-2.5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.04 2.34-.85 3.74-.78 1.78.11 3.26.85 4.12 2.2-3.41 2.05-2.82 6.64.44 8.01-1 2.21-2.4 4.54-3.38 5.48M12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.4-2.08 4.41-3.74 4.25z"/></svg> App Store
            </button>
          </div>
        </div>

      </div>

    </div>
  </div>
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
