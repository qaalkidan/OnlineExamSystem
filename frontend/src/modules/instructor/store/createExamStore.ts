import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useCreateExamStore = defineStore('createExam', () => {
  const title = ref('')
  const courseCode = ref('')
  const courseName = ref('')
  const examType = ref('Mid Exam')
  const section = ref('Section B')
  const totalMarks = ref(100)
  const passingMarks = ref(60)
  const description = ref('')
  
  const durationMinutes = ref(90)
  const scheduledDate = ref('')
  const scheduledTime = ref('09:00')

  // Editing an existing exam
  const editingExamId = ref<number | null>(null)

  // Array to hold questions created during the exam creation flow
  const questions = ref<any[]>([])

  // Exam Settings
  const shuffleQuestions = ref(true)
  const showReviewScreen = ref(true)
  const shuffleAnswers = ref(true)
  const allowBacktracking = ref(true)
  const showOneQuestionAtATime = ref(false)
  const autoSubmitOnTimeFinish = ref(true)

  // Security Settings
  const enableFullscreenMode = ref(true)
  const enableBrowserTabMonitoring = ref(true)
  const disableRightClick = ref(true)
  const allowCalculator = ref(false)
  const disableCopyPaste = ref(true)
  const webcamMonitoring = ref(false)

  const maxAttempts = ref('1')
  const timeZone = ref('(UTC+03:00) Addis Ababa, Nairobi')

  const getSettingsPayload = () => {
    return {
      shuffleQuestions: shuffleQuestions.value,
      showReviewScreen: showReviewScreen.value,
      shuffleAnswers: shuffleAnswers.value,
      allowBacktracking: allowBacktracking.value,
      showOneQuestionAtATime: showOneQuestionAtATime.value,
      autoSubmitOnTimeFinish: autoSubmitOnTimeFinish.value,
      enableFullscreenMode: enableFullscreenMode.value,
      enableBrowserTabMonitoring: enableBrowserTabMonitoring.value,
      disableRightClick: disableRightClick.value,
      allowCalculator: allowCalculator.value,
      disableCopyPaste: disableCopyPaste.value,
      webcamMonitoring: webcamMonitoring.value,
      maxAttempts: maxAttempts.value,
      timeZone: timeZone.value,
    }
  }

  // Load an existing exam into the form store for editing
  const loadExamForEditing = (exam: any) => {
    editingExamId.value = exam.id
    title.value = exam.title || ''
    courseCode.value = exam.course_code || ''
    courseName.value = exam.course_name || ''
    examType.value = exam.exam_type || exam.course_name || 'Mid Exam'
    section.value = exam.section || 'Section B'
    totalMarks.value = exam.total_marks || 100
    passingMarks.value = exam.passing_marks || 60
    description.value = exam.description || ''
    durationMinutes.value = exam.duration_minutes || 90

    // Parse scheduled_at back to date/time fields
    if (exam.scheduled_at) {
      const d = new Date(exam.scheduled_at)
      const month = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      const year = d.getFullYear()
      // <input type="date"> expects YYYY-MM-DD
      scheduledDate.value = `${year}-${month}-${day}`
      
      const hours = String(d.getHours()).padStart(2, '0')
      const minutes = String(d.getMinutes()).padStart(2, '0')
      // <input type="time"> expects HH:mm (24-hour format)
      scheduledTime.value = `${hours}:${minutes}`
    }

    // Load questions
    questions.value = (exam.questions || []).map((q: any) => ({
      ...q,
      options: Array.isArray(q.options)
        ? q.options.map((o: any) => (typeof o === 'string' ? o : o.text || o))
        : [],
    }))

    // Load settings
    const s = exam.settings || {}
    shuffleQuestions.value = s.shuffleQuestions ?? s.shuffle_questions ?? true
    showReviewScreen.value = s.showReviewScreen ?? s.show_review_screen ?? true
    shuffleAnswers.value = s.shuffleAnswers ?? s.shuffle_answers ?? true
    allowBacktracking.value = s.allowBacktracking ?? s.allow_backtracking ?? true
    showOneQuestionAtATime.value = s.showOneQuestionAtATime ?? s.show_one_question_at_a_time ?? false
    autoSubmitOnTimeFinish.value = s.autoSubmitOnTimeFinish ?? s.auto_submit_on_time_finish ?? true
    enableFullscreenMode.value = s.enableFullscreenMode ?? s.enable_fullscreen_mode ?? true
    enableBrowserTabMonitoring.value = s.enableBrowserTabMonitoring ?? s.enable_browser_tab_monitoring ?? true
    disableRightClick.value = s.disableRightClick ?? s.disable_right_click ?? true
    allowCalculator.value = s.allowCalculator ?? s.allow_calculator ?? false
    disableCopyPaste.value = s.disableCopyPaste ?? s.disable_copy_paste ?? true
    webcamMonitoring.value = s.webcamMonitoring ?? s.webcam_monitoring ?? false
    maxAttempts.value = String(s.maxAttempts ?? s.max_attempts ?? '1')
    timeZone.value = s.timeZone ?? s.time_zone ?? '(UTC+03:00) Addis Ababa, Nairobi'
  }

  // Build an ISO string from the separate date + time fields.
  // We parse manually to avoid browser-specific behavior with `new Date(string)`.
  const getScheduledAt = (): string | null => {
    if (!scheduledDate.value || !scheduledTime.value) return null
    try {
      // <input type="date"> uses YYYY-MM-DD format
      let year, month, day
      if (scheduledDate.value.includes('-')) {
        [year, month, day] = scheduledDate.value.split('-').map(Number)
      } else if (scheduledDate.value.includes('/')) {
        [month, day, year] = scheduledDate.value.split('/').map(Number)
      } else {
        return null
      }
      
      // scheduledTime is HH:MM AM/PM (or HH:MM from <input type="time">)
      let hour = 0, rawMin = 0
      
      // <input type="time"> uses 24-hour format HH:MM
      if (scheduledTime.value.includes('AM') || scheduledTime.value.includes('PM')) {
        const [timePart, meridiem] = scheduledTime.value.trim().split(' ')
        const [rawHour, min] = timePart.split(':').map(Number)
        hour = rawHour
        rawMin = min
        if (meridiem?.toUpperCase() === 'PM' && hour !== 12) hour += 12
        if (meridiem?.toUpperCase() === 'AM' && hour === 12) hour = 0
      } else {
        // 24-hour format from input type="time"
        const [rawHour, min] = scheduledTime.value.trim().split(':').map(Number)
        hour = rawHour
        rawMin = min
      }

      // Build as a local Date so it matches what the instructor sees on screen
      const d = new Date(year, month - 1, day, hour, rawMin, 0, 0)
      if (isNaN(d.getTime())) return null
      return d.toISOString()
    } catch {
      return null
    }
  }

  const reset = () => {
    editingExamId.value = null
    title.value = ''
    courseCode.value = ''
    courseName.value = ''
    examType.value = 'Mid Exam'
    section.value = 'Section B'
    totalMarks.value = 100
    passingMarks.value = 60
    description.value = ''
    durationMinutes.value = 90
    scheduledDate.value = ''
    scheduledTime.value = '09:00'
    questions.value = []
    
    // Reset Settings
    shuffleQuestions.value = true
    showReviewScreen.value = true
    shuffleAnswers.value = true
    allowBacktracking.value = true
    showOneQuestionAtATime.value = false
    autoSubmitOnTimeFinish.value = true
    enableFullscreenMode.value = true
    enableBrowserTabMonitoring.value = true
    disableRightClick.value = true
    allowCalculator.value = false
    disableCopyPaste.value = true
    webcamMonitoring.value = false
    maxAttempts.value = '1'
    timeZone.value = '(UTC+03:00) Addis Ababa, Nairobi'
  }

  return {
    title,
    courseCode,
    courseName,
    examType,
    section,
    totalMarks,
    passingMarks,
    description,
    durationMinutes,
    scheduledDate,
    scheduledTime,
    questions,
    editingExamId,
    loadExamForEditing,
    shuffleQuestions,
    showReviewScreen,
    shuffleAnswers,
    allowBacktracking,
    showOneQuestionAtATime,
    autoSubmitOnTimeFinish,
    enableFullscreenMode,
    enableBrowserTabMonitoring,
    disableRightClick,
    allowCalculator,
    disableCopyPaste,
    webcamMonitoring,
    maxAttempts,
    timeZone,
    getSettingsPayload,
    getScheduledAt,
    reset
  }
})
