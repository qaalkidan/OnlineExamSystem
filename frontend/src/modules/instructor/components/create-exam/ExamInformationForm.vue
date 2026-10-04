<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useCreateExamStore } from '../../store/createExamStore'
import { useAuthStore } from '../../../auth/store/authStore'
import apiClient from '../../../../core/api/apiClient'

const formStore = useCreateExamStore()
const authStore = useAuthStore()

const localExamType = ref('Mid Exam')
const isLoadingCourse = ref(false)

onMounted(async () => {
  if (['Mid Exam', 'Final Exam', 'Quiz', ''].includes(formStore.examType)) {
    localExamType.value = formStore.examType || 'Mid Exam'
  } else {
    localExamType.value = 'Other'
  }

  // Pre-fill today's date if date is empty
  if (!formStore.scheduledDate) {
    const today = new Date().toISOString().split('T')[0]
    formStore.scheduledDate = today
  }
  
  // Set course code, course name, and section from authenticated user by fetching actual profile
  isLoadingCourse.value = true
  try {
    const res = await apiClient.get('/instructor/me')
    const data = res.data?.data
    if (data) {
      if (data.course_code) formStore.courseCode = data.course_code
      if (data.course_name) formStore.courseName = data.course_name
      if (data.section) {
        const s = data.section.trim()
        if (s === 'Section B' || s === 'B') {
          formStore.section = 'Section B'
        } else if (s === 'Section A' || s === 'A') {
          formStore.section = 'Section A'
        } else {
          formStore.section = 'Both'
        }
      }
    }
  } catch (e) {
    if (!formStore.courseCode) {
      formStore.courseCode = authStore.user?.course_code || 'NT-00'
    }
    if (!formStore.courseName) {
      formStore.courseName = authStore.user?.course_name || 'Networking'
    }
    if (!formStore.section) {
      const s = authStore.user?.section || 'Section B'
      formStore.section = s.includes('B') ? 'Section B' : (s.includes('A') ? 'Section A' : 'Both')
    }
  } finally {
    isLoadingCourse.value = false
  }
})

