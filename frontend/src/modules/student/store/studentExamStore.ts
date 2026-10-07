import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '../../../core/api/apiClient'
import { sampleActiveExam, sampleUpcomingExams, sampleRecentResults } from '../data/mockData'
import type { ActiveExam, UpcomingExam, RecentResult } from '../types'

export const useStudentExamStore = defineStore('studentExam', () => {
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const usingMockData = ref(false)

  // Dashboard stats
  const dashboardStats = ref({
    completed_exams: 0,
    upcoming_exams: 0,
    average_score: 0,
  })

  // Exam data
  const activeExam = ref<ActiveExam | null>(null)
  const upcomingExams = ref<UpcomingExam[]>([])
  const results = ref<RecentResult[]>([])

  /**
   * Fetch dashboard stats from API
   */
  const fetchDashboard = async () => {
    try {
      const response = await apiClient.get('/student/dashboard')
      dashboardStats.value = response.data.data
    } catch (err: any) {
      console.error('Failed to fetch student dashboard', err)
    }
  }

  /**
   * Fetch published exams (upcoming + active) from API.
   * Falls back to mock data if backend is offline.
   * Accepts silent boolean to avoid flashing loading spinners during background polling.
   */
  const fetchExams = async (silent = false) => {
    if (!silent) {
      isLoading.value = true
    }
    error.value = null
    usingMockData.value = false

    try {
      const response = await apiClient.get('/student/exams')
      const data = response.data.data

      // Map active exam from API to frontend ActiveExam shape
      if (data.active_exam && !['submitted', 'graded', 'published'].includes(data.active_exam.attemptStatus)) {
        const ae = data.active_exam
        activeExam.value = {
          id: ae.id,
          courseCode: ae.courseCode,
          courseName: ae.courseName,
          examTitle: ae.examTitle,
          instructor: ae.instructor,
          date: ae.date,
          time: ae.time,
          durationMinutes: ae.durationMinutes,
          totalQuestions: 0, // will be filled on start
          totalMarks: ae.totalMarks,
          questions: [], // loaded when exam is started
        }
      } else {
        activeExam.value = null
      }

      // Map upcoming exams — exclude any that are already submitted, graded, or published
      upcomingExams.value = (data.upcoming_exams || [])
        .filter((e: any) => !['submitted', 'graded', 'published'].includes(e.attemptStatus))
        .map((e: any) => ({
          id: e.id,
          courseCode: e.courseCode,
          courseName: e.courseName,
          instructor: e.instructor,
          examType: e.examType,
          scheduledAt: e.scheduledAt || e.scheduledDate || null, // ISO string
          scheduledDate: e.scheduledAt || e.scheduledDate || null, // legacy alias
          startTime: e.startTime,
          durationMinutes: e.durationMinutes,
          totalQuestions: e.totalQuestions,
          totalMarks: e.totalMarks,
          status: e.status as 'Soon' | 'Pending' | 'Ready' | 'Upcoming',
          // Attempt tracking — drives Ready Card button state
          attemptStatus: e.attemptStatus as 'in_progress' | null | undefined,
          attemptId: e.attemptId as number | null | undefined,
          attemptStartedAt: e.attemptStartedAt as string | null | undefined,
        }))
    } catch (err: any) {
      console.error('Failed to fetch student exams', err)
      usingMockData.value = false
      if (!silent) {
        activeExam.value = null
        upcomingExams.value = []
      }
    } finally {
      if (!silent) {
        isLoading.value = false
      }
    }
  }

  /**
   * Start an exam attempt — returns questions without correct answers.
   */
  const startExam = async (examId: number) => {
    try {
      const response = await apiClient.post(`/student/exams/${examId}/start`)
      const data = response.data.data

      // Helper to remove <p> tags from rich text editor outputs
      const removePTags = (text: string) => {
        if (!text) return ''
        return String(text).replace(/<\/?p[^>]*>/gi, '')
      }

      // Build ActiveExam with real questions
      const examData: any = {
        id: examId,
        courseCode: data.course_code,
        courseName: data.course_name,
        examTitle: data.exam_title,
        instructor: '',
        date: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
        time: new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }),
        durationMinutes: data.duration_minutes,
        totalQuestions: data.questions.length,
        totalMarks: data.total_marks,
        settings: data.settings || {},
        // Attempt tracking — exam page uses started_at to calculate true time remaining
        attemptId: data.attempt_id,
        startedAt: data.started_at,  // ISO string of when attempt actually started
        // Recovery / timing fields from backend
        remainingSeconds: data.remaining_seconds ?? null,   // authoritative server remaining time
        adjustedDeadline: data.adjusted_deadline ?? null,   // ISO string deadline incl extra time
        extraTimeSeconds: data.extra_time_seconds ?? 0,
        savedAnswers: data.saved_answers ?? {},              // pre-filled answers for reconnecting students
        isCancelled: data.is_cancelled ?? false,
        questions: data.questions.map((q: any) => ({
          id: q.id,
          text: removePTags(q.text),
          instruction: q.instruction || null,
          type: q.type,
          options: (q.options || []).map((o: any) => {
            if (!o) return ''
            const optText = typeof o === 'string' ? o : (o.text || '')
            return removePTags(optText)
          }),
          pairs: q.pairs ? q.pairs.map((p: any) => ({
            ...p,
            left: removePTags(p.left),
            right: removePTags(p.right)
          })) : null,
          columnA: q.columnA || null,
          columnB: q.columnB || null,
          marks: q.marks || 0,
        })),
      }

      activeExam.value = examData

      // Mark this exam as in_progress in upcomingExams so the Ready Card shows "Continue Exam"
      const idx = upcomingExams.value.findIndex(e => e.id === examId)
      if (idx !== -1) {
        upcomingExams.value[idx] = {
          ...upcomingExams.value[idx],
          attemptStatus: 'in_progress',
          attemptId: data.attempt_id,
          attemptStartedAt: data.started_at,
        }
      }

      return { attemptId: data.attempt_id, exam: examData }
    } catch (err: any) {
      const message = err.response?.data?.message || 'Failed to start exam'
      console.error(message, err)
      throw new Error(message)
    }
  }

  /**
   * Submit exam answers — returns graded result.
   */
  const submitExam = async (examId: number, answers: Record<number, string>) => {
    try {
      const response = await apiClient.post(`/student/exams/${examId}/submit`, { answers })
      const data = response.data.data

      const hasPending  = data.has_pending as boolean
      const autoScore   = data.auto_score  as number
      const autoTotal   = data.auto_total  as number
      const pendingTotal = data.pending_total as number

      // Status: pending if manual questions exist
      const status = hasPending
        ? 'Pending'
        : (data.percentage >= 50 ? 'Passed' : 'Failed')

      // Build a RecentResult from the response
      const result: RecentResult = {
        id: data.attempt_id,
        courseCode: data.course_code,
        courseName: data.course_name,
        examTitle: data.exam_title,
        score: autoScore,
        totalMarks: data.total_marks,
        percentage: data.percentage ?? 0,
        grade: data.grade,
        status: status as any,
        completedDate: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
        // Extra breakdown fields
        autoScore,
        autoTotal,
        pendingTotal,
        hasPending,
        typeBreakdown: data.type_breakdown ?? {},
        questionsReview: (data.questionsReview ?? []).map((q: any) => ({
          questionText:  q.questionText,
          type:          q.type,
          gradingStatus: q.gradingStatus,   // 'graded' | 'pending'
          studentAnswer: q.studentAnswer ?? 'Not answered',
          correctAnswer: q.correctAnswer ?? null,
          explanation:   q.explanation ?? '',
          isCorrect:     q.isCorrect,       // null if pending
          marks:         q.marks,
          earnedMarks:   q.earnedMarks,     // null if pending
          correctCount:  q.correctCount,
          totalCount:    q.totalCount,
        })),
      }

      // Prepend to results
      results.value.unshift(result)

      // Clear active exam
      activeExam.value = null

      // Remove from upcoming
      upcomingExams.value = upcomingExams.value.filter(e => e.id !== examId)

      return result
    } catch (err: any) {
      console.error('Failed to submit exam', err)
      throw err
    }
  }

  /**
   * Send heartbeat to backend — syncs current answers and gets authoritative timer.
   * Returns: { remaining_seconds, extra_time_seconds, adjusted_deadline, is_cancelled, is_paused }
   */
  const sendHeartbeat = async (examId: number, answers: Record<number, string>) => {
    try {
      const response = await apiClient.post(`/student/exams/${examId}/heartbeat`, { answers })
      return response.data.data
    } catch (err: any) {
      // Silently fail — heartbeat should not crash the exam session
      console.warn('Heartbeat failed:', err?.response?.status)
      return null
    }
  }

  /**
   * Notify backend that the student reconnected after a connection loss.
   * Returns: recovery request info + authoritative timer
   */
  const sendReconnect = async (
    examId: number,
    pendingAnswers: Record<number, string>,
    clientDisconnectedAt: string
  ) => {
    try {
      const response = await apiClient.post(`/student/exams/${examId}/reconnect`, {
        pending_answers: pendingAnswers,
        client_disconnected_at: clientDisconnectedAt,
      })
      return response.data.data
    } catch (err: any) {
      console.warn('Reconnect notification failed:', err?.response?.status)
      return null
    }
  }

  /**
   * Fetch past exam results from API.
   */
  const fetchResults = async () => {
    try {
      const response = await apiClient.get('/student/results')
      results.value = response.data.data.map((r: any) => ({
        id: r.id,
        courseCode: r.courseCode,
        courseName: r.courseName,
        examTitle: r.examTitle,
        examType: r.examType,
        score: r.score,
        totalMarks: r.totalMarks,
        percentage: r.percentage,
        grade: r.grade,
        status: r.status as 'Passed' | 'Failed',
        completedDate: r.completedDate,
        questionsReview: r.questionsReview || [],
      }))
    } catch (err: any) {
      console.error('Failed to fetch results', err)
      results.value = []
    }
  }

  /**
   * Fetch detailed result breakdown for a specific attempt from API.
   */
  const fetchResultDetail = async (attemptId: number | string) => {
    isLoading.value = true
    error.value = null
    try {
      const response = await apiClient.get(`/student/results/${attemptId}`)
      return response.data.data
    } catch (err: any) {
      console.error('Failed to fetch result detail', err)
      error.value = err.response?.data?.message || 'Failed to load exam result details'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  return {
    isLoading,
    error,
    usingMockData,
    dashboardStats,
    activeExam,
    upcomingExams,
    results,
    fetchDashboard,
    fetchExams,
    startExam,
    submitExam,
    sendHeartbeat,
    sendReconnect,
    fetchResults,
    fetchResultDetail,
  }
})
