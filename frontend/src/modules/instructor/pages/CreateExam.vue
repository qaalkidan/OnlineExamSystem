<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useCreateExamStore } from '../store/createExamStore'
import { useInstructorExamStore } from '../store/instructorExamStore'
import { useSemesterLockStore } from '../store/semesterLockStore'

import ExamStepper from '../components/create-exam/ExamStepper.vue'
import ExamInformationForm from '../components/create-exam/ExamInformationForm.vue'
import ExamHelpSidebar from '../components/create-exam/ExamHelpSidebar.vue'

// Step 2 Components
import AddQuestionForm from '../components/create-exam/AddQuestionForm.vue'
import QuestionTypesSidebar from '../components/create-exam/QuestionTypesSidebar.vue'
import QuestionTipsSidebar from '../components/create-exam/QuestionTipsSidebar.vue'
import QuickActionsSidebar from '../components/create-exam/QuickActionsSidebar.vue'

// Step 3 Components
import ExamSettingsForm from '../components/create-exam/ExamSettingsForm.vue'
import SettingsOverviewSidebar from '../components/create-exam/SettingsOverviewSidebar.vue'
import SettingsTipsSidebar from '../components/create-exam/SettingsTipsSidebar.vue'
import SettingsHelpSidebar from '../components/create-exam/SettingsHelpSidebar.vue'

// Step 4 Components
import ReviewPublishForm from '../components/create-exam/ReviewPublishForm.vue'
import ReviewSummarySidebar from '../components/create-exam/ReviewSummarySidebar.vue'
import ReadyPublishSidebar from '../components/create-exam/ReadyPublishSidebar.vue'
import WhatHappensNextSidebar from '../components/create-exam/WhatHappensNextSidebar.vue'

const router = useRouter()
const formStore = useCreateExamStore()
const examStore = useInstructorExamStore()
const lockStore = useSemesterLockStore()

// State-driven step navigation (does not alter URL to prevent component remounting)
const currentStep = ref(1)
const errorMessage = ref('')

onMounted(async () => {
  await lockStore.fetchLockStatus()
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('create exam')
    router.replace('/instructor/exams')
    return
  }
  // Only reset if entering clean without an ongoing draft in progress
  if (!formStore.editingExamId && !formStore.title && formStore.questions.length === 0) {
    formStore.reset()
  }
})

const validateStep1 = (): boolean => {
  if (!formStore.title || !formStore.title.trim()) {
    errorMessage.value = 'Please enter an Exam Title before proceeding.'
    window.scrollTo({ top: 0, behavior: 'smooth' })
    return false
  }
  if (!formStore.examType) {
    errorMessage.value = 'Please select an Exam Type.'
    window.scrollTo({ top: 0, behavior: 'smooth' })
    return false
  }
  if (!formStore.totalMarks || Number(formStore.totalMarks) <= 0) {
    errorMessage.value = 'Please enter valid Total Marks (must be greater than 0).'
    window.scrollTo({ top: 0, behavior: 'smooth' })
    return false
  }
  if (!formStore.durationMinutes || Number(formStore.durationMinutes) <= 0) {
    errorMessage.value = 'Please enter a valid Exam Duration in minutes.'
    return false
  }
  errorMessage.value = ''
  return true
}

