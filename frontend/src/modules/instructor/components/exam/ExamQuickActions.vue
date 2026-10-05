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

const handleImport = () => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('import questions')
    return
  }
  router.push('/instructor/question-banks')
}
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm">
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-[12px] font-bold text-slate-800">Quick Actions</h2>
      <span v-if="lockStore.isLocked" class="px-2 py-0.5 text-[9px] font-bold bg-emerald-50 text-emerald-700 rounded-md border border-emerald-200">
        Read-Only
      </span>
    </div>
    
    <!-- When Editable -->
    <div v-if="!lockStore.isLocked" class="grid grid-cols-2 gap-2">
      <button @click="handleCreate" class="flex flex-col items-center justify-center py-3 px-2 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group">
        <svg class="w-4 h-4 text-[#5138ed] mb-1.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        <span class="text-[9px] xl:text-[10px] font-bold text-slate-600 group-hover:text-[#5138ed] text-center">Create New Exam</span>
      </button>
      
      <button @click="handleImport" class="flex flex-col items-center justify-center py-3 px-2 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group">
        <svg class="w-4 h-4 text-[#5138ed] mb-1.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
        <span class="text-[9px] xl:text-[10px] font-bold text-slate-600 group-hover:text-[#5138ed] text-center">Question Banks</span>
      </button>
      
      <router-link to="/instructor/results" class="flex flex-col items-center justify-center py-3 px-2 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group">
        <svg class="w-4 h-4 text-[#5138ed] mb-1.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
        <span class="text-[9px] xl:text-[10px] font-bold text-slate-600 group-hover:text-[#5138ed] text-center">Results</span>
      </router-link>
      
      <router-link to="/instructor/reports" class="flex flex-col items-center justify-center py-3 px-2 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group">
        <svg class="w-4 h-4 text-[#5138ed] mb-1.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        <span class="text-[9px] xl:text-[10px] font-bold text-slate-600 group-hover:text-[#5138ed] text-center">Exam Reports</span>
      </router-link>
    </div>

    <!-- When Locked (Read-Only) -->
    <div v-else class="grid grid-cols-2 gap-2">
      <router-link to="/instructor/exams" class="flex flex-col items-center justify-center py-3 px-2 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group bg-slate-50/50">
        <svg class="w-4 h-4 text-[#5138ed] mb-1.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        <span class="text-[9px] xl:text-[10px] font-bold text-slate-600 group-hover:text-[#5138ed] text-center">View Exams</span>
      </router-link>
      
      <router-link to="/instructor/question-banks" class="flex flex-col items-center justify-center py-3 px-2 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group bg-slate-50/50">
        <svg class="w-4 h-4 text-[#5138ed] mb-1.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
        <span class="text-[9px] xl:text-[10px] font-bold text-slate-600 group-hover:text-[#5138ed] text-center">Browse Banks</span>
      </router-link>
      
      <router-link to="/instructor/results" class="flex flex-col items-center justify-center py-3 px-2 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group bg-slate-50/50">
        <svg class="w-4 h-4 text-emerald-600 mb-1.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
        <span class="text-[9px] xl:text-[10px] font-bold text-slate-600 group-hover:text-[#5138ed] text-center">View Results</span>
      </router-link>
      
      <router-link to="/instructor/reports" class="flex flex-col items-center justify-center py-3 px-2 border border-slate-100 rounded-xl hover:border-[#5138ed] hover:shadow-sm transition-all group bg-slate-50/50">
        <svg class="w-4 h-4 text-[#5138ed] mb-1.5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        <span class="text-[9px] xl:text-[10px] font-bold text-slate-600 group-hover:text-[#5138ed] text-center">Exam Reports</span>
      </router-link>
    </div>
  </div>
</template>
