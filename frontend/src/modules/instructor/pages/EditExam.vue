<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useCreateExamStore } from '../store/createExamStore'
import { useInstructorExamStore } from '../store/instructorExamStore'
import { useSemesterLockStore } from '../store/semesterLockStore'
import apiClient from '../../../core/api/apiClient'

import ExamStepper from '../components/create-exam/ExamStepper.vue'
import ExamInformationForm from '../components/create-exam/ExamInformationForm.vue'
import AddQuestionForm from '../components/create-exam/AddQuestionForm.vue'
import ExamSettingsForm from '../components/create-exam/ExamSettingsForm.vue'
import ReviewPublishForm from '../components/create-exam/ReviewPublishForm.vue'
import SettingsOverviewSidebar from '../components/create-exam/SettingsOverviewSidebar.vue'
import SettingsTipsSidebar from '../components/create-exam/SettingsTipsSidebar.vue'
import SettingsHelpSidebar from '../components/create-exam/SettingsHelpSidebar.vue'

const router = useRouter()
const route = useRoute()
const formStore = useCreateExamStore()
const examStore = useInstructorExamStore()
const lockStore = useSemesterLockStore()

const isLoading = ref(true)
const loadError = ref<string | null>(null)

// The current step — start at 4 (Review & Publish) so the user sees everything at once, unless overridden by query
const currentStep = computed({
  get: () => Number(route.query.step) || 4,
  set: (val) => router.push({ query: { ...route.query, step: val } })
})

onMounted(async () => {
  await lockStore.fetchLockStatus()
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('edit exam')
    router.replace('/instructor/exams')
    return
  }

  const examId = Number(route.params.id)
  if (!examId) {
    router.replace('/instructor/exams')
    return
  }

  // Reset form first, then load data
  formStore.reset()

  try {
    const details = await examStore.fetchExamDetails(examId)
    if (!details) throw new Error('Exam not found')
    formStore.loadExamForEditing(details)
    isLoading.value = false
  } catch (err: any) {
    loadError.value = 'Could not load exam data. Please go back and try again.'
    isLoading.value = false
  }
})

onBeforeUnmount(() => {
  formStore.reset()
})

const nextStep = () => {
  if (currentStep.value < 4) currentStep.value++
}

const isSavingDraft = ref(false)
const saveAsDraft = async () => {
  isSavingDraft.value = true
  try {
    if (formStore.editingExamId) {
      await apiClient.put(`/instructor/exams/${formStore.editingExamId}`, {
        title: formStore.title,
        course_code: formStore.courseCode,
        course_name: formStore.examType,
        duration_minutes: formStore.durationMinutes,
        total_marks: formStore.totalMarks,
        status: 'draft',
        scheduled_at: formStore.getScheduledAt(),
        questions: formStore.questions,
        settings: formStore.getSettingsPayload()
      })
    }
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
  <div class="max-w-[1400px] mx-auto">

    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <router-link to="/instructor/exams" class="min-h-[44px] px-4 py-2 bg-white border border-slate-200 text-slate-700 font-bold text-[13px] rounded-xl hover:bg-slate-50 transition-colors shadow-sm flex items-center gap-2">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Exams
        </router-link>
        <span class="text-[13px] font-bold text-slate-500">Editing Exam</span>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex flex-col items-center justify-center py-32 gap-4">
      <svg class="w-10 h-10 text-[#5138ed] animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
      </svg>
      <p class="text-slate-500 font-bold text-sm">Loading exam data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="loadError" class="bg-rose-50 border border-rose-200 text-rose-700 p-6 rounded-2xl text-center font-bold">
      {{ loadError }}
    </div>

    <!-- Edit Form -->
    <div v-else class="flex flex-col xl:flex-row gap-6">

      <!-- Left Column (Form) -->
      <div class="flex-1 min-w-0">
        <ExamStepper :currentStep="currentStep" />

        <!-- STEP 1: Exam Information -->
        <template v-if="currentStep === 1">
          <ExamInformationForm />
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 pb-10">
            <button @click="router.push('/instructor/exams')" class="min-h-[44px] px-6 py-2.5 border border-slate-200 text-slate-600 font-bold text-[13px] rounded-xl hover:bg-slate-50 transition-colors flex items-center justify-center">
              Cancel
            </button>
            <button @click="nextStep" class="min-h-[44px] px-6 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-[13px] rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2">
              Next: Add Questions
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </button>
          </div>
        </template>

        <!-- STEP 2: Add Questions -->
        <template v-else-if="currentStep === 2">
          <AddQuestionForm
            @cancel="router.push('/instructor/exams')"
            @next="nextStep"
            @prev="currentStep = 1"
            @save-draft="saveAsDraft"
            :isSaving="isSavingDraft"
          />
        </template>

        <!-- STEP 3: Exam Settings -->
        <template v-else-if="currentStep === 3">
          <ExamSettingsForm
            @cancel="router.push('/instructor/exams')"
            @next="nextStep"
            @prev="currentStep = 2"
            @save-draft="saveAsDraft"
            :isSaving="isSavingDraft"
          />
        </template>

        <!-- STEP 4: Review & Publish -->
        <template v-else-if="currentStep === 4">
          <ReviewPublishForm
            @edit-step="(step) => currentStep = step"
            @cancel="router.push('/instructor/exams')"
            @save-draft="saveAsDraft"
            :isSaving="isSavingDraft"
          />
        </template>
      </div>

      <!-- Right Column (Sidebar Widgets) -->
      <div v-if="currentStep === 3" class="w-full xl:w-[320px] pt-4 xl:pt-[84px]">
        <SettingsOverviewSidebar />
        <SettingsTipsSidebar />
        <SettingsHelpSidebar />
      </div>

    </div>
  </div>
</template>
