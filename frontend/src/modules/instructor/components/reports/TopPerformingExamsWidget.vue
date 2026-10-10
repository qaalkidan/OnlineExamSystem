<script setup lang="ts">
import { ref } from 'vue'

const showAllModal = ref(false)

const topExams = [
  { rank: 1, title: 'Database Systems Mid Exam', code: 'CS 301', avgScore: '82.6%', passRate: '78.3%', students: 98 },
  { rank: 2, title: 'Web Programming Quiz 1', code: 'CS 204', avgScore: '81.4%', passRate: '76.5%', students: 95 },
  { rank: 3, title: 'Operating Systems Mid Exam', code: 'CS 305', avgScore: '79.8%', passRate: '74.2%', students: 88 },
  { rank: 4, title: 'Software Engineering Quiz 1', code: 'SE 302', avgScore: '76.3%', passRate: '72.1%', students: 90 },
  { rank: 5, title: 'Data Structures Final Exam', code: 'CS 202', avgScore: '75.6%', passRate: '70.4%', students: 92 },
]
</script>

<template>
  <div class="bg-white border border-[#E6EBF3] rounded-2xl p-5 sm:p-6 shadow-2xs hover:border-slate-300 transition-all">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-[14px] font-bold text-[#17243A]">Top Performing Exams</h2>
      <button
        @click="showAllModal = true"
        class="text-[11px] font-bold text-[#4F35F3] hover:text-indigo-800 transition-colors cursor-pointer"
      >
        View All
      </button>
    </div>
    
    <!-- Table Header matching screenshot -->
    <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2 text-[10px] font-bold text-slate-800">
      <span class="w-1/2">Exam Title</span>
      <span class="w-1/4 text-center">Average Score</span>
      <span class="w-1/4 text-center">Pass Rate</span>
    </div>

    <!-- Data Rows matching screenshot -->
    <div class="space-y-3.5">
      <div
        v-for="exam in topExams"
        :key="exam.rank"
        class="flex items-center justify-between hover:bg-slate-50/70 p-1 rounded-lg transition-colors group cursor-pointer"
      >
        <span class="text-[11px] font-bold text-slate-600 group-hover:text-[#4F35F3] w-1/2 truncate pr-2" :title="exam.title">
          {{ exam.title }}
        </span>
        <span class="text-[11px] font-medium text-slate-800 w-1/4 text-center">
          {{ exam.avgScore }}
        </span>
        <span class="text-[11px] font-medium text-emerald-600 w-1/4 text-center">
          {{ exam.passRate }}
        </span>
      </div>
    </div>

    <!-- View All Modal -->
    <div
      v-if="showAllModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150"
    >
      <div class="bg-white border border-[#E6EBF3] rounded-2xl w-full max-w-lg shadow-2xl p-6 relative">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <div>
            <h3 class="text-base font-bold text-[#17243A]">Top Performing Exams</h3>
            <p class="text-xs text-[#71819B] mt-0.5">Ranked by highest cohort average score</p>
          </div>
          <button
            @click="showAllModal = false"
            class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <div class="mt-4 space-y-2.5 max-h-[360px] overflow-y-auto pr-1">
          <div
            v-for="exam in topExams"
            :key="exam.rank"
            class="flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-indigo-100 hover:bg-indigo-50/30 transition-all"
          >
            <div class="flex items-center gap-3">
              <span class="w-6 h-6 rounded-full bg-indigo-50 text-[#4F35F3] font-black text-xs flex items-center justify-center">
                {{ exam.rank }}
              </span>
              <div>
                <div class="text-xs font-bold text-[#17243A]">{{ exam.title }}</div>
                <div class="text-[10px] text-[#71819B]">{{ exam.code }} • {{ exam.students }} Students</div>
              </div>
            </div>
            <div class="text-right">
              <div class="text-xs font-black text-slate-800">Avg {{ exam.avgScore }}</div>
              <div class="text-[10px] font-bold text-emerald-600">Pass {{ exam.passRate }}</div>
            </div>
          </div>
        </div>

        <div class="mt-5 pt-3 border-t border-slate-100 flex justify-end">
          <button
            @click="showAllModal = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
