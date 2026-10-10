<script setup lang="ts">
import { useProfileStore } from '../../store/profileStore'

const profileStore = useProfileStore()
</script>

<template>
  <div class="bg-white border border-[#E6EBF3] rounded-2xl p-5 sm:p-6 shadow-2xs hover:border-slate-300 transition-all">
    <h2 class="text-[14px] font-bold text-[#17243A] mb-4">Account Statistics</h2>
    
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3.5">
      
      <!-- Exams Created -->
      <div class="flex items-center gap-3 p-3.5 rounded-xl border border-[#E6EBF3] bg-white shadow-2xs hover:border-indigo-100 transition-all">
        <div class="w-10 h-10 rounded-xl bg-[#EEF0FF] flex items-center justify-center text-[#4F35F3] shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="text-[18px] font-black text-[#17243A] leading-tight mb-0.5">
            {{ profileStore.profile?.stats?.exams_created ?? 2 }}
          </div>
          <div class="text-[10px] font-bold text-[#71819B] uppercase tracking-wide truncate">Exams Created</div>
        </div>
      </div>

      <!-- Questions Created -->
      <div class="flex items-center gap-3 p-3.5 rounded-xl border border-[#E6EBF3] bg-white shadow-2xs hover:border-emerald-100 transition-all">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="text-[18px] font-black text-[#17243A] leading-tight mb-0.5">
            {{ profileStore.profile?.stats?.questions_created ?? 48 }}
          </div>
          <div class="text-[10px] font-bold text-[#71819B] uppercase tracking-wide truncate">Questions Created</div>
        </div>
      </div>

      <!-- Students Assessed -->
      <div class="flex items-center gap-3 p-3.5 rounded-xl border border-[#E6EBF3] bg-white shadow-2xs hover:border-blue-100 transition-all">
        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="text-[18px] font-black text-[#17243A] leading-tight mb-0.5">
            {{ profileStore.profile?.stats?.students_assessed ?? 193 }}
          </div>
          <div class="text-[10px] font-bold text-[#71819B] uppercase tracking-wide truncate">Students Assessed</div>
        </div>
      </div>

      <!-- Reports Generated -->
      <div class="flex items-center gap-3 p-3.5 rounded-xl border border-[#E6EBF3] bg-white shadow-2xs hover:border-amber-100 transition-all">
        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="text-[18px] font-black text-[#17243A] leading-tight mb-0.5">
            {{ profileStore.profile?.stats?.reports_generated ?? 12 }}
          </div>
          <div class="text-[10px] font-bold text-[#71819B] uppercase tracking-wide truncate">Reports Generated</div>
        </div>
      </div>

      <!-- Hours Saved -->
      <div class="flex items-center gap-3 p-3.5 rounded-xl border border-[#E6EBF3] bg-white shadow-2xs hover:border-rose-100 transition-all">
        <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600 shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="text-[18px] font-black text-[#17243A] leading-tight mb-0.5">
            {{ profileStore.profile?.stats?.hours_saved ?? 34 }}
          </div>
          <div class="text-[10px] font-bold text-[#71819B] uppercase tracking-wide truncate">Hours Saved</div>
        </div>
      </div>

    </div>

    <div class="mt-4 flex items-center gap-2 text-slate-400">
      <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span class="text-[10px] font-medium text-[#71819B]">Statistics are updated daily and based on your examination and grading activity.</span>
    </div>
  </div>
</template>
