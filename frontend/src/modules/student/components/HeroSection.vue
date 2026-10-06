<script setup lang="ts">
import type { StudentProfile } from '../types'

defineProps<{
  profile: StudentProfile
  stats?: {
    examsCompleted: number
    upcomingExams: number
  }
  title?: string
  subtitle?: string
  description?: string
}>()
</script>

<template>
  <!-- Full Width Hero Container flush under Header -->
  <div class="relative w-full min-h-[350px] sm:min-h-[400px] lg:min-h-[460px] bg-white overflow-hidden flex items-center">
    
    <!-- Background Image: hero-bg1.png (Graduation cap illustration on the right) -->
    <div 
      class="absolute inset-0 bg-no-repeat bg-[length:auto_60%] sm:bg-[length:auto_85%] lg:bg-cover bg-right-bottom sm:bg-right z-0 transition-all duration-300 pointer-events-none"
      style="background-image: url('/images/hero-bg1.png');"
    ></div>

    <!-- Soft Gradient Overlay on small screens to ensure text legibility -->
    <div class="absolute inset-0 bg-gradient-to-r from-white via-white/90 to-transparent sm:via-white/75 lg:via-transparent z-0 pointer-events-none"></div>

    <!-- Left Content Layer -->
    <div class="relative z-10 px-3.5 sm:px-8 md:px-12 lg:px-16 max-w-[840px] w-full py-6 sm:py-8 lg:py-10 flex flex-col justify-between space-y-4 sm:space-y-6">

      <!-- Greeting & Title -->
      <div>
        <div class="flex items-center gap-2">
          <p class="text-indigo-600 font-bold text-xs uppercase tracking-widest">{{ subtitle || 'GOOD MORNING,' }}</p>
          <span class="text-sm">👋</span>
        </div>
        <h1 class="text-2xl sm:text-4xl md:text-5xl lg:text-6xl font-black tracking-tight text-slate-800 leading-tight mt-1.5 uppercase break-words">
          {{ title || profile.name.split(' ')[0] }}
        </h1>
        <p class="text-slate-600 text-xs sm:text-sm md:text-base leading-relaxed mt-2 font-normal max-w-[540px]">
          {{ description || 'Access your exams, track your academic progress, and stay organized throughout the semester.' }}
        </p>
      </div>

      <!-- Responsive Student Information Badges -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 max-w-[640px]">
        <div class="p-2 sm:p-2.5 rounded-xl bg-white/90 backdrop-blur-md border border-slate-200/80 shadow-2xs">
          <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Student ID</p>
          <p class="text-xs font-mono font-bold text-slate-800 truncate">{{ profile.id || 'N/A' }}</p>
        </div>
        <div class="p-2 sm:p-2.5 rounded-xl bg-white/90 backdrop-blur-md border border-slate-200/80 shadow-2xs">
          <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Year Level</p>
          <p class="text-xs font-bold text-slate-800 truncate">{{ profile.yearLevel || 'Year 3' }}</p>
        </div>
        <div class="p-2 sm:p-2.5 rounded-xl bg-white/90 backdrop-blur-md border border-slate-200/80 shadow-2xs">
          <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Department</p>
          <p class="text-xs font-bold text-slate-800 truncate">{{ profile.department || 'Informatics' }}</p>
        </div>
        <div class="p-2 sm:p-2.5 rounded-xl bg-white/90 backdrop-blur-md border border-slate-200/80 shadow-2xs">
          <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Section</p>
          <p class="text-xs font-bold text-slate-800 truncate">{{ profile.section || 'A' }}</p>
        </div>
      </div>

      <!-- Row of 4 Feature Badges -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3 pt-2 sm:pt-4">
        <!-- 1. Take Exams -->
        <div class="flex items-center gap-2.5 sm:gap-3 p-2.5 sm:p-3 rounded-xl bg-[#fbfafccd] backdrop-blur-md border border-slate-200/60 shadow-xs hover:shadow transition-all">
          <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-indigo-100/80 text-indigo-600 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <h4 class="text-xs font-bold text-slate-900 leading-tight truncate">Take Exams</h4>
            <p class="text-[10px] text-slate-500 leading-tight mt-0.5 truncate hidden sm:block">Start exams online</p>
          </div>
        </div>

        <!-- 2. View Results -->
        <div class="flex items-center gap-2.5 sm:gap-3 p-2.5 sm:p-3 rounded-xl bg-[#fbfafce4] backdrop-blur-md border border-slate-200/60 shadow-xs hover:shadow transition-all">
          <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-emerald-100/80 text-emerald-600 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <h4 class="text-xs font-bold text-slate-900 leading-tight truncate">View Results</h4>
            <p class="text-[10px] text-slate-500 leading-tight mt-0.5 truncate hidden sm:block">Check results instantly</p>
          </div>
        </div>

        <!-- 3. Track Progress -->
        <div class="flex items-center gap-2.5 sm:gap-3 p-2.5 sm:p-3 rounded-xl bg-[#fbfafce4] backdrop-blur-md border border-slate-200/60 shadow-xs hover:shadow transition-all">
          <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-amber-100/80 text-amber-600 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <h4 class="text-xs font-bold text-slate-900 leading-tight truncate">Track Progress</h4>
            <p class="text-[10px] text-slate-500 leading-tight mt-0.5 truncate hidden sm:block">Monitor performance</p>
          </div>
        </div>

        <!-- 4. Stay Updated -->
        <div class="flex items-center gap-2.5 sm:gap-3 p-2.5 sm:p-3 rounded-xl bg-[#fbfafce4] backdrop-blur-md border border-slate-200/60 shadow-xs hover:shadow transition-all">
          <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-blue-100/80 text-blue-600 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
          </div>
          <div class="min-w-0">
            <h4 class="text-xs font-bold text-slate-900 leading-tight truncate">Stay Updated</h4>
            <p class="text-[10px] text-slate-500 leading-tight mt-0.5 truncate hidden sm:block">Get announcements</p>
          </div>
        </div>
      </div>

      <!-- Quote Bar -->
      <div class="max-w-[640px] p-2.5 sm:p-3 px-3 sm:px-4 rounded-xl sm:rounded-2xl bg-white/90 backdrop-blur-md border border-slate-200/80 shadow-2xs flex items-center gap-2.5 sm:gap-3 text-xs">
        <span class="text-indigo-600 font-serif font-black text-lg sm:text-xl leading-none shrink-0">“</span>
        <p class="text-slate-600 font-medium italic flex-1 text-[11px] sm:text-xs line-clamp-2 sm:line-clamp-none">
          Education is the most powerful weapon which you can use to change the world.
        </p>
        <span class="text-indigo-600 font-bold text-[10px] sm:text-[11px] whitespace-nowrap ml-auto shrink-0">– Nelson Mandela</span>
      </div>

    </div>
  </div>
</template>
