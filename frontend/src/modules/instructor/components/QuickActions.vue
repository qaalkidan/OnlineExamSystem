<script setup lang="ts">
import { useSemesterLockStore } from '../store/semesterLockStore'

const lockStore = useSemesterLockStore()
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-[16px] font-bold text-slate-800">Quick Actions</h2>
      <span v-if="lockStore.isLocked" class="px-2 py-0.5 text-[10px] font-bold bg-emerald-50 text-emerald-700 rounded-md border border-emerald-200 flex items-center gap-1">
        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        Read-Only
      </span>
    </div>
    
    <!-- Actions for Editable Semester -->
    <div v-if="!lockStore.isLocked" class="grid grid-cols-2 gap-3">
      <router-link to="/instructor/exams/create" class="flex flex-col items-center justify-center py-4 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group">
        <svg class="w-6 h-6 text-[#5138ed] mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        <span class="text-[12px] font-semibold text-slate-600 group-hover:text-[#5138ed]">Create Exam</span>
      </router-link>
      
      <router-link to="/instructor/question-banks" class="flex flex-col items-center justify-center py-4 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group">
        <svg class="w-6 h-6 text-[#5138ed] mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
        <span class="text-[12px] font-semibold text-slate-600 group-hover:text-[#5138ed]">Add Questions</span>
      </router-link>
      
      <router-link to="/instructor/exams" class="flex flex-col items-center justify-center py-4 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group">
        <svg class="w-6 h-6 text-[#5138ed] mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        <span class="text-[12px] font-semibold text-slate-600 group-hover:text-[#5138ed]">Manage Exams</span>
      </router-link>
      
      <router-link to="/instructor/reports" class="flex flex-col items-center justify-center py-4 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group">
        <svg class="w-6 h-6 text-[#5138ed] mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        <span class="text-[12px] font-semibold text-slate-600 group-hover:text-[#5138ed]">View Reports</span>
      </router-link>
    </div>

    <!-- Read-Only Actions When Semester is Locked -->
    <div v-else class="grid grid-cols-2 gap-3">
      <router-link to="/instructor/exams" class="flex flex-col items-center justify-center py-4 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group bg-slate-50/50">
        <svg class="w-6 h-6 text-[#5138ed] mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        <span class="text-[12px] font-semibold text-slate-700 group-hover:text-[#5138ed]">View Exams</span>
      </router-link>
      
      <router-link to="/instructor/question-banks" class="flex flex-col items-center justify-center py-4 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group bg-slate-50/50">
        <svg class="w-6 h-6 text-[#5138ed] mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
        <span class="text-[12px] font-semibold text-slate-700 group-hover:text-[#5138ed]">Browse Banks</span>
      </router-link>
      
      <router-link to="/instructor/results" class="flex flex-col items-center justify-center py-4 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group bg-slate-50/50">
        <svg class="w-6 h-6 text-emerald-600 mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
        <span class="text-[12px] font-semibold text-slate-700 group-hover:text-emerald-600">View Results</span>
      </router-link>
      
      <router-link to="/instructor/reports" class="flex flex-col items-center justify-center py-4 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group bg-slate-50/50">
        <svg class="w-6 h-6 text-[#5138ed] mb-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        <span class="text-[12px] font-semibold text-slate-700 group-hover:text-[#5138ed]">View Reports</span>
      </router-link>
    </div>
  </div>
</template>
