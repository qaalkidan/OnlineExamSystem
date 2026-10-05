<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const activeTab = ref('overview')
const tabs = [
  { key: 'overview', label: 'Overview', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
  { key: 'exam-history', label: 'Exam History', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
  { key: 'attendance', label: 'Attendance', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { key: 'assignments', label: 'Assignments', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { key: 'activity-log', label: 'Activity Log', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
]

// For the trend chart we'll use a simple SVG graph visualization
const trendData = [92, 76, 88, 65, 72]
const maxData = 100
const minData = 0
</script>

<template>
  <div class="max-w-[1500px] mx-auto px-4 py-2">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-800">Student Profile</h1>
        <p class="text-[13px] text-slate-500 mt-1">View detailed information, academic progress, and examination history.</p>
        <div class="flex flex-wrap items-center gap-2 text-[12px] text-slate-400 mt-2">
          <router-link to="/instructor/dashboard" class="hover:text-[#5138ed] transition-colors">Dashboard</router-link>
          <span>&gt;</span>
          <router-link to="/instructor/students" class="hover:text-[#5138ed] transition-colors">Students</router-link>
          <span>&gt;</span>
          <span class="text-slate-600 font-medium">Student Profile</span>
        </div>
      </div>
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <button @click="router.push('/instructor/students')" class="min-h-[44px] flex items-center justify-center gap-2 px-4 py-2 border border-slate-200 text-slate-700 text-sm font-bold rounded-xl hover:bg-slate-50 transition-colors bg-white">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Students
        </button>
        <button class="min-h-[44px] flex items-center justify-center gap-2 px-4 py-2 bg-[#5138ed] text-white text-sm font-bold rounded-xl shadow-sm hover:bg-[#4530d1] transition-colors">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
          Send Message
        </button>
      </div>
    </div>

    <!-- Top Card -->
    <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-6 shadow-sm mb-6 flex flex-col md:flex-row gap-4 sm:gap-6 md:gap-12 lg:gap-20">
      
      <!-- Student Info -->
      <div class="flex items-center gap-4 sm:gap-6">
        <div class="relative w-16 h-16 sm:w-20 sm:h-20 shrink-0">
          <img src="https://i.pravatar.cc/150?u=a042581f4e290267041" alt="Avatar" class="w-full h-full object-cover rounded-full shadow-sm" />
          <div class="absolute bottom-0 right-0 w-4 h-4 bg-emerald-500 border-2 border-white rounded-full"></div>
        </div>
        <div class="flex flex-col">
          <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
            <h2 class="text-lg sm:text-xl font-bold text-slate-800">Selamawit Getachew</h2>
            <span class="flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-600">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active Student
            </span>
          </div>
          <div class="flex flex-wrap items-center gap-4 sm:gap-6 text-[12px] font-medium text-slate-500">
            <div class="flex items-center gap-2"><div class="text-slate-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg></div><div class="flex flex-col"><span class="text-[9px] text-slate-400 font-bold uppercase">ID Number</span><span class="text-slate-700">WU/2021/CS/001</span></div></div>
            <div class="flex items-center gap-2"><div class="text-slate-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg></div><div class="flex flex-col"><span class="text-[9px] text-slate-400 font-bold uppercase">Email</span><span class="text-slate-700 truncate max-w-[160px] sm:max-w-none">selamawit.get@wollo.edu.et</span></div></div>
            <div class="flex items-center gap-2"><div class="text-slate-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg></div><div class="flex flex-col"><span class="text-[9px] text-slate-400 font-bold uppercase">Phone</span><span class="text-slate-700">+251 91 234 5678</span></div></div>
          </div>
        </div>
      </div>
      
      <div class="w-px bg-slate-100 hidden md:block"></div>

      <!-- Student Metadata Grid -->
      <div class="grid grid-cols-2 gap-x-6 sm:gap-x-8 gap-y-3 sm:gap-y-4">
        <div class="flex items-center gap-2 text-[12px]"><div class="text-slate-400 w-4"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg></div><span class="text-slate-500 w-16 font-medium">Program</span><span class="text-slate-800 font-bold">BSc in Computer Science</span></div>
        <div class="flex items-center gap-2 text-[12px]"><div class="text-slate-400 w-4"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div><span class="text-slate-500 w-16 font-medium">Year</span><span class="text-slate-800 font-bold">3rd Year</span></div>
        <div class="flex items-center gap-2 text-[12px]"><div class="text-slate-400 w-4"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg></div><span class="text-slate-500 w-16 font-medium">Section</span><span class="text-slate-800 font-bold">CS-304-A</span></div>
        <div class="flex items-center gap-2 text-[12px]"><div class="text-slate-400 w-4"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></div><span class="text-slate-500 w-16 font-medium">Acad. Year</span><span class="text-slate-800 font-bold">2025/2026</span></div>
      </div>

    </div>

    <!-- Main Layout -->
    <div class="flex flex-col xl:flex-row gap-6">
      
      <!-- Left Column -->
      <div class="flex-1 min-w-0">
        
        <!-- Tabs -->
        <div class="flex items-center gap-2 border-b border-slate-100 mb-6 overflow-x-auto">
          <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
            class="flex items-center gap-2 px-4 py-3 text-[13px] font-bold transition-colors relative whitespace-nowrap"
            :class="activeTab === tab.key ? 'text-[#5138ed]' : 'text-slate-500 hover:text-slate-800'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon"></path></svg>
            {{ tab.label }}
            <div v-if="activeTab === tab.key" class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#5138ed] rounded-t-full"></div>
          </button>
        </div>

        <!-- Overview Content -->
        <div v-if="activeTab === 'overview'" class="space-y-6">
          
          <!-- Quick Stats Row -->
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm relative overflow-hidden group">
              <div class="absolute bottom-0 left-0 right-0 h-1 bg-[#5138ed]"></div>
              <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center text-[#5138ed] shrink-0"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg></div>
                <span class="text-[12px] font-bold text-slate-500">Exams Taken</span>
              </div>
              <div class="flex flex-col">
                <span class="text-3xl font-black text-slate-800 mb-1">4<span class="text-xl text-slate-400">/6</span></span>
                <span class="text-[11px] font-medium text-slate-400">Exams Completed</span>
              </div>
            </div>

            <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm relative overflow-hidden group">
              <div class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-500"></div>
              <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 shrink-0"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                <span class="text-[12px] font-bold text-slate-500">Average Score</span>
              </div>
              <div class="flex flex-col">
                <span class="text-3xl font-black text-slate-800 mb-1">78.4%</span>
                <span class="text-[11px] font-medium text-slate-400">Across all exams</span>
              </div>
            </div>

            <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm relative overflow-hidden group">
              <div class="absolute bottom-0 left-0 right-0 h-1 bg-orange-400"></div>
              <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-orange-50 flex items-center justify-center text-orange-500 shrink-0"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg></div>
                <span class="text-[12px] font-bold text-slate-500">Average Attendance</span>
              </div>
              <div class="flex flex-col">
                <span class="text-3xl font-black text-slate-800 mb-1">86.7%</span>
                <span class="text-[11px] font-medium text-slate-400">Overall attendance</span>
              </div>
            </div>

            <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm relative overflow-hidden group">
              <div class="absolute bottom-0 left-0 right-0 h-1 bg-blue-500"></div>
              <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 shrink-0"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>
                <span class="text-[12px] font-bold text-slate-500">Overall Grade</span>
              </div>
              <div class="flex flex-col">
                <span class="text-3xl font-black text-[#5138ed] mb-1">B+</span>
                <span class="text-[11px] font-medium text-slate-400">Good Performance</span>
              </div>
            </div>
          </div>

          <!-- Bottom Grid: Personal Info & Statistics -->
          <div class="flex flex-col md:flex-row gap-6">
            
            <!-- Personal Information -->
            <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm flex-1">
              <h3 class="text-[14px] font-bold text-slate-800 mb-5 border-b border-slate-50 pb-3">Personal Information</h3>
              <div class="space-y-4 text-[12px]">
                <div class="flex items-center"><div class="flex items-center gap-2 w-1/2 text-slate-500 font-medium"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>Full Name</div><div class="w-1/2 text-slate-800 font-bold">Selamawit Getachew</div></div>
                <div class="flex items-center"><div class="flex items-center gap-2 w-1/2 text-slate-500 font-medium"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>Date of Birth</div><div class="w-1/2 text-slate-800 font-bold">March 12, 2003</div></div>
                <div class="flex items-center"><div class="flex items-center gap-2 w-1/2 text-slate-500 font-medium"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>Gender</div><div class="w-1/2 text-slate-800 font-bold">Female</div></div>
                <div class="flex items-center"><div class="flex items-center gap-2 w-1/2 text-slate-500 font-medium"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>Department</div><div class="w-1/2 text-slate-800 font-bold">Computer Science</div></div>
                <div class="flex items-center"><div class="flex items-center gap-2 w-1/2 text-slate-500 font-medium"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>Program</div><div class="w-1/2 text-slate-800 font-bold">BSc in Computer Science</div></div>
                <div class="flex items-center"><div class="flex items-center gap-2 w-1/2 text-slate-500 font-medium"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>Admission Year</div><div class="w-1/2 text-slate-800 font-bold">2021</div></div>
                <div class="flex items-center"><div class="flex items-center gap-2 w-1/2 text-slate-500 font-medium"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>University Email</div><div class="w-1/2 text-slate-800 font-bold">selamawit.get@wollo.edu.et</div></div>
                <div class="flex items-center"><div class="flex items-center gap-2 w-1/2 text-slate-500 font-medium"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>Phone Number</div><div class="w-1/2 text-slate-800 font-bold">+251 91 234 5678</div></div>
                <div class="flex items-start"><div class="flex items-center gap-2 w-1/2 text-slate-500 font-medium"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>Address</div><div class="w-1/2 text-slate-800 font-bold">Kombolcha, Wollo, Ethiopia</div></div>
              </div>
            </div>
            
            <!-- Statistics -->
            <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm flex-1">
              <h3 class="text-[14px] font-bold text-slate-800 mb-5 border-b border-slate-50 pb-3">Statistics</h3>
              
              <div class="space-y-5">
                <div class="flex flex-col gap-1.5">
                  <div class="flex justify-between items-center"><span class="text-[12px] font-bold text-slate-700">Exams Completed</span><span class="text-[12px] font-bold text-slate-800">4 / 6</span></div>
                  <div class="h-1.5 w-full bg-slate-100 rounded-full"><div class="h-full bg-[#5138ed] rounded-full" style="width: 66.7%"></div></div>
                </div>
                <div class="flex flex-col gap-1.5">
                  <div class="flex justify-between items-center"><span class="text-[12px] font-bold text-slate-700">Exams Pending</span><span class="text-[12px] font-bold text-slate-800">2 / 6</span></div>
                  <div class="h-1.5 w-full bg-slate-100 rounded-full"><div class="h-full bg-orange-500 rounded-full" style="width: 33.3%"></div></div>
                </div>
                <div class="flex flex-col gap-1.5 mt-2">
                  <div class="flex justify-between items-center"><span class="text-[12px] font-bold text-slate-700">Average Score</span><span class="text-[12px] font-bold text-slate-800">78.4%</span></div>
                  <div class="h-1.5 w-full bg-slate-100 rounded-full"><div class="h-full bg-emerald-500 rounded-full" style="width: 78.4%"></div></div>
                </div>
                <div class="flex flex-col gap-1.5">
                  <div class="flex justify-between items-center"><span class="text-[12px] font-bold text-slate-700">Attendance Rate</span><span class="text-[12px] font-bold text-slate-800">86.7%</span></div>
                  <div class="h-1.5 w-full bg-slate-100 rounded-full"><div class="h-full bg-blue-600 rounded-full" style="width: 86.7%"></div></div>
                </div>
                <div class="flex flex-col gap-1.5 mt-2">
                  <div class="flex justify-between items-center"><span class="text-[12px] font-bold text-slate-700">Assignments Submitted</span><span class="text-[12px] font-bold text-slate-800">9 / 11</span></div>
                  <div class="h-1.5 w-full bg-slate-100 rounded-full"><div class="h-full bg-emerald-500 rounded-full" style="width: 81.8%"></div></div>
                </div>
              </div>
            </div>

          </div>
          
          <!-- Notes -->
          <div class="bg-amber-50/50 border border-amber-100/50 rounded-2xl p-6 relative">
            <div class="flex items-center gap-2 mb-4">
              <div class="w-6 h-6 rounded-md bg-amber-100 text-amber-500 flex items-center justify-center"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></div>
              <h3 class="text-[13px] font-bold text-amber-900">Notes</h3>
            </div>
            <button class="absolute top-5 right-5 w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm text-slate-400 hover:text-slate-600 border border-slate-100">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
            </button>
            <p class="text-[13px] text-slate-700 leading-relaxed font-medium mb-4 pr-10">
              Selamawit is an active and participative student. She shows good understanding of the subject and consistently performs well in assignments and exams. Encourage her to improve attendance in practical sessions.
            </p>
            <div class="flex flex-col">
              <span class="text-[12px] font-bold text-slate-800">— Dr. Abebe Kebede</span>
              <span class="text-[10px] font-medium text-slate-400 mt-0.5">Last updated: May 23, 2025</span>
            </div>
          </div>

        </div>

      </div>

      <!-- Right Sidebar -->
      <div class="w-full xl:w-[320px] shrink-0 space-y-6">
        
        <!-- Exam Performance Trend -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm">
          <h3 class="text-[13px] font-bold text-slate-800 mb-6">Exam Performance Trend</h3>
          
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
                <!-- Example Path matching trendData [92, 76, 88, 65, 72] -->
                <path d="M 20,11 L 80,33 L 140,16 L 200,49 L 260,39" fill="none" stroke="#5138ed" stroke-width="2.5" />
                
                <circle cx="20" cy="11" r="3.5" fill="#5138ed" />
                <circle cx="80" cy="33" r="3.5" fill="#5138ed" />
                <circle cx="140" cy="16" r="3.5" fill="#5138ed" />
                <circle cx="200" cy="49" r="3.5" fill="#5138ed" />
                <circle cx="260" cy="39" r="3.5" fill="#5138ed" />
              </svg>
              <!-- Value Labels -->
              <div class="absolute -top-4 font-bold text-[10px] text-slate-600" style="left: 14px;">92%</div>
              <div class="absolute top-[20px] font-bold text-[10px] text-slate-600" style="left: 74px;">76%</div>
              <div class="absolute top-[4px] font-bold text-[10px] text-slate-600" style="left: 134px;">88%</div>
              <div class="absolute top-[36px] font-bold text-[10px] text-slate-600" style="left: 194px;">65%</div>
              <div class="absolute top-[26px] font-bold text-[10px] text-slate-600" style="left: 254px;">72%</div>
            </div>
            
            <!-- X-axis labels -->
            <div class="absolute left-8 right-0 bottom-0 flex justify-between px-2">
              <span class="text-[9px] font-bold text-slate-500">Quiz 1</span>
              <span class="text-[9px] font-bold text-slate-500">Quiz 2</span>
              <span class="text-[9px] font-bold text-slate-500">Mid Exam</span>
              <span class="text-[9px] font-bold text-slate-500">Quiz 3</span>
              <span class="text-[9px] font-bold text-slate-500">Final Exam</span>
            </div>
          </div>
        </div>

        <!-- Recent Exams -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm">
          <h3 class="text-[13px] font-bold text-slate-800 mb-4">Recent Exams</h3>
          <div class="space-y-4 mb-5">
            <div class="flex items-center justify-between group cursor-pointer border-b border-slate-50 pb-3">
              <div class="flex flex-col"><span class="text-[12px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors">Database Systems Mid Exam</span><span class="text-[10px] font-medium text-slate-400">May 10, 2025</span></div>
              <div class="flex items-center gap-2"><span class="text-[12px] font-bold text-emerald-500">88%</span><svg class="w-3.5 h-3.5 text-slate-300 group-hover:text-[#5138ed] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></div>
            </div>
            <div class="flex items-center justify-between group cursor-pointer border-b border-slate-50 pb-3">
              <div class="flex flex-col"><span class="text-[12px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors">Database Quiz 3</span><span class="text-[10px] font-medium text-slate-400">Apr 28, 2025</span></div>
              <div class="flex items-center gap-2"><span class="text-[12px] font-bold text-orange-500">65%</span><svg class="w-3.5 h-3.5 text-slate-300 group-hover:text-[#5138ed] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></div>
            </div>
            <div class="flex items-center justify-between group cursor-pointer border-b border-slate-50 pb-3">
              <div class="flex flex-col"><span class="text-[12px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors">Database Quiz 2</span><span class="text-[10px] font-medium text-slate-400">Apr 15, 2025</span></div>
              <div class="flex items-center gap-2"><span class="text-[12px] font-bold text-orange-400">76%</span><svg class="w-3.5 h-3.5 text-slate-300 group-hover:text-[#5138ed] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></div>
            </div>
            <div class="flex items-center justify-between group cursor-pointer pb-1">
              <div class="flex flex-col"><span class="text-[12px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors">Database Quiz 1</span><span class="text-[10px] font-medium text-slate-400">Apr 02, 2025</span></div>
              <div class="flex items-center gap-2"><span class="text-[12px] font-bold text-emerald-500">92%</span><svg class="w-3.5 h-3.5 text-slate-300 group-hover:text-[#5138ed] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></div>
            </div>
          </div>
          <button class="w-full py-2.5 bg-indigo-50 text-[#5138ed] text-[12px] font-bold rounded-xl hover:bg-indigo-100 transition-colors">View All Exams</button>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm">
          <h3 class="text-[13px] font-bold text-slate-800 mb-4">Quick Actions</h3>
          <div class="space-y-1">
            <button class="w-full flex items-center justify-center gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-slate-50 hover:border-slate-200 transition-colors group">
              <div class="text-slate-400 group-hover:text-[#5138ed] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg></div>
              <span class="text-[12px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">View Exam Results</span>
            </button>
            <button class="w-full flex items-center justify-center gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-slate-50 hover:border-slate-200 transition-colors group">
              <div class="text-slate-400 group-hover:text-[#5138ed] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>
              <span class="text-[12px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">View Attendance</span>
            </button>
            <button class="w-full flex items-center justify-center gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-slate-50 hover:border-slate-200 transition-colors group">
              <div class="text-slate-400 group-hover:text-[#5138ed] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg></div>
              <span class="text-[12px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">Download Report</span>
            </button>
            <button class="w-full flex items-center justify-center gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-slate-50 hover:border-slate-200 transition-colors group">
              <div class="text-slate-400 group-hover:text-[#5138ed] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg></div>
              <span class="text-[12px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">Send Message</span>
            </button>
          </div>
        </div>

      </div>

    </div>
  </div>
</template>