watch(localExamType, (newVal) => {
  if (newVal !== 'Other') {
    formStore.examType = newVal
  } else if (formStore.examType === 'Mid Exam' || formStore.examType === 'Final Exam' || formStore.examType === 'Quiz') {
    formStore.examType = ''
  }
})
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm mb-6">
    <div class="mb-6">
      <h2 class="text-[15px] font-bold text-slate-800">Exam Information</h2>
      <p class="text-[12px] text-slate-500 mt-1">Enter the basic details for your exam.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
      <!-- Exam Title -->
      <div>
        <label class="block text-[13px] font-bold text-slate-700 mb-2">Exam Title <span class="text-rose-500">*</span></label>
        <input
          v-model="formStore.title"
          type="text"
          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]"
          placeholder="e.g., Computer Networking Midterm Exam"
        >
        <p class="text-[11px] text-slate-400 mt-2 font-medium">Example: Networking Midterm Exam</p>
      </div>

      <!-- Course Code & Name -->
      <div>
        <label class="block text-[13px] font-bold text-slate-700 mb-2">Assigned Course</label>
        <input
          :value="formStore.courseName ? `${formStore.courseName} (${formStore.courseCode})` : (formStore.courseCode || 'Networking (NT-00)')"
          type="text"
          readonly
          disabled
          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 font-semibold bg-slate-50 cursor-not-allowed focus:outline-none"
          placeholder="Assigned course code"
        >
        <p class="text-[11px] text-slate-400 mt-2 font-medium">Auto-assigned based on your instructor profile</p>
      </div>

      <!-- Exam Type -->
      <div>
        <label class="block text-[13px] font-bold text-slate-700 mb-2">Exam Type <span class="text-rose-500">*</span></label>
        <div class="flex flex-col gap-3">
          <select v-model="localExamType" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 font-medium focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-[length:10px_10px] bg-no-repeat bg-[position:right_1rem_center]">
            <option value="Mid Exam">Mid Exam</option>
            <option value="Final Exam">Final Exam</option>
            <option value="Quiz">Quiz</option>
            <option value="Other">Other</option>
          </select>
          <input 
            v-if="localExamType === 'Other'" 
            v-model="formStore.examType" 
            type="text" 
            class="w-full border border-indigo-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] shadow-sm shadow-indigo-50" 
            placeholder="Type your custom exam type"
          >
        </div>
        <p class="text-[11px] text-slate-400 mt-2 font-medium">Mid Exam, Final Exam, Quiz, Assignment, etc.</p>
      </div>

      <!-- Total Marks -->
      <div>
        <label class="block text-[13px] font-bold text-slate-700 mb-2">Total Marks <span class="text-rose-500">*</span></label>
        <input v-model="formStore.totalMarks" type="number" min="1" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 font-semibold focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" placeholder="Enter total marks">
        <p class="text-[11px] text-slate-400 mt-2 font-medium">Total marks for the exam</p>
      </div>

      <!-- Description -->
      <div class="md:row-span-3 h-full">
        <label class="block text-[13px] font-bold text-slate-700 mb-2">Description</label>
        <textarea v-model="formStore.description" class="w-full h-full min-h-[120px] border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] resize-none" placeholder="Enter exam description (optional)"></textarea>
        <p class="text-[11px] text-slate-400 mt-2 font-medium">Briefly describe the purpose of this exam</p>
      </div>

      <!-- Section -->
      <div>
        <label class="block text-[13px] font-bold text-slate-700 mb-2">Section <span class="text-rose-500">*</span></label>
        <select v-model="formStore.section" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 font-semibold focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-[length:10px_10px] bg-no-repeat bg-[position:right_1rem_center]">
          <option value="Section A">Section A</option>
          <option value="Section B">Section B</option>
          <option value="Both">Section A and B (Both Sections)</option>
        </select>
        <p class="text-[11px] text-slate-400 mt-2 font-medium">Which students can see this exam</p>
      </div>

      <!-- Passing Marks -->
      <div>
        <label class="block text-[13px] font-bold text-slate-700 mb-2">Passing Marks <span class="text-slate-400 font-normal">(Optional)</span></label>
        <input v-model="formStore.passingMarks" type="number" min="1" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" placeholder="Enter passing marks">
        <p class="text-[11px] text-slate-400 mt-2 font-medium">Minimum marks required to pass</p>
      </div>
    </div>
  </div>

  <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm mb-6">
    <div class="mb-6">
      <h2 class="text-[15px] font-bold text-slate-800">Exam Duration &amp; Schedule</h2>
      <p class="text-[12px] text-slate-500 mt-1">Set the duration and schedule for the exam.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Duration -->
      <div>
        <label class="block text-[13px] font-bold text-slate-700 mb-2">Duration (minutes) <span class="text-rose-500">*</span></label>
        <input v-model="formStore.durationMinutes" type="number" min="1" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] font-semibold">
        <p class="text-[11px] text-slate-400 mt-2 font-medium">Set the total time duration in minutes</p>
      </div>

      <!-- Exam Date -->
      <div>
        <label class="block text-[13px] font-bold text-slate-700 mb-2">Exam Date <span class="text-rose-500">*</span></label>
        <div class="relative">
          <input v-model="formStore.scheduledDate" type="date" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] font-medium">
        </div>
        <p class="text-[11px] text-slate-400 mt-2 font-medium">Scheduled date for the exam</p>
      </div>

      <!-- Start Time -->
      <div>
        <label class="block text-[13px] font-bold text-slate-700 mb-2">Start Time <span class="text-rose-500">*</span></label>
        <div class="relative">
          <input v-model="formStore.scheduledTime" type="time" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] font-medium">
        </div>
        <p class="text-[11px] text-slate-400 mt-2 font-medium">Start time (e.g. 09:00 AM)</p>
      </div>

    </div>
  </div>
</template>
