<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useSemesterLockStore } from '../../store/semesterLockStore'

const router = useRouter()
const lockStore = useSemesterLockStore()

const handleCreate = () => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('create exam')
    return
  }
  router.push('/instructor/exams/create')
}
</script>

<template>
  <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col justify-between">
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#5138ed]">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
        </div>
        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Quick Actions</h2>
      </div>

      <span v-if="lockStore.isLocked" class="px-2 py-0.5 text-[9px] font-bold bg-emerald-50 text-emerald-700 rounded-md border border-emerald-200 flex items-center gap-1">
        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        Read-Only
      </span>
    </div>
    
    <!-- Action Cards Grid -->
    <div class="grid grid-cols-2 gap-2.5">
      <!-- Create New Exam -->
      <button 
        @click="handleCreate" 
        class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200/70 hover:border-[#5138ed] hover:bg-indigo-50/40 hover:shadow-2xs transition-all group cursor-pointer text-center"
      >
        <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#5138ed] mb-1.5 group-hover:scale-105 transition-transform">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
        </div>
        <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">
          {{ lockStore.isLocked ? 'Exam Creation (Locked)' : 'Create New Exam' }}
        </span>
      </button>
      
      <!-- Question Banks -->
      <router-link 
        to="/instructor/question-banks" 
        class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200/70 hover:border-[#5138ed] hover:bg-indigo-50/40 hover:shadow-2xs transition-all group text-center"
      >
        <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#5138ed] mb-1.5 group-hover:scale-105 transition-transform">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
        </div>
        <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">
          Question Banks
        </span>
      </router-link>
      
      <!-- Results Management -->
      <router-link 
        to="/instructor/results" 
        class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200/70 hover:border-[#5138ed] hover:bg-indigo-50/40 hover:shadow-2xs transition-all group text-center"
      >
        <div class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 mb-1.5 group-hover:scale-105 transition-transform">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">
          View Results
        </span>
      </router-link>
      
      <!-- Exam Reports -->
      <router-link 
        to="/instructor/reports" 
        class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200/70 hover:border-[#5138ed] hover:bg-indigo-50/40 hover:shadow-2xs transition-all group text-center"
      >
        <div class="w-8 h-8 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 mb-1.5 group-hover:scale-105 transition-transform">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">
          Exam Reports
        </span>
      </router-link>
    </div>
  </div>
</template>
