<script setup lang="ts">
import { ref, computed } from 'vue'
import { useInstructorStudentStore } from '../../store/instructorStudentStore'

const studentStore = useInstructorStudentStore()

const searchQuery = ref('')
const selectedStatus = ref('All Status')

const getScoreColor = (score: number) => {
  if (score >= 80) return 'text-emerald-500'
  if (score >= 60) return 'text-orange-500'
  return 'text-rose-500'
}

const filteredStudents = computed(() => {
  let result = studentStore.students

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(s =>
      s.name.toLowerCase().includes(q) ||
      s.email.toLowerCase().includes(q) ||
      s.id_number.toLowerCase().includes(q)
    )
  }

  if (selectedStatus.value !== 'All Status') {
    result = result.filter(s => s.status === selectedStatus.value)
  }

  return result
})
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl shadow-sm mb-6 overflow-hidden">
    
    <div class="p-4 sm:p-6 sm:pb-4 border-b border-slate-100">
      <h2 class="text-[14px] font-bold text-slate-800 mb-4">Students Directory</h2>
      
      <!-- Filter Bar -->
      <div class="flex flex-wrap items-center gap-3 sm:gap-4">
        
        <!-- Search -->
        <div class="relative flex-1 min-w-[200px]">
          <svg class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
          <input v-model="searchQuery" type="text" placeholder="Search students..." class="w-full min-h-[44px] pl-11 pr-4 py-2 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-700 placeholder:text-slate-400">
        </div>

        <!-- Status Dropdown -->
        <div class="relative flex-1 sm:flex-none min-w-[120px]">
          <select v-model="selectedStatus" class="w-full min-h-[44px] appearance-none pl-4 pr-10 py-2 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-700 bg-white cursor-pointer font-medium">
            <option>All Status</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
          <svg class="w-4 h-4 text-slate-400 absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>

        <!-- Filter Button -->
        <button class="min-h-[44px] px-5 py-2 border border-[#5138ed] text-[#5138ed] font-bold text-[12px] rounded-xl hover:bg-indigo-50 transition-colors flex items-center justify-center gap-2 w-full sm:w-auto">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
          Filter
        </button>
      </div>
    </div>

    <!-- Desktop Table -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-white border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
            <th class="px-6 py-3">Student</th>
            <th class="px-4 py-3">ID Number</th>
            <th class="px-4 py-3">Course</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-center">Average Score</th>
            <th class="px-4 py-3 text-center">Exams Taken</th>
            <th class="px-6 py-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <!-- Loading -->
          <tr v-if="studentStore.isLoading">
            <td colspan="7" class="py-8 text-center text-slate-500">
              <svg class="animate-spin -ml-1 mr-3 h-5 w-5 inline text-[#5138ed]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Loading students...
            </td>
          </tr>

          <!-- Empty -->
          <tr v-else-if="filteredStudents.length === 0">
            <td colspan="7" class="py-8 text-center text-slate-500 font-medium">No students found.</td>
          </tr>

          <!-- Data -->
          <tr v-else v-for="student in filteredStudents" :key="student.id" class="hover:bg-slate-50/50 transition-colors group">
            <td class="px-6 py-3.5">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-[#5138ed] text-[11px] font-bold flex-shrink-0">
                  {{ student.name.charAt(0) }}
                </div>
                <div>
                  <span class="block text-[12px] font-bold text-slate-800">{{ student.name }}</span>
                  <span class="block text-[10px] font-medium text-slate-500 mt-0.5">{{ student.email }}</span>
                </div>
              </div>
            </td>
            <td class="px-4 py-3.5">
              <span class="text-[11px] font-semibold text-slate-600">{{ student.id_number }}</span>
            </td>
            <td class="px-4 py-3.5">
              <div class="flex flex-col">
                <span class="text-[11px] font-medium text-slate-600">{{ student.course_name }}</span>
                <span class="text-[10px] text-slate-400">{{ student.course_code }}</span>
              </div>
            </td>
            <td class="px-4 py-3.5">
              <span class="text-[11px] font-bold" :class="student.status === 'Active' ? 'text-emerald-500' : 'text-rose-500'">
                {{ student.status }}
              </span>
            </td>
            <td class="px-4 py-3.5 text-center">
              <span class="text-[11px] font-black" :class="getScoreColor(student.average_score)">
                {{ student.average_score }}%
              </span>
            </td>
            <td class="px-4 py-3.5 text-center">
              <span class="text-[12px] font-semibold text-slate-600">{{ student.exams_taken }}</span>
            </td>
            <td class="px-6 py-3.5">
              <div class="flex items-center justify-center gap-1">
                <button class="p-1.5 text-slate-400 hover:text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors" title="View Profile">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                </button>
                <button class="p-1.5 text-slate-400 hover:text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors" title="View Progress">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                </button>
                <button class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile Card View -->
    <div class="md:hidden divide-y divide-slate-100">
      <div v-if="studentStore.isLoading" class="py-8 text-center text-slate-500">
        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 inline text-[#5138ed]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Loading students...
      </div>
      <div v-else-if="filteredStudents.length === 0" class="py-8 text-center text-slate-500 font-medium">
        No students found.
      </div>
      <div v-else v-for="student in filteredStudents" :key="student.id" class="p-4">
        <div class="flex items-start gap-3 mb-3">
          <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-[#5138ed] text-[12px] font-bold shrink-0">
            {{ student.name.charAt(0) }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[13px] font-bold text-slate-800 truncate">{{ student.name }}</p>
            <p class="text-[11px] text-slate-500 mt-0.5 truncate">{{ student.email }}</p>
          </div>
          <span class="text-[11px] font-bold shrink-0" :class="student.status === 'Active' ? 'text-emerald-500' : 'text-rose-500'">
            {{ student.status }}
          </span>
        </div>
        <div class="grid grid-cols-3 gap-2 mb-3 text-center">
          <div class="bg-slate-50 rounded-xl p-2">
            <p class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">ID</p>
            <p class="text-[11px] font-bold text-slate-700 truncate">{{ student.id_number }}</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-2">
            <p class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">Score</p>
            <p class="text-[11px] font-black" :class="getScoreColor(student.average_score)">{{ student.average_score }}%</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-2">
            <p class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">Exams</p>
            <p class="text-[11px] font-bold text-slate-700">{{ student.exams_taken }}</p>
          </div>
        </div>
        <div class="flex items-center justify-between pt-2 border-t border-slate-50">
          <p class="text-[11px] text-slate-500 truncate max-w-[200px]">{{ student.course_name }}</p>
          <div class="flex items-center gap-1.5 shrink-0">
            <button class="w-8 h-8 rounded-lg flex items-center justify-center text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 transition-colors" title="View Profile">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
            </button>
            <button class="w-8 h-8 rounded-lg flex items-center justify-center text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 transition-colors" title="View Progress">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div class="p-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
      <span class="text-[11px] font-medium text-slate-500 pl-2">Showing {{ filteredStudents.length }} of {{ studentStore.students.length }} students</span>
      <div class="flex items-center gap-1 pr-2">
        <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-[#5138ed] text-white font-bold text-[11px] shadow-sm">1</button>
      </div>
    </div>

  </div>
</template>
