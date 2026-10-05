<script setup lang="ts">
const schedules = [
  {
    id: 1,
    title: 'Database Systems Mid Exam',
    subtitle: 'Mid Term Examination',
    courseName: 'Database Systems',
    courseCode: 'CS 304',
    type: 'Mid Exam',
    typeClass: 'bg-indigo-50 text-[#5138ed]',
    date: 'May 25, 2025',
    time: '09:00 AM',
    duration: '1h 30m',
    status: 'Upcoming',
    statusClass: 'bg-indigo-50 text-[#5138ed]',
    icon: 'text-[#5138ed]'
  },
  {
    id: 2,
    title: 'Web Programming Quiz 1',
    subtitle: 'First Quiz',
    courseName: 'Web Programming',
    courseCode: 'CS 206',
    type: 'Quiz',
    typeClass: 'bg-emerald-50 text-emerald-600',
    date: 'May 20, 2025',
    time: '09:00 AM',
    duration: '45 min',
    status: 'Upcoming',
    statusClass: 'bg-indigo-50 text-[#5138ed]',
    icon: 'text-emerald-500'
  },
  {
    id: 3,
    title: 'Data Structures Final Exam',
    subtitle: 'Final Examination',
    courseName: 'Data Structures',
    courseCode: 'CS 202',
    type: 'Final Exam',
    typeClass: 'bg-orange-50 text-orange-600',
    date: 'May 18, 2025',
    time: '02:00 PM',
    duration: '2h 00m',
    status: 'Ongoing',
    statusClass: 'bg-emerald-50 text-emerald-600',
    icon: 'text-orange-500'
  },
  {
    id: 4,
    title: 'Operating Systems Mid Exam',
    subtitle: 'Mid Term Examination',
    courseName: 'Operating Systems',
    courseCode: 'CS 305',
    type: 'Mid Exam',
    typeClass: 'bg-indigo-50 text-[#5138ed]',
    date: 'May 12, 2025',
    time: '11:00 AM',
    duration: '1h 30m',
    status: 'Completed',
    statusClass: 'bg-slate-100 text-slate-600',
    icon: 'text-[#5138ed]'
  },
  {
    id: 5,
    title: 'Software Engineering Quiz 1',
    subtitle: 'First Quiz',
    courseName: 'Software Engineering',
    courseCode: 'SE 302',
    type: 'Quiz',
    typeClass: 'bg-emerald-50 text-emerald-600',
    date: 'May 10, 2025',
    time: '09:00 AM',
    duration: '30 min',
    status: 'Completed',
    statusClass: 'bg-slate-100 text-slate-600',
    icon: 'text-emerald-500'
  },
  {
    id: 6,
    title: 'Computer Networks Mid Exam',
    subtitle: 'Mid Term Examination',
    courseName: 'Computer Networks',
    courseCode: 'CS 307',
    type: 'Mid Exam',
    typeClass: 'bg-indigo-50 text-[#5138ed]',
    date: 'May 08, 2025',
    time: '10:00 AM',
    duration: '1h 30m',
    status: 'Completed',
    statusClass: 'bg-slate-100 text-slate-600',
    icon: 'text-[#5138ed]'
  },
  {
    id: 7,
    title: 'Artificial Intelligence Quiz 1',
    subtitle: 'First Quiz',
    courseName: 'Artificial Intelligence',
    courseCode: 'CS 405',
    type: 'Quiz',
    typeClass: 'bg-emerald-50 text-emerald-600',
    date: 'May 05, 2025',
    time: '09:00 AM',
    duration: '30 min',
    status: 'Completed',
    statusClass: 'bg-slate-100 text-slate-600',
    icon: 'text-[#5138ed]'
  },
  {
    id: 8,
    title: 'Theory of Computation Final',
    subtitle: 'Final Examination',
    courseName: 'Theory of Computation',
    courseCode: 'CS 404',
    type: 'Final Exam',
    typeClass: 'bg-orange-50 text-orange-600',
    date: 'Apr 28, 2025',
    time: '01:00 PM',
    duration: '2h 00m',
    status: 'Completed',
    statusClass: 'bg-slate-100 text-slate-600',
    icon: 'text-rose-500'
  }
]
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl shadow-sm mb-6 overflow-hidden">
    
    <!-- Filter Bar -->
    <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-wrap items-center gap-3 sm:gap-4">
      
      <!-- Search -->
      <div class="relative flex-1 min-w-[200px]">
        <svg class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        <input type="text" placeholder="Search exams, courses..." class="w-full pl-11 pr-4 py-2.5 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-700 placeholder:text-slate-400 min-h-[44px]">
      </div>

      <!-- Course Dropdown -->
      <div class="relative min-w-[140px] flex-1 sm:flex-none">
        <select class="w-full appearance-none pl-4 pr-10 py-2.5 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-700 bg-white cursor-pointer min-h-[44px]">
          <option>All Courses</option>
        </select>
        <svg class="w-4 h-4 text-slate-400 absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
      </div>

      <!-- Status Dropdown -->
      <div class="relative min-w-[130px] flex-1 sm:flex-none">
        <select class="w-full appearance-none pl-4 pr-10 py-2.5 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-700 bg-white cursor-pointer min-h-[44px]">
          <option>All Status</option>
        </select>
        <svg class="w-4 h-4 text-slate-400 absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
      </div>

      <!-- Date Range -->
      <div class="relative min-w-[180px] w-full sm:w-auto">
        <input type="text" placeholder="Select date range" class="w-full pl-4 pr-10 py-2.5 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-700 placeholder:text-slate-500 cursor-pointer min-h-[44px]">
        <svg class="w-4 h-4 text-slate-400 absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
      </div>

      <!-- Filter Button -->
      <button class="min-h-[44px] px-5 py-2.5 border border-[#5138ed] text-[#5138ed] font-bold text-[13px] rounded-xl hover:bg-indigo-50 transition-colors flex items-center justify-center gap-2 w-full sm:w-auto">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
        Filter
      </button>
    </div>

    <!-- Desktop Table -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-slate-50/50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
            <th class="px-6 py-4">Exam Title</th>
            <th class="px-6 py-4">Course</th>
            <th class="px-6 py-4">Exam Type</th>
            <th class="px-6 py-4">Date & Time</th>
            <th class="px-6 py-4">Duration</th>
            <th class="px-6 py-4">Status</th>
            <th class="px-6 py-4 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="exam in schedules" :key="exam.id" class="hover:bg-slate-50/50 transition-colors group">
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div :class="`w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 bg-slate-50 border border-slate-100`">
                  <svg :class="`w-4 h-4 ${exam.icon}`" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div>
                  <span class="block text-[13px] font-bold text-slate-800">{{ exam.title }}</span>
                  <span class="block text-[11px] font-medium text-slate-500 mt-0.5">{{ exam.subtitle }}</span>
                </div>
              </div>
            </td>
            <td class="px-6 py-4">
              <span class="block text-[12px] font-semibold text-slate-700">{{ exam.courseName }}</span>
              <span class="block text-[11px] font-medium text-slate-500 mt-0.5">{{ exam.courseCode }}</span>
            </td>
            <td class="px-6 py-4">
              <span :class="`inline-block px-2.5 py-1 text-[10px] font-bold rounded-lg ${exam.typeClass}`">
                {{ exam.type }}
              </span>
            </td>
            <td class="px-6 py-4">
              <span class="block text-[12px] font-semibold text-slate-700">{{ exam.date }}</span>
              <span class="block text-[11px] font-medium text-slate-500 mt-0.5">{{ exam.time }}</span>
            </td>
            <td class="px-6 py-4">
              <span class="text-[12px] font-semibold text-slate-700">{{ exam.duration }}</span>
            </td>
            <td class="px-6 py-4">
              <span :class="`inline-block px-2.5 py-1 text-[10px] font-bold rounded-lg ${exam.statusClass}`">
                {{ exam.status }}
              </span>
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center justify-center gap-1">
                <button class="p-1.5 text-slate-400 hover:text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors" title="View details">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                </button>
                <button v-if="exam.status !== 'Completed'" class="p-1.5 text-slate-400 hover:text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors" title="Edit schedule">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                </button>
                <button v-if="exam.status === 'Completed'" class="p-1.5 text-slate-400 hover:text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors" title="View results">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </button>
                <button class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile Card View -->
    <div class="md:hidden divide-y divide-slate-100">
      <div v-for="exam in schedules" :key="exam.id" class="p-4">
        <div class="flex items-start gap-3 mb-3">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 bg-slate-50 border border-slate-100">
            <svg :class="`w-4 h-4 ${exam.icon}`" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[13px] font-bold text-slate-800 truncate">{{ exam.title }}</p>
            <p class="text-[11px] text-slate-500 mt-0.5">{{ exam.courseName }} · {{ exam.courseCode }}</p>
          </div>
          <span :class="`inline-block px-2.5 py-1 text-[10px] font-bold rounded-lg shrink-0 ${exam.statusClass}`">{{ exam.status }}</span>
        </div>
        <div class="grid grid-cols-3 gap-2 mb-3 text-center">
          <div class="bg-slate-50 rounded-xl p-2">
            <p class="text-[10px] text-slate-400 font-semibold mb-0.5">Date</p>
            <p class="text-[11px] font-bold text-slate-700">{{ exam.date }}</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-2">
            <p class="text-[10px] text-slate-400 font-semibold mb-0.5">Time</p>
            <p class="text-[11px] font-bold text-slate-700">{{ exam.time }}</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-2">
            <p class="text-[10px] text-slate-400 font-semibold mb-0.5">Duration</p>
            <p class="text-[11px] font-bold text-slate-700">{{ exam.duration }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
          <span :class="`inline-block px-2.5 py-1 text-[10px] font-bold rounded-lg ${exam.typeClass}`">{{ exam.type }}</span>
          <div class="flex items-center gap-1.5 ml-auto">
            <button class="w-8 h-8 flex items-center justify-center text-[#5138ed] bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
            </button>
            <button v-if="exam.status !== 'Completed'" class="w-8 h-8 flex items-center justify-center text-amber-500 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
            </button>
            <button v-if="exam.status === 'Completed'" class="w-8 h-8 flex items-center justify-center text-emerald-500 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div class="p-4 sm:p-6 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
      <span class="text-[12px] font-medium text-slate-500">Showing 1 to 8 of 12 scheduled exams</span>
      <div class="flex items-center gap-1">
        <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition-colors disabled:opacity-50" disabled>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <button class="w-9 h-9 flex items-center justify-center rounded-lg bg-[#5138ed] text-white font-bold text-[12px] shadow-sm">1</button>
        <button class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-slate-50 text-slate-600 font-bold text-[12px] transition-colors">2</button>
        <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
      </div>
    </div>

  </div>
</template>
