<script setup lang="ts">
import { useSemesterLockStore } from '../store/semesterLockStore'
import {
  FileText,
  Layers,
  BarChart3,
  PieChart,
  PlusCircle,
  Eye,
  Lock,
  Sparkles
} from 'lucide-vue-next'

const lockStore = useSemesterLockStore()
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-6 shadow-xs">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center">
          <Sparkles class="w-4 h-4" />
        </div>
        <h2 class="text-base font-bold text-slate-900 tracking-tight">Quick Actions</h2>
      </div>

      <!-- Semester Lock Indicator -->
      <span
        v-if="lockStore.isLocked"
        class="px-2.5 py-0.5 text-[10px] font-bold bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200/80 flex items-center gap-1 shadow-2xs"
      >
        <Lock class="w-2.5 h-2.5" />
        <span>Read-Only</span>
      </span>
    </div>

    <!-- Actions for Editable Semester -->
    <div v-if="!lockStore.isLocked" class="grid grid-cols-2 gap-2.5">
      <!-- Create Exam -->
      <router-link
        to="/instructor/exams/create"
        class="flex flex-col items-center justify-center p-3.5 border border-slate-100 bg-slate-50/40 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/40 hover:shadow-2xs transition-all group text-center"
      >
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center mb-2 group-hover:scale-105 transition-transform shadow-2xs">
          <PlusCircle class="w-5 h-5" />
        </div>
        <span class="text-[12px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors leading-tight">Create Exam</span>
        <span class="text-[10px] text-slate-400 font-medium mt-0.5">New assessment</span>
      </router-link>

      <!-- Question Banks -->
      <router-link
        to="/instructor/question-banks"
        class="flex flex-col items-center justify-center p-3.5 border border-slate-100 bg-slate-50/40 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/40 hover:shadow-2xs transition-all group text-center"
      >
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center mb-2 group-hover:scale-105 transition-transform shadow-2xs">
          <Layers class="w-5 h-5" />
        </div>
        <span class="text-[12px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors leading-tight">Question Banks</span>
        <span class="text-[10px] text-slate-400 font-medium mt-0.5">Manage items</span>
      </router-link>

      <!-- View Results -->
      <router-link
        to="/instructor/results"
        class="flex flex-col items-center justify-center p-3.5 border border-slate-100 bg-slate-50/40 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/40 hover:shadow-2xs transition-all group text-center"
      >
        <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center mb-2 group-hover:scale-105 transition-transform shadow-2xs">
          <BarChart3 class="w-5 h-5" />
        </div>
        <span class="text-[12px] font-bold text-slate-700 group-hover:text-teal-600 transition-colors leading-tight">Exam Results</span>
        <span class="text-[10px] text-slate-400 font-medium mt-0.5">Grades & reviews</span>
      </router-link>

      <!-- View Reports -->
      <router-link
        to="/instructor/reports"
        class="flex flex-col items-center justify-center p-3.5 border border-slate-100 bg-slate-50/40 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/40 hover:shadow-2xs transition-all group text-center"
      >
        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-2 group-hover:scale-105 transition-transform shadow-2xs">
          <PieChart class="w-5 h-5" />
        </div>
        <span class="text-[12px] font-bold text-slate-700 group-hover:text-amber-600 transition-colors leading-tight">View Reports</span>
        <span class="text-[10px] text-slate-400 font-medium mt-0.5">Course analytics</span>
      </router-link>
    </div>

    <!-- Read-Only Actions When Semester is Locked -->
    <div v-else class="grid grid-cols-2 gap-2.5">
      <!-- View Exams -->
      <router-link
        to="/instructor/exams"
        class="flex flex-col items-center justify-center p-3.5 border border-slate-100 bg-slate-50/60 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/40 hover:shadow-2xs transition-all group text-center"
      >
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center mb-2 group-hover:scale-105 transition-transform shadow-2xs">
          <Eye class="w-5 h-5" />
        </div>
        <span class="text-[12px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors leading-tight">View Exams</span>
        <span class="text-[10px] text-slate-400 font-medium mt-0.5">Read-only list</span>
      </router-link>

      <!-- Browse Banks -->
      <router-link
        to="/instructor/question-banks"
        class="flex flex-col items-center justify-center p-3.5 border border-slate-100 bg-slate-50/60 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/40 hover:shadow-2xs transition-all group text-center"
      >
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center mb-2 group-hover:scale-105 transition-transform shadow-2xs">
          <Layers class="w-5 h-5" />
        </div>
        <span class="text-[12px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors leading-tight">Browse Banks</span>
        <span class="text-[10px] text-slate-400 font-medium mt-0.5">Archived items</span>
      </router-link>

      <!-- View Results -->
      <router-link
        to="/instructor/results"
        class="flex flex-col items-center justify-center p-3.5 border border-slate-100 bg-slate-50/60 rounded-xl hover:border-emerald-200 hover:bg-emerald-50/40 hover:shadow-2xs transition-all group text-center"
      >
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2 group-hover:scale-105 transition-transform shadow-2xs">
          <BarChart3 class="w-5 h-5" />
        </div>
        <span class="text-[12px] font-bold text-slate-800 group-hover:text-emerald-600 transition-colors leading-tight">View Results</span>
        <span class="text-[10px] text-slate-400 font-medium mt-0.5">Submitted grades</span>
      </router-link>

      <!-- View Reports -->
      <router-link
        to="/instructor/reports"
        class="flex flex-col items-center justify-center p-3.5 border border-slate-100 bg-slate-50/60 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/40 hover:shadow-2xs transition-all group text-center"
      >
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center mb-2 group-hover:scale-105 transition-transform shadow-2xs">
          <PieChart class="w-5 h-5" />
        </div>
        <span class="text-[12px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors leading-tight">View Reports</span>
        <span class="text-[10px] text-slate-400 font-medium mt-0.5">Semester analytics</span>
      </router-link>
    </div>
  </div>
</template>
