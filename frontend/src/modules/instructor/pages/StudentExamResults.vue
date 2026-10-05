<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const activeTab = ref('all')
const tabs = ['All Exams', 'Completed', 'Pending', 'Graded', 'Ungraded']

const exams = ref([
  { id: 1, title: 'Database Systems Mid Exam', subtitle: 'Mid Term Examination', type: 'Written Exam', typeColor: 'bg-indigo-50 text-[#5138ed]', date: 'May 10, 2025', time: '10:00 AM', totalMarks: 100, score: 88, scorePercent: 88, grade: 'A', gradeColor: 'text-emerald-500', status: 'Graded', statusColor: 'bg-emerald-50 text-emerald-500', icon: 'text-indigo-500 bg-indigo-50' },
  { id: 2, title: 'Database Quiz 3', subtitle: 'Quiz', type: 'Online Quiz', typeColor: 'bg-blue-50 text-blue-500', date: 'Apr 28, 2025', time: '09:30 AM', totalMarks: 20, score: 13, scorePercent: 65, grade: 'C+', gradeColor: 'text-orange-500', status: 'Graded', statusColor: 'bg-emerald-50 text-emerald-500', icon: 'text-orange-400 bg-orange-50' },
  { id: 3, title: 'Database Quiz 2', subtitle: 'Quiz', type: 'Online Quiz', typeColor: 'bg-blue-50 text-blue-500', date: 'Apr 15, 2025', time: '09:30 AM', totalMarks: 25, score: 19, scorePercent: 76, grade: 'B-', gradeColor: 'text-orange-400', status: 'Graded', statusColor: 'bg-emerald-50 text-emerald-500', icon: 'text-orange-400 bg-orange-50' },
  { id: 4, title: 'Database Quiz 1', subtitle: 'Quiz', type: 'Online Quiz', typeColor: 'bg-blue-50 text-blue-500', date: 'Apr 02, 2025', time: '09:30 AM', totalMarks: 25, score: 23, scorePercent: 92, grade: 'A', gradeColor: 'text-emerald-500', status: 'Graded', statusColor: 'bg-emerald-50 text-emerald-500', icon: 'text-emerald-500 bg-emerald-50' },
  { id: 5, title: 'Database Assignment 2', subtitle: 'Assignment', type: 'Assignment', typeColor: 'bg-orange-50 text-orange-500', date: 'Mar 18, 2025', time: '11:59 PM', totalMarks: 20, score: 18, scorePercent: 90, grade: 'A', gradeColor: 'text-emerald-500', status: 'Graded', statusColor: 'bg-emerald-50 text-emerald-500', icon: 'text-blue-500 bg-blue-50' },
  { id: 6, title: 'Final Exam (Database Systems)', subtitle: 'Final Examination', type: 'Written Exam', typeColor: 'bg-indigo-50 text-[#5138ed]', date: 'Jun 10, 2025', time: '10:00 AM', totalMarks: 150, score: null, scorePercent: null, grade: '-', gradeColor: 'text-slate-400', status: 'Pending', statusColor: 'bg-orange-50 text-orange-500', icon: 'text-indigo-500 bg-indigo-50' },
])

</script>

