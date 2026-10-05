<template>
  <div class="border-b border-slate-200 bg-white px-4 sm:px-8 flex items-center justify-between overflow-x-auto no-scrollbar gap-4">
    
    <div class="flex items-center gap-4 sm:gap-6 min-w-max">
      <button class="py-3.5 sm:py-4 text-[13px] font-bold text-[#5138ed] border-b-2 border-[#5138ed] min-h-[44px]">
        Overview
      </button>
      <button class="py-3.5 sm:py-4 text-[13px] font-bold text-slate-500 hover:text-slate-800 transition-colors min-h-[44px]">
        Exam Reports
      </button>
      <button class="py-3.5 sm:py-4 text-[13px] font-bold text-slate-500 hover:text-slate-800 transition-colors min-h-[44px]">
        Student Reports
      </button>
      <button class="py-3.5 sm:py-4 text-[13px] font-bold text-slate-500 hover:text-slate-800 transition-colors min-h-[44px]">
        Question Reports
      </button>
      <button class="py-3.5 sm:py-4 text-[13px] font-bold text-slate-500 hover:text-slate-800 transition-colors min-h-[44px]">
        Performance Reports
      </button>
      <button class="py-3.5 sm:py-4 text-[13px] font-bold text-slate-500 hover:text-slate-800 transition-colors min-h-[44px]">
        Audit Logs
      </button>
    </div>

    <button class="py-2 px-3.5 sm:px-4 border border-[#5138ed] text-[#5138ed] font-bold text-[12px] rounded-xl hover:bg-indigo-50 transition-colors flex items-center gap-2 whitespace-nowrap min-h-[40px] shrink-0">
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
      Export Report
    </button>
    
  </div>
</template>

<style scoped>
/* Hide scrollbar for Chrome, Safari and Opera */
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
/* Hide scrollbar for IE, Edge and Firefox */
.no-scrollbar {
  -ms-overflow-style: none;  /* IE and Edge */
  scrollbar-width: none;  /* Firefox */
}
</style>