const nextStep = () => {
  if (currentStep.value === 1) {
    if (!validateStep1()) return
  }
  if (currentStep.value < 4) {
    currentStep.value++
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const handleStepChange = (targetStep: number) => {
  if (targetStep > currentStep.value) {
    if (currentStep.value === 1 && !validateStep1()) return
  }
  errorMessage.value = ''
  currentStep.value = targetStep
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const handleCancel = () => {
  formStore.reset()
  router.push('/instructor/exams')
}

const isSavingDraft = ref(false)
const saveAsDraft = async () => {
  isSavingDraft.value = true
  try {
    await examStore.createExam({
      title: formStore.title || 'Untitled Exam',
      course_code: formStore.courseCode || 'NT-00',
      course_name: formStore.courseName || formStore.examType || 'Networking',
      section: formStore.section,
      duration_minutes: formStore.durationMinutes,
      total_marks: formStore.totalMarks,
      status: 'draft',
      scheduled_at: formStore.getScheduledAt(),
      settings: formStore.getSettingsPayload()
    })
    formStore.reset()
    router.push('/instructor/exams')
  } catch (err) {
    console.error('Failed to save draft:', err)
  } finally {
    isSavingDraft.value = false
  }
}
</script>

<template>
  <div class="max-w-[1400px] mx-auto px-2">
    
    <!-- Top Bar with Navigation and Quick Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
      <div class="flex items-center gap-2">
        <button
          @click="handleCancel"
          class="min-h-[44px] px-4 py-2 bg-white border border-slate-200 text-slate-700 font-bold text-[13px] rounded-xl hover:bg-slate-50 transition-colors shadow-xs flex items-center justify-center gap-2 cursor-pointer w-full sm:w-auto"
        >
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Exams
        </button>
      </div>

      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <button
          v-if="currentStep === 1"
          @click="saveAsDraft"
          :disabled="isSavingDraft"
          class="min-h-[44px] px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold text-[13px] rounded-xl transition-colors cursor-pointer justify-center"
        >
          Save as Draft
        </button>
        <button
          v-if="currentStep === 1"
          @click="nextStep"
          class="min-h-[44px] px-5 py-2 bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-[13px] rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2 cursor-pointer"
        >
          Next: Add Questions
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
        </button>
      </div>
    </div>

    <!-- Error Banner -->
    <div v-if="errorMessage" class="mb-4 p-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold rounded-2xl flex items-center justify-between shadow-xs">
      <div class="flex items-center gap-2.5">
        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <span>{{ errorMessage }}</span>
      </div>
      <button @click="errorMessage = ''" class="text-rose-500 hover:text-rose-700 font-bold text-xs cursor-pointer">
        &times;
      </button>
    </div>

    <!-- Main Content Area -->
    <div class="flex flex-col xl:flex-row gap-6">
      
      <!-- Left Column (Form) -->
      <div class="flex-1 min-w-0">
        <ExamStepper :currentStep="currentStep" @change-step="handleStepChange" />
        
        <!-- STEP 1: Exam Information -->
        <template v-if="currentStep === 1">
          <ExamInformationForm />

          <!-- Bottom Action Buttons -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 pb-10">
            <button @click="handleCancel" class="min-h-[44px] px-6 py-2.5 border border-slate-200 text-slate-600 font-bold text-[13px] rounded-xl hover:bg-slate-50 transition-colors cursor-pointer flex items-center justify-center">
              Cancel
            </button>
            
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
              <button @click="saveAsDraft" :disabled="isSavingDraft" class="min-h-[44px] px-5 py-2.5 border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 font-bold text-[13px] rounded-xl transition-colors cursor-pointer flex items-center justify-center">
                Save Draft
              </button>
              <button @click="nextStep" class="min-h-[44px] px-6 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-[13px] rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2 cursor-pointer">
                Next: Add Questions
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
              </button>
            </div>
          </div>
        </template>

        <!-- STEP 2: Add Questions -->
        <template v-else-if="currentStep === 2">
          <AddQuestionForm @cancel="handleCancel" @next="nextStep" @prev="handleStepChange(1)" @save-draft="saveAsDraft" :isSaving="isSavingDraft" />
        </template>

        <!-- STEP 3: Exam Settings -->
        <template v-else-if="currentStep === 3">
          <ExamSettingsForm @cancel="handleCancel" @next="nextStep" @prev="handleStepChange(2)" @save-draft="saveAsDraft" :isSaving="isSavingDraft" />
        </template>

        <!-- STEP 4: Review & Publish -->
        <template v-else-if="currentStep === 4">
          <ReviewPublishForm @edit-step="(step) => handleStepChange(step)" @cancel="handleCancel" @save-draft="saveAsDraft" :isSaving="isSavingDraft" />
        </template>

      </div>

      <!-- Right Column (Sidebar Widgets for Step 3) -->
      <div v-if="currentStep === 3" class="w-full xl:w-[320px] pt-4 xl:pt-[84px]">
        <SettingsOverviewSidebar />
        <SettingsTipsSidebar />
        <SettingsHelpSidebar />
      </div>

    </div>
  </div>
</template>
