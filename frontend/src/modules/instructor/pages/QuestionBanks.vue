<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useInstructorQbStore } from '../store/instructorQbStore'
import { useSemesterLockStore } from '../store/semesterLockStore'

import QBStatCards from '../components/question-bank/QBStatCards.vue'
import QBTable from '../components/question-bank/QBTable.vue'
import CreateBankModal from '../components/question-bank/CreateBankModal.vue'

const qbStore = useInstructorQbStore()
const lockStore = useSemesterLockStore()
const showCreateModal = ref(false)
const bankToEdit = ref<{ id: number, title: string, description: string } | null>(null)

onMounted(() => {
  qbStore.fetchQuestionBanks()
  lockStore.fetchLockStatus()
})

const openCreateModal = () => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('create question bank')
    return
  }
  bankToEdit.value = null
  showCreateModal.value = true
}

const openEditModal = (bank: any) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('edit question bank')
    return
  }
  bankToEdit.value = {
    id: bank.id,
    title: bank.title,
    description: bank.description || ''
  }
  showCreateModal.value = true
}

const handleSubmitBank = async (payload: { id?: number, title: string, description: string }) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice(payload.id ? 'update question bank' : 'create question bank')
    return
  }
  if (payload.id) {
    await qbStore.updateQuestionBank(payload.id, payload)
  } else {
    await qbStore.createQuestionBank(payload)
  }
}
</script>

<template>
  <div>
    <div class="max-w-[1200px] mx-auto">
      
      <!-- Main Column -->
      <div class="space-y-5 lg:space-y-6">
        
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 mb-2">
          <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800">Question Banks</h1>
            <p class="text-xs sm:text-[14px] text-slate-500 mt-0.5 sm:mt-1">Create and manage question banks for your courses.</p>
          </div>
          <button
            v-if="!lockStore.isLocked"
            @click="openCreateModal"
            class="flex items-center justify-center gap-2 bg-[#5138ed] hover:bg-indigo-600 text-white px-4 sm:px-5 py-2.5 rounded-xl font-bold text-xs sm:text-sm shadow-xs transition-colors w-full sm:w-fit min-h-[44px]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Create Question Bank
          </button>
          <div
            v-else
            @click="lockStore.promptLockedNotice('create question bank')"
            class="flex items-center justify-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2.5 rounded-xl font-bold text-xs shadow-xs cursor-pointer hover:bg-emerald-100 transition-colors w-full sm:w-fit min-h-[44px]"
          >
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <span>Semester Locked (Read-Only)</span>
          </div>
        </div>

        <!-- Dev Banner: Mock Data Active -->
        <div
          v-if="qbStore.usingMockData"
          class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 text-xs sm:text-sm font-medium flex items-center gap-2"
        >
          <svg class="w-4 h-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>
            <strong>Dev Mode:</strong> Backend is offline — showing mock data.
          </span>
        </div>

        <!-- Error Banner: Real API Error -->
        <div v-if="qbStore.error" class="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 text-xs sm:text-sm font-medium flex items-center gap-2">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          {{ qbStore.error }}
        </div>

        <!-- Stat Cards -->
        <QBStatCards />

        <!-- Main Table Area -->
        <QBTable @edit="openEditModal" />

      </div>

    </div>

    <!-- Create Bank Modal -->
    <CreateBankModal 
      :show="showCreateModal"
      :initial-data="bankToEdit"
      @close="showCreateModal = false" 
      @submit="handleSubmitBank" 
    />
  </div>
</template>