<template>
  <div class="max-w-[1500px] mx-auto px-4 py-2">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-800">Student Exam Results</h1>
        <p class="text-[13px] text-slate-500 mt-1">View detailed examination results and performance history.</p>
        <div class="flex flex-wrap items-center gap-2 text-[12px] text-slate-400 mt-2">
          <router-link to="/instructor/dashboard" class="hover:text-[#5138ed] transition-colors">Dashboard</router-link>
          <span>&gt;</span>
          <router-link to="/instructor/students" class="hover:text-[#5138ed] transition-colors">Students</router-link>
          <span>&gt;</span>
          <span class="hover:text-[#5138ed] transition-colors cursor-pointer" @click="router.push('/instructor/students/1')">Student Profile</span>
          <span>&gt;</span>
          <span class="text-slate-600 font-medium">Exam Results</span>
        </div>
      </div>
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <button @click="router.push('/instructor/students/1')" class="min-h-[44px] flex items-center justify-center gap-2 px-4 py-2 border border-slate-200 text-slate-700 text-sm font-bold rounded-xl hover:bg-slate-50 transition-colors bg-white">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Profile
        </button>
        <button class="min-h-[44px] flex items-center justify-center gap-2 px-4 py-2 bg-[#5138ed] text-white text-sm font-bold rounded-xl shadow-sm hover:bg-[#4530d1] transition-colors">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
          Message Student
        </button>
      </div>
    </div>

    <!-- Top Card -->
    <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-6 shadow-sm mb-6 flex flex-col md:flex-row gap-4 sm:gap-6 md:gap-12 lg:gap-20">
      
      <!-- Student Info -->
      <div class="flex items-center gap-4 sm:gap-6">
        <div class="relative w-16 h-16 sm:w-20 sm:h-20 shrink-0">
          <img src="https://i.pravatar.cc/150?u=a042581f4e290267041" alt="Avatar" class="w-full h-full object-cover rounded-full shadow-sm" />
        </div>
        <div class="flex flex-col">
          <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
            <h2 class="text-lg sm:text-xl font-bold text-slate-800">Selamawit Getachew</h2>
            <span class="flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-600">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active Student
            </span>
          </div>
          <div class="flex flex-wrap items-center gap-4 sm:gap-6 text-[12px] font-medium text-slate-500">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg></div>
              <div class="flex flex-col"><span class="text-[9px] text-slate-400 font-bold uppercase tracking-wide">ID Number</span><span class="text-[12px] text-slate-700 font-bold">WU/2021/CS/001</span></div>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg></div>
              <div class="flex flex-col"><span class="text-[9px] text-slate-400 font-bold uppercase tracking-wide">Email</span><span class="text-[12px] text-slate-700 font-bold truncate max-w-[160px] sm:max-w-none">selamawit.get@wollo.edu.et</span></div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="w-px bg-slate-100 hidden md:block"></div>

      <!-- Student Metadata Grid -->
      <div class="grid grid-cols-2 gap-x-6 sm:gap-x-8 gap-y-3 sm:gap-y-4 pt-1">
        <div class="flex items-center gap-2 text-[12px]"><div class="text-slate-400 w-4"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg></div><span class="text-[11px] text-slate-500 w-16 font-medium">Program</span><span class="text-[12px] text-slate-800 font-bold">BSc in Computer Science</span></div>
        <div class="flex items-center gap-2 text-[12px]"><div class="text-slate-400 w-4"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg></div><span class="text-[11px] text-slate-500 w-16 font-medium">Section</span><span class="text-[12px] text-slate-800 font-bold">CS-304-A</span></div>
        <div class="flex items-center gap-2 text-[12px]"><div class="text-slate-400 w-4"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div><span class="text-[11px] text-slate-500 w-16 font-medium">Acad. Year</span><span class="text-[12px] text-slate-800 font-bold">2025/2026</span></div>
        <div class="flex items-center gap-2 text-[12px]"><div class="text-slate-400 w-4"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div><span class="text-[11px] text-slate-500 w-16 font-medium">Curr. Year</span><span class="text-[12px] text-slate-800 font-bold">3rd Year</span></div>
      </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
      <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-[#5138ed] shrink-0"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg></div>
        <div class="flex flex-col">
          <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Total Exams Taken</span>
          <span class="text-2xl font-black text-slate-800 leading-none my-0.5">6</span>
          <span class="text-[10px] font-medium text-slate-400">Total Exams</span>
        </div>
      </div>
      <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500 shrink-0"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
        <div class="flex flex-col">
          <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Completed Exams</span>
          <span class="text-2xl font-black text-slate-800 leading-none my-0.5">4</span>
          <span class="text-[10px] font-medium text-slate-400">Completed</span>
        </div>
      </div>
      <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center text-orange-500 shrink-0"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
        <div class="flex flex-col">
          <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Pending Exams</span>
          <span class="text-2xl font-black text-slate-800 leading-none my-0.5">2</span>
          <span class="text-[10px] font-medium text-slate-400">Pending</span>
        </div>
      </div>
      <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500 shrink-0"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>
        <div class="flex flex-col">
          <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Average Score</span>
          <span class="text-2xl font-black text-slate-800 leading-none my-0.5">78.4%</span>
          <span class="text-[10px] font-medium text-slate-400">Across all exams</span>
        </div>
      </div>
      <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-[#5138ed] shrink-0"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></div>
        <div class="flex flex-col">
          <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Highest Score</span>
          <span class="text-2xl font-black text-slate-800 leading-none my-0.5">92%</span>
          <span class="text-[10px] font-medium text-slate-400">Quiz 1</span>
        </div>
      </div>
    </div>

    <!-- Main Layout -->
    <div class="flex flex-col xl:flex-row gap-6">
      
      <!-- Left Column -->
      <div class="flex-1 min-w-0 flex flex-col">
        
        <!-- Table Area -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm flex-1 flex flex-col p-6">
          
          <!-- Filters & Tabs -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-6 border-b border-slate-100 overflow-x-auto">
              <button v-for="tab in tabs" :key="tab" @click="activeTab = tab"
                class="pb-3 text-[13px] font-bold transition-colors relative whitespace-nowrap min-h-[44px]"
                :class="activeTab === tab ? 'text-[#5138ed]' : 'text-slate-500 hover:text-slate-800'">
                {{ tab }}
                <div v-if="activeTab === tab" class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#5138ed] rounded-t-full"></div>
              </button>
            </div>
            
            <div class="flex items-center gap-3">
              <div class="relative">
                <select class="appearance-none bg-white border border-slate-200 text-slate-700 text-[12px] font-bold rounded-xl pl-4 pr-10 py-2 min-h-[44px] focus:outline-none focus:border-[#5138ed] cursor-pointer">
                  <option>All Semesters</option>
                  <option>Semester I</option>
                  <option>Semester II</option>
                </select>
                <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
              </div>
              <button class="min-h-[44px] flex items-center gap-2 px-4 py-2 border border-[#5138ed] text-[#5138ed] text-[12px] font-bold rounded-xl hover:bg-indigo-50 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                Filter
              </button>
            </div>
          </div>

          <!-- Desktop Exams Table -->
          <div class="hidden md:block overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse whitespace-nowrap">
              <thead>
                <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                  <th class="py-4 pr-3">Exam Title</th>
                  <th class="py-4 px-3">Exam Type</th>
                  <th class="py-4 px-3">Date</th>
                  <th class="py-4 px-3 text-center">Total Marks</th>
                  <th class="py-4 px-3 text-center">Score</th>
                  <th class="py-4 px-3 text-center">Grade</th>
                  <th class="py-4 px-3 text-center">Status</th>
                  <th class="py-4 pl-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="exam in exams" :key="exam.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors last:border-0 group">
                  <td class="py-4 pr-3">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :class="exam.icon"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>
                      <div class="flex flex-col">
                        <span class="text-[12px] font-bold text-slate-800">{{ exam.title }}</span>
                        <span class="text-[10px] text-slate-500 font-medium">{{ exam.subtitle }}</span>
                      </div>
                    </div>
                  </td>
                  <td class="py-4 px-3"><span class="px-2.5 py-1 text-[10px] font-bold rounded-md" :class="exam.typeColor">{{ exam.type }}</span></td>
                  <td class="py-4 px-3">
                    <div class="flex flex-col"><span class="text-[11px] font-bold text-slate-700">{{ exam.date }}</span><span class="text-[10px] text-slate-400">{{ exam.time }}</span></div>
                  </td>
                  <td class="py-4 px-3 text-center text-[12px] font-bold text-slate-700">{{ exam.totalMarks }}</td>
                  <td class="py-4 px-3 text-center">
                    <div v-if="exam.score !== null" class="flex flex-col"><span class="text-[12px] font-bold" :class="exam.gradeColor">{{ exam.score }}</span><span class="text-[10px] font-bold" :class="exam.gradeColor">{{ exam.scorePercent }}%</span></div>
                    <span v-else class="text-[12px] font-bold text-slate-400">-</span>
                  </td>
                  <td class="py-4 px-3 text-center text-[12px] font-bold" :class="exam.gradeColor">{{ exam.grade }}</td>
                  <td class="py-4 px-3 text-center">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-wider" :class="exam.statusColor">
                      <span v-if="exam.status === 'Graded'" class="w-1 h-1 rounded-full bg-emerald-500"></span>
                      <span v-else class="w-1 h-1 rounded-full bg-orange-500"></span>
                      {{ exam.status }}
                    </span>
                  </td>
                  <td class="py-4 pl-3">
                    <div class="flex items-center justify-end gap-1">
                      <button v-if="exam.status === 'Graded'" class="w-7 h-7 rounded-lg flex items-center justify-center text-[#5138ed] hover:bg-indigo-50 transition-colors" title="View Result">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                      </button>
                      <button v-else class="w-7 h-7 rounded-lg flex items-center justify-center text-[#5138ed] hover:bg-indigo-50 transition-colors" title="Pending">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                      </button>
                      <button class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile Exam Cards -->
          <div class="md:hidden divide-y divide-slate-100">
            <div v-for="exam in exams" :key="exam.id" class="py-4">
              <div class="flex items-start gap-3 mb-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 mt-0.5" :class="exam.icon">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-[13px] font-bold text-slate-800 truncate">{{ exam.title }}</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">{{ exam.subtitle }} · {{ exam.date }}</p>
                </div>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-wider shrink-0" :class="exam.statusColor">
                  <span v-if="exam.status === 'Graded'" class="w-1 h-1 rounded-full bg-emerald-500"></span>
                  <span v-else class="w-1 h-1 rounded-full bg-orange-500"></span>
                  {{ exam.status }}
                </span>
              </div>
              <div class="grid grid-cols-3 gap-2 mb-3 text-center">
                <div class="bg-slate-50 rounded-xl p-2">
                  <p class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">Total</p>
                  <p class="text-[12px] font-bold text-slate-700">{{ exam.totalMarks }}</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-2">
                  <p class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">Score</p>
                  <p class="text-[12px] font-bold" :class="exam.gradeColor">{{ exam.score !== null ? `${exam.score} (${exam.scorePercent}%)` : '-' }}</p>
                </div>
                <div class="bg-indigo-50 rounded-xl p-2">
                  <p class="text-[9px] text-[#5138ed] font-bold uppercase mb-0.5">Grade</p>
                  <p class="text-[12px] font-bold" :class="exam.gradeColor">{{ exam.grade }}</p>
                </div>
              </div>
              <div class="flex items-center justify-between pt-2 border-t border-slate-50">
                <span class="px-2.5 py-1 text-[10px] font-bold rounded-md" :class="exam.typeColor">{{ exam.type }}</span>
                <div class="flex items-center gap-1.5">
                  <button v-if="exam.status === 'Graded'" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 transition-colors" title="View Result">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  </button>
                  <button v-else class="w-8 h-8 rounded-lg flex items-center justify-center text-orange-500 bg-orange-50 hover:bg-orange-100 transition-colors" title="Pending">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <span class="text-[11px] text-slate-500 font-medium">Showing 1 to 6 of 6 exams</span>
            <div class="flex items-center gap-1">
              <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 transition-colors"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
              <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-[#5138ed] text-white font-bold text-[11px] shadow-sm">1</button>
              <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
            </div>
          </div>
        </div>

        <!-- Info Banner -->
        <div class="bg-blue-50/50 border border-blue-100 rounded-2xl p-4 mt-6 flex items-start gap-3">
          <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0 mt-0.5"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
          <div class="flex flex-col">
            <span class="text-[12px] font-bold text-blue-900">Note:</span>
            <span class="text-[11px] text-blue-700 font-medium">Click on the eye icon in the Actions column to view detailed result, question breakdown, and answer script for each exam.</span>
          </div>
        </div>

      </div>

      <!-- Right Sidebar -->
      <div class="w-full xl:w-[320px] shrink-0 space-y-6">
        
        <!-- Performance Overview Donut Chart -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm">
          <h3 class="text-[13px] font-bold text-slate-800 mb-6">Performance Overview</h3>
          <div class="flex items-center gap-6">
            <div class="relative w-28 h-28 shrink-0">
              <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                <!-- Outer background circle -->
                <circle cx="50" cy="50" r="40" fill="none" stroke="#f1f5f9" stroke-width="12"></circle>
                <!-- Segments (Simulated) -->
                <!-- Excellent 1/6 ~ 16% (50 offset) -->
                <circle cx="50" cy="50" r="40" fill="none" stroke="#10b981" stroke-width="12" stroke-dasharray="41.8 251.3" stroke-dashoffset="0"></circle>
                <!-- Good 2/6 ~ 33% -->
                <circle cx="50" cy="50" r="40" fill="none" stroke="#3b82f6" stroke-width="12" stroke-dasharray="83.7 251.3" stroke-dashoffset="-41.8"></circle>
                <!-- Average 1/6 ~ 16% -->
                <circle cx="50" cy="50" r="40" fill="none" stroke="#f59e0b" stroke-width="12" stroke-dasharray="41.8 251.3" stroke-dashoffset="-125.5"></circle>
                <!-- Note: Total graded is 4. The rest is empty space (pending) -->
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-[15px] font-black text-slate-800 leading-none">78.4%</span>
                <span class="text-[9px] font-bold text-slate-400 mt-1 uppercase text-center w-full block">Average<br>Score</span>
              </div>
            </div>
            
            <div class="flex flex-col gap-3">
              <div class="flex items-start gap-2">
                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1 shrink-0"></div>
                <div class="flex flex-col"><span class="text-[10px] font-bold text-slate-700 leading-none">Excellent (90%+)</span><span class="text-[9px] text-slate-500 mt-0.5">1 Exam</span></div>
              </div>
              <div class="flex items-start gap-2">
                <div class="w-1.5 h-1.5 rounded-full bg-blue-500 mt-1 shrink-0"></div>
                <div class="flex flex-col"><span class="text-[10px] font-bold text-slate-700 leading-none">Good (70% - 89%)</span><span class="text-[9px] text-slate-500 mt-0.5">2 Exams</span></div>
              </div>
              <div class="flex items-start gap-2">
                <div class="w-1.5 h-1.5 rounded-full bg-amber-500 mt-1 shrink-0"></div>
                <div class="flex flex-col"><span class="text-[10px] font-bold text-slate-700 leading-none">Average (50% - 69%)</span><span class="text-[9px] text-slate-500 mt-0.5">1 Exam</span></div>
              </div>
              <div class="flex items-start gap-2">
                <div class="w-1.5 h-1.5 rounded-full bg-rose-500 mt-1 shrink-0"></div>
                <div class="flex flex-col"><span class="text-[10px] font-bold text-slate-700 leading-none">Below Average (&lt;50%)</span><span class="text-[9px] text-slate-500 mt-0.5">0 Exams</span></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Score Trend Chart -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm">
          <h3 class="text-[13px] font-bold text-slate-800 mb-6">Score Trend</h3>
          
          <div class="relative h-[160px] w-full flex items-end justify-between pt-6 pb-2">
            <!-- Y-axis labels -->
            <div class="absolute left-0 top-0 bottom-6 flex flex-col justify-between text-[9px] font-bold text-slate-400 h-[140px]">
              <span>100%</span>
              <span>75%</span>
              <span>50%</span>
              <span>25%</span>
              <span>0%</span>
            </div>
            
            <!-- Grid lines -->
            <div class="absolute left-8 right-0 top-0 bottom-6 flex flex-col justify-between h-[140px]">
              <div class="w-full h-px border-t border-dashed border-slate-200"></div>
              <div class="w-full h-px border-t border-dashed border-slate-200"></div>
              <div class="w-full h-px border-t border-dashed border-slate-200"></div>
              <div class="w-full h-px border-t border-dashed border-slate-200"></div>
              <div class="w-full h-px bg-slate-200"></div>
            </div>

            <!-- Chart Line & Points -->
            <div class="absolute left-8 right-0 top-0 bottom-6 h-[140px] pointer-events-none">
              <svg class="w-full h-full overflow-visible" preserveAspectRatio="none">
                <path d="M 20,11 L 80,33 L 140,49 L 200,16" fill="none" stroke="#5138ed" stroke-width="2.5" />
                <circle cx="20" cy="11" r="3.5" fill="#5138ed" />
                <circle cx="80" cy="33" r="3.5" fill="#5138ed" />
                <circle cx="140" cy="49" r="3.5" fill="#5138ed" />
                <circle cx="200" cy="16" r="3.5" fill="#5138ed" />
              </svg>
              <!-- Value Labels -->
              <div class="absolute -top-4 font-bold text-[10px] text-slate-800" style="left: 14px;">92%</div>
              <div class="absolute top-[20px] font-bold text-[10px] text-slate-800" style="left: 74px;">76%</div>
              <div class="absolute top-[36px] font-bold text-[10px] text-slate-800" style="left: 134px;">65%</div>
              <div class="absolute top-[3px] font-bold text-[10px] text-slate-800" style="left: 194px;">88%</div>
            </div>
            
            <!-- X-axis labels -->
            <div class="absolute left-8 right-0 bottom-0 flex justify-between px-4">
              <span class="text-[9px] font-bold text-slate-500">Quiz 1</span>
              <span class="text-[9px] font-bold text-slate-500">Quiz 2</span>
              <span class="text-[9px] font-bold text-slate-500">Quiz 3</span>
              <span class="text-[9px] font-bold text-slate-500">Mid Exam</span>
            </div>
          </div>
        </div>

        <!-- Exam Summary -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm">
          <h3 class="text-[13px] font-bold text-slate-800 mb-4">Exam Summary</h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between"><span class="text-[11px] font-bold text-slate-500">Total Marks Obtained</span><span class="text-[12px] font-bold text-slate-800">161 / 205</span></div>
            <div class="flex items-center justify-between"><span class="text-[11px] font-bold text-slate-500">Average Score</span><span class="text-[12px] font-bold text-slate-800">78.4%</span></div>
            <div class="flex items-center justify-between"><span class="text-[11px] font-bold text-slate-500">Highest Score</span><span class="text-[12px] font-bold text-slate-800">92%</span></div>
            <div class="flex items-center justify-between"><span class="text-[11px] font-bold text-slate-500">Lowest Score</span><span class="text-[12px] font-bold text-slate-800">65%</span></div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm">
          <h3 class="text-[13px] font-bold text-slate-800 mb-4">Quick Actions</h3>
          <div class="space-y-1">
            <button class="w-full flex items-center justify-center gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-slate-50 hover:border-slate-200 transition-colors group">
              <div class="text-[#5138ed]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg></div>
              <span class="text-[12px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">View Detailed Result</span>
            </button>
            <button class="w-full flex items-center justify-center gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-slate-50 hover:border-slate-200 transition-colors group">
              <div class="text-[#5138ed]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg></div>
              <span class="text-[12px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">Download Result Report</span>
            </button>
            <button class="w-full flex items-center justify-center gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-slate-50 hover:border-slate-200 transition-colors group">
              <div class="text-[#5138ed]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>
              <span class="text-[12px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">View Answer Scripts</span>
            </button>
            <button class="w-full flex items-center justify-center gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-slate-50 hover:border-slate-200 transition-colors group">
              <div class="text-[#5138ed]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg></div>
              <span class="text-[12px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">Print Result Summary</span>
            </button>
          </div>
        </div>

      </div>

    </div>
  </div>
</template>
