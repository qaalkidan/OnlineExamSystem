<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{
  activeTab?: string
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: string): void
  (e: 'export'): void
  (e: 'print'): void
}>()

const currentTab = ref(props.activeTab || 'overview')
const showExportMenu = ref(false)

const tabs = [
  { id: 'overview', label: 'Overview' },
  { id: 'exam-reports', label: 'Exam Reports' },
  { id: 'student-reports', label: 'Student Reports' },
  { id: 'question-reports', label: 'Question Reports' },
  { id: 'performance-reports', label: 'Performance Reports' },
  { id: 'audit-logs', label: 'Audit Logs' }
]

const selectTab = (id: string) => {
  currentTab.value = id
  emit('update:activeTab', id)
}
</script>

<template>
  <div class="border-b border-[#E6EBF3] bg-white px-4 sm:px-6 lg:px-8 flex items-center justify-between overflow-x-auto no-scrollbar gap-4 shadow-2xs">
    
    <!-- Tab items -->
    <div class="flex items-center gap-1 sm:gap-2 min-w-max">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="selectTab(tab.id)"
        class="relative py-4 px-3 sm:px-4 text-[13px] font-bold transition-all whitespace-nowrap min-h-[50px] flex items-center gap-2 cursor-pointer"
        :class="currentTab === tab.id 
          ? 'text-[#4F35F3]' 
          : 'text-[#71819B] hover:text-[#17243A] hover:bg-slate-50/80 rounded-lg'"
      >
        <span>{{ tab.label }}</span>
        
        <!-- Active indicator bar matching screenshot -->
        <span
          v-if="currentTab === tab.id"
          class="absolute bottom-0 left-0 right-0 h-[2.5px] bg-[#4F35F3] rounded-t-full transition-all duration-200"
        ></span>
      </button>
    </div>

    <!-- Export Report Button & Dropdown -->
    <div class="relative py-2 shrink-0">
      <button
        @click="showExportMenu = !showExportMenu"
        class="py-2 px-3.5 sm:px-4 border border-[#4F35F3] text-[#4F35F3] bg-white hover:bg-[#EEF0FF] active:bg-indigo-100 font-bold text-[12px] rounded-xl transition-all flex items-center gap-2 whitespace-nowrap min-h-[40px] shadow-2xs cursor-pointer group"
        title="Export Academic Report"
      >
        <svg class="w-4 h-4 shrink-0 transition-transform group-hover:-translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
        </svg>
        <span>Export Report</span>
        <svg class="w-3 h-3 transition-transform text-[#4F35F3]" :class="{ 'rotate-180': showExportMenu }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <!-- Dropdown Menu -->
      <div
        v-if="showExportMenu"
        @click="showExportMenu = false"
        class="absolute right-0 top-full mt-1.5 w-52 bg-white border border-[#E6EBF3] rounded-xl shadow-lg py-1.5 z-50 animate-in fade-in slide-in-from-top-1 duration-150"
      >
        <button
          @click="emit('export')"
          class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-slate-700 hover:bg-[#EEF0FF] hover:text-[#4F35F3] flex items-center gap-2.5 transition-colors cursor-pointer"
        >
          <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <div>
            <div class="font-bold">Export CSV File</div>
            <div class="text-[10px] text-slate-400">Download raw data (.csv)</div>
          </div>
        </button>

        <button
          @click="emit('print')"
          class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-slate-700 hover:bg-[#EEF0FF] hover:text-[#4F35F3] flex items-center gap-2.5 transition-colors cursor-pointer"
        >
          <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
          <div>
            <div class="font-bold">Print / PDF Report</div>
            <div class="text-[10px] text-slate-400">Formatted official PDF report</div>
          </div>
        </button>
      </div>
    </div>
    
  </div>
</template>

<style scoped>
/* Hide scrollbar for Chrome, Safari and Opera */
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
/* Hide scrollbar for IE, Edge and Firefox */
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
