import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import apiClient from '../../../core/api/apiClient'

export interface SemesterLockData {
  is_locked: boolean
  status: 'pending' | 'submitted' | 'under_review' | 'approved' | 'rejected' | 'correction_required' | 'reopened'
  access_mode: 'editable' | 'read_only'
  academic_year: string
  semester: string
  submitted_at: string | null
  approved_at: string | null
  reopened_at: string | null
  reopen_reason: string | null
  remarks: string | null
}

export const useSemesterLockStore = defineStore('semesterLock', () => {
  const isLocked = ref(false)
  const status = ref<string>('pending')
  const accessMode = ref<'editable' | 'read_only'>('editable')
  const academicYear = ref('2025/2026')
  const semester = ref('First Semester')
  const submittedAt = ref<string | null>(null)
  const approvedAt = ref<string | null>(null)
  const reopenedAt = ref<string | null>(null)
  const reopenReason = ref<string | null>(null)
  const remarks = ref<string | null>(null)

  const isLoading = ref(false)
  const hasFetched = ref(false)
  const showLockedNoticeModal = ref(false)
  const attemptedAction = ref('')

  const statusLabel = computed(() => {
    switch (status.value.toLowerCase()) {
      case 'submitted':
        return 'Semester Submitted'
      case 'approved':
        return 'Semester Approved'
      case 'reopened':
        return 'Semester Reopened'
      case 'correction_required':
        return 'Correction Required'
      case 'rejected':
        return 'Rejected'
      default:
        return 'Pending Submission'
    }
  })

  const lockMessage = computed(() => {
    return 'Your semester submission has been completed. Academic modifications are locked for this semester. Please contact the Department Head if a correction is required.'
  })

  const fetchLockStatus = async (force = false): Promise<void> => {
    if (hasFetched.value && !force) return
    isLoading.value = true
    try {
      const res = await apiClient.get('/instructor/semester-lock-status')
      const data = res.data?.data
      if (data) {
        isLocked.value = Boolean(data.is_locked)
        status.value = data.status || 'pending'
        accessMode.value = data.access_mode || (data.is_locked ? 'read_only' : 'editable')
        if (data.academic_year) academicYear.value = data.academic_year
        if (data.semester) semester.value = data.semester
        submittedAt.value = data.submitted_at || null
        approvedAt.value = data.approved_at || null
        reopenedAt.value = data.reopened_at || null
        reopenReason.value = data.reopen_reason || null
        remarks.value = data.remarks || null
      }
      hasFetched.value = true
    } catch (err) {
      console.warn('Failed to fetch semester lock status:', err)
    } finally {
      isLoading.value = false
    }
  }

  const promptLockedNotice = (actionName = 'modify academic records') => {
    attemptedAction.value = actionName
    showLockedNoticeModal.value = true
  }

  const closeLockedNotice = () => {
    showLockedNoticeModal.value = false
    attemptedAction.value = ''
  }

  return {
    isLocked,
    status,
    accessMode,
    academicYear,
    semester,
    submittedAt,
    approvedAt,
    reopenedAt,
    reopenReason,
    remarks,
    isLoading,
    hasFetched,
    statusLabel,
    lockMessage,
    showLockedNoticeModal,
    attemptedAction,
    fetchLockStatus,
    promptLockedNotice,
    closeLockedNotice,
  }
})
