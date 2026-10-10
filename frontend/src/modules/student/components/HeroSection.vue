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
  <div class="relative w-full min-h-[360px] sm:min-h-[420px] lg:min-h-[470px] bg-white border-b border-[#E6EBF3] overflow-hidden flex items-center">
    
    <!-- Background Graphic: hero-bg1.png (Graduation cap & Wollo University 3D seal glass cube) -->
    <div 
      class="absolute inset-0 bg-no-repeat bg-[length:auto_70%] sm:bg-[length:auto_88%] lg:bg-contain bg-right-bottom sm:bg-right z-0 transition-all duration-300 pointer-events-none"
      style="background-image: url('/images/hero-bg1.png');"
    ></div>

    <!-- Soft Gradient Overlay to ensure crisp typography and high contrast -->
    <div class="absolute inset-0 bg-gradient-to-r from-white via-white/85 to-white/10 sm:via-white/85 sm:to-transparent z-0 pointer-events-none"></div>

    <!-- Left Content Layer -->
    <div class="relative z-10 px-3.5 sm:px-8 md:px-12 lg:px-16 max-w-[880px] w-full py-6 sm:py-8 lg:py-10 flex flex-col justify-between space-y-4 sm:space-y-6">

      <!-- Greeting & Title -->
      <div>
        <div class="flex items-center gap-2">
          <p class="text-[#4F35F3] font-black text-xs uppercase tracking-widest">{{ subtitle || 'GOOD MORNING,' }}</p>
          <span class="text-sm">👋</span>
        </div>
        <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-[56px] font-black tracking-tight text-[#17243A] leading-tight mt-1.5 uppercase break-words">
          {{ title || (profile.name ? profile.name.split(' ')[0] : 'STUDENT') }}
        </h1>
        <p class="text-[#71819B] text-xs sm:text-sm md:text-[15px] leading-relaxed mt-2 font-normal max-w-[540px]">
          {{ description || 'Access your exams, track your academic progress, and stay organized throughout the semester.' }}
        </p>
      </div>

      <!-- Responsive Student Information Badges -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 max-w-[660px]">
        <div class="p-2.5 sm:p-3 rounded-xl bg-white/95 backdrop-blur-md border border-[#E6EBF3] shadow-2xs hover:border-[#4F35F3]/30 transition-colors">
          <p class="text-[9px] font-bold text-[#71819B] uppercase tracking-wider">Student ID</p>
          <p class="text-xs font-mono font-bold text-[#17243A] truncate mt-0.5">{{ profile.id || 'N/A' }}</p>
        </div>
        <div class="p-2.5 sm:p-3 rounded-xl bg-white/95 backdrop-blur-md border border-[#E6EBF3] shadow-2xs hover:border-[#4F35F3]/30 transition-colors">
          <p class="text-[9px] font-bold text-[#71819B] uppercase tracking-wider">Year Level</p>
          <p class="text-xs font-bold text-[#17243A] truncate mt-0.5">{{ profile.yearLevel || '1st Year' }}</p>
        </div>
        <div class="p-2.5 sm:p-3 rounded-xl bg-white/95 backdrop-blur-md border border-[#E6EBF3] shadow-2xs hover:border-[#4F35F3]/30 transition-colors">
          <p class="text-[9px] font-bold text-[#71819B] uppercase tracking-wider">Department</p>
          <p class="text-xs font-bold text-[#17243A] truncate mt-0.5">{{ profile.department || 'Computer Science' }}</p>
        </div>
        <div class="p-2.5 sm:p-3 rounded-xl bg-white/95 backdrop-blur-md border border-[#E6EBF3] shadow-2xs hover:border-[#4F35F3]/30 transition-colors">
          <p class="text-[9px] font-bold text-[#71819B] uppercase tracking-wider">Section</p>
          <p class="text-xs font-bold text-[#17243A] truncate mt-0.5">{{ profile.section || 'Section A' }}</p>
        </div>
      </div>

      <!-- Row of 4 Feature Badges -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3 pt-1 sm:pt-2 max-w-[700px]">
        <!-- 1. Take Exams -->
        <div class="flex items-center gap-2.5 sm:gap-3 p-2.5 sm:p-3 rounded-xl bg-white/95 backdrop-blur-md border border-[#E6EBF3] shadow-2xs hover:shadow-xs hover:border-[#4F35F3]/30 transition-all">
          <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <h4 class="text-xs font-bold text-[#17243A] leading-tight truncate">Take Exams</h4>
            <p class="text-[10px] text-[#71819B] leading-tight mt-0.5 truncate hidden sm:block">Start exams online</p>
          </div>
        </div>

        <!-- 2. View Results -->
        <div class="flex items-center gap-2.5 sm:gap-3 p-2.5 sm:p-3 rounded-xl bg-white/95 backdrop-blur-md border border-[#E6EBF3] shadow-2xs hover:shadow-xs hover:border-emerald-200 transition-all">
          <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 shadow-2xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <h4 class="text-xs font-bold text-[#17243A] leading-tight truncate">View Results</h4>
            <p class="text-[10px] text-[#71819B] leading-tight mt-0.5 truncate hidden sm:block">Check results instantly</p>
          </div>
        </div>

        <!-- 3. Track Progress -->
        <div class="flex items-center gap-2.5 sm:gap-3 p-2.5 sm:p-3 rounded-xl bg-white/95 backdrop-blur-md border border-[#E6EBF3] shadow-2xs hover:shadow-xs hover:border-amber-200 transition-all">
          <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 shadow-2xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <h4 class="text-xs font-bold text-[#17243A] leading-tight truncate">Track Progress</h4>
            <p class="text-[10px] text-[#71819B] leading-tight mt-0.5 truncate hidden sm:block">Monitor performance</p>
          </div>
        </div>

        <!-- 4. Stay Updated -->
        <div class="flex items-center gap-2.5 sm:gap-3 p-2.5 sm:p-3 rounded-xl bg-white/95 backdrop-blur-md border border-[#E6EBF3] shadow-2xs hover:shadow-xs hover:border-blue-200 transition-all">
          <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 shadow-2xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
          </div>
          <div class="min-w-0">
            <h4 class="text-xs font-bold text-[#17243A] leading-tight truncate">Stay Updated</h4>
            <p class="text-[10px] text-[#71819B] leading-tight mt-0.5 truncate hidden sm:block">Get announcements</p>
          </div>
        </div>
      </div>

      <!-- Quote Bar -->
      <div class="max-w-[660px] p-2.5 sm:p-3 px-3.5 sm:px-4 rounded-xl sm:rounded-2xl bg-white/95 backdrop-blur-md border border-[#E6EBF3] shadow-2xs flex items-center gap-2.5 sm:gap-3 text-xs">
        <span class="text-[#4F35F3] font-serif font-black text-lg sm:text-xl leading-none shrink-0">“</span>
        <p class="text-slate-700 font-medium italic flex-1 text-[11px] sm:text-xs line-clamp-2 sm:line-clamp-none">
          Education is the most powerful weapon which you can use to change the world.
        </p>
        <span class="text-[#4F35F3] font-bold text-[10px] sm:text-[11px] whitespace-nowrap ml-auto shrink-0">– Nelson Mandela</span>
      </div>

    </div>
  </div>
</template>
