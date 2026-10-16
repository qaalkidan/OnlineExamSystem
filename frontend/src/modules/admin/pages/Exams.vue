<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { Doughnut } from 'vue-chartjs'
import apiClient from '../../../core/api/apiClient'

ChartJS.register(ArcElement, Tooltip, Legend)

// ── State Management ────────────────────────────────────────────────────────
const search = ref('')
const debouncedSearch = ref('')
let searchTimeout: any = null

watch(search, (newVal) => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    debouncedSearch.value = newVal.trim()
    currentPage.value = 1
  }, 250)
})

const statusFilter = ref('all')
const courseFilter = ref('all')
const departmentFilter = ref('all')
const typeFilter = ref('all')
const yearFilter = ref('all')
const semesterFilter = ref('all')
const currentPage = ref(1)
const perPage = ref(10)

const isLoading = ref(true)
const isExporting = ref(false)
const showExportDropdown = ref(false)
const showColumnDropdown = ref(false)
const activeActionDropdown = ref<number | null>(null)

// Modals and Views
const showDetailsPage = ref(false)
const showAllQuestions = ref(false)
const showDeleteModal = ref(false)
const showCancelModal = ref(false)
const showFormModal = ref(false)
const isEditing = ref(false)

const selectedExam = ref<any>(null)
const cancellationReason = ref('')
const isCancelling = ref(false)
const isSaving = ref(false)

// Toast State
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' | 'info' }>({
  show: false,
  message: '',
  type: 'info'
})
const showToast = (message: string, type: 'success' | 'error' | 'info' = 'info') => {
  toast.value = { show: true, message, type }
  setTimeout(() => { toast.value.show = false }, 3500)
}

// Backend Data
const allExams = ref<any[]>([])
const backendCourses = ref<any[]>([])
const backendDepartments = ref<any[]>([])
const backendInstructors = ref<any[]>([])

const statsData = ref({
  total: 0,
  published: 0,
  draft: 0,
  scheduled: 0,
  completed: 0,
  cancelled: 0,
  total_questions: 0,
  total_attempts: 0,
})

// Form State
const examForm = ref({
  title: '',
  course_code: '',
  course_name: '',
  section: 'Both Sections',
  duration_minutes: 60,
  total_marks: 100,
  status: 'published',
  scheduled_at: '',
  instructor_id: '',
  exam_type: 'Mid Exam',
  shuffle_questions: true,
  shuffle_options: true,
  allow_backtracking: true,
  fullscreen_mode: true,
  tab_monitoring: true,
  disable_right_click: true,
  disable_copy_paste: true,
  allow_calculator: false,
  show_one_question: false,
})

const examFormErrors = ref<Record<string, string>>({})

// Visible Columns Configuration
const visibleColumns = ref({
  course: true,
  department: true,
  instructor: true,
  type: true,
  schedule: true,
  duration: true,
  metrics: true,
  status: true,
})

// ── Dropdown Filters (Strictly Real Data) ────────────────────────────────────
const allCourses = computed(() => {
  const fromExams = allExams.value.map(e => e.course || e.courseName).filter(Boolean)
  const fromDb = backendCourses.value.map(c => c.title).filter(Boolean)
  return Array.from(new Set([...fromExams, ...fromDb])).sort()
})

const allDepartments = computed(() => {
  const fromExams = allExams.value.map(e => e.department).filter(Boolean)
  const fromDb = backendDepartments.value.map(d => d.name).filter(Boolean)
  return Array.from(new Set([...fromExams, ...fromDb])).sort()
})

const allYears = computed(() => {
  const years = allExams.value.map(e => e.year || e.year_level).filter(Boolean)
  return Array.from(new Set(years)).sort()
})

const allSemesters = computed(() => {
  const sems = allExams.value.map(e => e.semester).filter(Boolean)
  return Array.from(new Set(sems)).sort()
})

const examTypes = computed(() => {
  const types = allExams.value.map(e => e.type || e.exam_type).filter(Boolean)
  return Array.from(new Set([...types, 'Mid Exam', 'Final Exam', 'Quiz', 'Assignment'])).sort()
})

// ── Filtering Logic ───────────────────────────────────────────────────────────
const filtered = computed(() => {
  return allExams.value.filter(e => {
    // 1. Debounced Search
    if (debouncedSearch.value) {
      const q = debouncedSearch.value.toLowerCase()
      const matchTitle = (e.title || '').toLowerCase().includes(q)
      const matchCourse = (e.course || e.courseName || '').toLowerCase().includes(q)
      const matchCode = (e.courseCode || e.course_code || e.code || '').toLowerCase().includes(q)
      const matchInst = (e.instructor || '').toLowerCase().includes(q)
      const matchDept = (e.department || '').toLowerCase().includes(q)
      if (!matchTitle && !matchCourse && !matchCode && !matchInst && !matchDept) return false
    }

    // 2. Status Filter
    if (statusFilter.value !== 'all') {
      const s = statusFilter.value.toLowerCase()
      if (s === 'cancelled' && !e.is_cancelled && e.status !== 'cancelled') return false
      if (s !== 'cancelled' && (e.status !== s || e.is_cancelled)) return false
    }

    // 3. Course Filter
    if (courseFilter.value !== 'all') {
      if ((e.course || e.courseName) !== courseFilter.value && (e.courseCode || e.course_code) !== courseFilter.value) {
        return false
      }
    }

    // 4. Department Filter
    if (departmentFilter.value !== 'all') {
      if (e.department !== departmentFilter.value) return false
    }

    // 5. Year Filter
    if (yearFilter.value !== 'all') {
      if ((e.year || e.year_level) !== yearFilter.value) return false
    }

    // 6. Semester Filter
    if (semesterFilter.value !== 'all') {
      if (e.semester !== semesterFilter.value) return false
    }

    // 7. Type Filter
    if (typeFilter.value !== 'all') {
      if ((e.type || e.exam_type) !== typeFilter.value) return false
    }

    return true
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filtered.value.slice(start, start + perPage.value)
})

// ── Active Filter Chips ───────────────────────────────────────────────────────
const activeFilterChips = computed(() => {
  const chips: Array<{ id: string; label: string; clear: () => void }> = []
  if (debouncedSearch.value) {
    chips.push({ id: 'search', label: `Search: "${debouncedSearch.value}"`, clear: () => { search.value = ''; debouncedSearch.value = '' } })
  }
  if (statusFilter.value !== 'all') {
    chips.push({ id: 'status', label: `Status: ${statusFilter.value}`, clear: () => { statusFilter.value = 'all' } })
  }
  if (courseFilter.value !== 'all') {
    chips.push({ id: 'course', label: `Course: ${courseFilter.value}`, clear: () => { courseFilter.value = 'all' } })
  }
  if (departmentFilter.value !== 'all') {
    chips.push({ id: 'dept', label: `Dept: ${departmentFilter.value}`, clear: () => { departmentFilter.value = 'all' } })
  }
  if (typeFilter.value !== 'all') {
    chips.push({ id: 'type', label: `Type: ${typeFilter.value}`, clear: () => { typeFilter.value = 'all' } })
  }
  if (yearFilter.value !== 'all') {
    chips.push({ id: 'year', label: `Year: ${yearFilter.value}`, clear: () => { yearFilter.value = 'all' } })
  }
  if (semesterFilter.value !== 'all') {
    chips.push({ id: 'sem', label: `Semester: ${semesterFilter.value}`, clear: () => { semesterFilter.value = 'all' } })
  }
  return chips
})

const clearAllFilters = () => {
  search.value = ''
  debouncedSearch.value = ''
  statusFilter.value = 'all'
  courseFilter.value = 'all'
  departmentFilter.value = 'all'
  typeFilter.value = 'all'
  yearFilter.value = 'all'
  semesterFilter.value = 'all'
  currentPage.value = 1
}

// ── KPI Card Interactive Filtering ──────────────────────────────────────────
const selectKpiStatus = (status: string) => {
  if (statusFilter.value === status) {
    statusFilter.value = 'all'
  } else {
    statusFilter.value = status
  }
  currentPage.value = 1
}

// ── Status & Type Configurations ────────────────────────────────────────────
const statusConfig: Record<string, { badge: string; dot: string; label: string }> = {
  published: { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500', label: 'Published' },
  completed: { badge: 'bg-indigo-50 text-indigo-700 border-indigo-200',     dot: 'bg-[#4338ca]',   label: 'Completed' },
  scheduled: { badge: 'bg-amber-50 text-amber-700 border-amber-200',       dot: 'bg-amber-500',   label: 'Scheduled' },
  draft:     { badge: 'bg-slate-100 text-slate-600 border-slate-200',      dot: 'bg-slate-400',   label: 'Draft' },
  cancelled: { badge: 'bg-rose-50 text-rose-700 border-rose-200',          dot: 'bg-rose-500',    label: 'Cancelled' },
}

const typeConfig: Record<string, string> = {
  'Quiz':           'bg-sky-50 text-sky-700 border-sky-100',
  'Midterm':        'bg-amber-50 text-amber-700 border-amber-100',
  'Mid Exam':       'bg-amber-50 text-amber-700 border-amber-100',
  'Final':          'bg-rose-50 text-rose-700 border-rose-100',
  'Final Exam':     'bg-rose-50 text-rose-700 border-rose-100',
  'Assignment':     'bg-violet-50 text-violet-700 border-violet-100',
  'Practical Exam': 'bg-teal-50 text-teal-700 border-teal-100',
}

// ── Real Data Chart & Analytics ─────────────────────────────────────────────
const chartData = computed(() => {
  const published = statsData.value.published
  const scheduled = statsData.value.scheduled
  const completed = statsData.value.completed
  const other = (statsData.value.draft || 0) + (statsData.value.cancelled || 0)

  return {
    labels: ['Published', 'Scheduled', 'Completed', 'Draft/Cancelled'],
    datasets: [{
      backgroundColor: ['#10B981', '#F59E0B', '#4338ca', '#94A3B8'],
      data: [published, scheduled, completed, other],
      borderWidth: 0,
      hoverOffset: 6
    }]
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '72%',
  plugins: {
    legend: { display: false },
    tooltip: { enabled: true }
  }
}

const calcPct = (val: number) => {
  const total = statsData.value.total
  if (!total || total === 0) return '0%'
  return Math.round((val / total) * 100) + '%'
}

// Top Exam Types dynamically calculated from real database
const topExamTypes = computed(() => {
  const map: Record<string, number> = {}
  allExams.value.forEach(e => {
    const t = e.type || e.exam_type || 'Mid Exam'
    map[t] = (map[t] || 0) + 1
  })
  return Object.entries(map)
    .map(([name, count]) => ({ name, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 5)
})

// Real Upcoming Exams from database
const upcomingExams = computed(() => {
  return allExams.value
    .filter(e => !e.is_cancelled && (e.status === 'scheduled' || e.status === 'published'))
    .sort((a, b) => {
      const timeA = a.scheduled_at ? new Date(a.scheduled_at).getTime() : 0
      const timeB = b.scheduled_at ? new Date(b.scheduled_at).getTime() : 0
      return timeA - timeB
    })
    .slice(0, 4)
})

// ── Question Paper Management & Advanced Question Normalization ───────────────────
const answerKeyVisible = ref(true)
const questionTypeFilter = ref('all')
const searchQuestionQuery = ref('')

const normalizeQuestionType = (rawType: string): 'true_false' | 'matching' | 'multiple_choice' | 'fill_in_the_blank' | 'short_answer' => {
  const t = (rawType || '').toLowerCase().trim()
  if (t === 'true_false' || t === 'true/false' || t.includes('true') || t.includes('false')) {
    return 'true_false'
  }
  if (t === 'matching' || t.includes('match')) {
    return 'matching'
  }
  if (t === 'fill_blank' || t === 'fill_in_blank' || t === 'fill_in_the_blank' || t.includes('blank') || t.includes('fill')) {
    return 'fill_in_the_blank'
  }
  if (t === 'short_answer' || t === 'essay' || t.includes('short') || t.includes('essay')) {
    return 'short_answer'
  }
  return 'multiple_choice'
}

const getLetterLabel = (index: number): string => {
  let label = ''
  let num = index
  while (num >= 0) {
    label = String.fromCharCode(65 + (num % 26)) + label
    num = Math.floor(num / 26) - 1
  }
  return label
}

const parseMatchingData = (q: any) => {
  const qData = q.question_data || {}
  let columnA: { id: number; text: string }[] = []
  let columnB: { label: string; text: string }[] = []
  
  if (Array.isArray(qData.column_a) && qData.column_a.length > 0) {
    columnA = qData.column_a.map((item: any, idx: number) => ({
      id: idx + 1,
      text: typeof item === 'object' && item !== null ? (item.text || item.title || JSON.stringify(item)) : String(item)
    }))
  }
  
  if (Array.isArray(qData.column_b) && qData.column_b.length > 0) {
    columnB = qData.column_b.map((item: any, idx: number) => ({
      label: item.label || getLetterLabel(idx),
      text: typeof item === 'object' && item !== null ? (item.text || item.description || JSON.stringify(item)) : String(item)
    }))
  }
  
  // Fallback to options if column_a or column_b not populated
  if ((columnA.length === 0 || columnB.length === 0) && Array.isArray(q.options) && q.options.length > 0) {
    columnA = q.options.map((opt: any, idx: number) => ({
      id: idx + 1,
      text: opt.left || opt.premise || opt.term || `Item ${idx + 1}`
    }))
    columnB = q.options.map((opt: any, idx: number) => ({
      label: getLetterLabel(idx),
      text: opt.right || opt.response || opt.definition || `Option ${getLetterLabel(idx)}`
    }))
  }
  
  // Extract matches
  const matches: { aNum: number; aText: string; bLabel: string; bText: string }[] = []
  
  if (Array.isArray(qData.matched_pairs) && qData.matched_pairs.length > 0) {
    qData.matched_pairs.forEach((pair: any, idx: number) => {
      const matchedB = columnB.find(b => b.text.trim().toLowerCase() === String(pair.right || '').trim().toLowerCase())
      matches.push({
        aNum: idx + 1,
        aText: pair.left || '',
        bLabel: matchedB?.label || getLetterLabel(idx),
        bText: pair.right || ''
      })
    })
  } else if (qData.correct_answers && typeof qData.correct_answers === 'object') {
    Object.entries(qData.correct_answers).forEach(([aKey, bVal]) => {
      const aNum = Number(aKey)
      const aItem = columnA.find(a => a.id === aNum)
      const bLabel = String(bVal).trim().toUpperCase()
      const bItem = columnB.find(b => b.label.toUpperCase() === bLabel)
      if (aItem) {
        matches.push({
          aNum: aItem.id,
          aText: aItem.text,
          bLabel: bLabel,
          bText: bItem?.text || ''
        })
      }
    })
  } else if (typeof q.correct_answer === 'string' && q.correct_answer.includes('-')) {
    const pairs = q.correct_answer.split(',').map((s: string) => s.trim())
    pairs.forEach((p: string) => {
      const parts = p.split('-').map(s => s.trim())
      const aNum = parseInt(parts[0], 10)
      const bLabel = parts[1] ? parts[1].toUpperCase() : ''
      const aItem = columnA.find(a => a.id === aNum)
      const bItem = columnB.find(b => b.label.toUpperCase() === bLabel)
      if (aItem) {
        matches.push({
          aNum: aItem.id,
          aText: aItem.text,
          bLabel: bLabel,
          bText: bItem?.text || ''
        })
      }
    })
  }
  
  const marksPerItem = q.marks_per_item || qData.marks_per_item || (columnA.length > 0 ? (Number(q.marks || 0) / columnA.length).toFixed(1) : 1)
  
  return {
    columnA,
    columnB,
    matches,
    marksPerItem,
    columnATitle: qData.column_a_title || 'Column A (Premises / Terms)',
    columnBTitle: qData.column_b_title || 'Column B (Responses / Definitions)',
  }
}

const parseMultipleChoiceOptions = (q: any) => {
  if (!Array.isArray(q.options)) return []
  const rawCorrect = String(q.correct_answer || '').trim()
  
  return q.options.map((opt: any, idx: number) => {
    const label = getLetterLabel(idx)
    const text = typeof opt === 'object' && opt !== null ? (opt.text || opt.label || JSON.stringify(opt)) : String(opt)
    const isExplicitCorrect = typeof opt === 'object' && Boolean(opt.is_correct || opt.isCorrect)
    const isMatchingLetter = rawCorrect.toUpperCase() === label
    const isMatchingText = rawCorrect.trim().toLowerCase() === text.trim().toLowerCase()
    
    return {
      label,
      text,
      isCorrect: Boolean(isExplicitCorrect || isMatchingLetter || isMatchingText)
    }
  })
}

const parseTrueFalse = (q: any) => {
  const rawCorrect = String(q.correct_answer || '').trim().toLowerCase()
  const isACorrect = rawCorrect === 'a' || rawCorrect === 'true' || rawCorrect === 't' || rawCorrect === '1'
  const isBCorrect = rawCorrect === 'b' || rawCorrect === 'false' || rawCorrect === 'f' || rawCorrect === '0'
  
  return [
    { label: 'A', text: 'True', isCorrect: isACorrect },
    { label: 'B', text: 'False', isCorrect: isBCorrect },
  ]
}

const renderFillInBlankHtml = (text: string) => {
  if (!text) return ''
  const blankPattern = /_{2,}|\{blank\}/gi
  if (!blankPattern.test(text)) {
    return text
  }
  return text.replace(blankPattern, `<span class="inline-block border-b-2 border-dashed border-slate-400 min-w-[80px] text-center font-mono text-slate-400 text-xs mx-1 pb-0.5">[ &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ]</span>`)
}

// Master list of all questions with global continuous sequential index (1 to N)
const allExamQuestions = computed(() => {
  if (!selectedExam.value?.questions || !Array.isArray(selectedExam.value.questions)) return []
  return selectedExam.value.questions.map((q: any, idx: number) => {
    const normType = normalizeQuestionType(q.raw_type || q.type)
    return {
      ...q,
      globalIndex: idx + 1,
      normalizedType: normType,
    }
  })
})

// Counts and marks breakdown by question type
const questionTypeSummary = computed(() => {
  const summary: Record<string, { count: number; marks: number }> = {
    all: { count: allExamQuestions.value.length, marks: 0 },
    true_false: { count: 0, marks: 0 },
    matching: { count: 0, marks: 0 },
    multiple_choice: { count: 0, marks: 0 },
    fill_in_the_blank: { count: 0, marks: 0 },
    short_answer: { count: 0, marks: 0 },
  }
  
  allExamQuestions.value.forEach((q: any) => {
    const marks = Number(q.marks || 0)
    summary.all.marks += marks
    if (summary[q.normalizedType]) {
      summary[q.normalizedType].count++
      summary[q.normalizedType].marks += marks
    }
  })
  
  return summary
})

// Filtered questions based on active type tab and search query
const filteredQuestionsList = computed(() => {
  return allExamQuestions.value.filter((q: any) => {
    // 1. Type Filter
    if (questionTypeFilter.value !== 'all' && q.normalizedType !== questionTypeFilter.value) {
      return false
    }
    // 2. Search Query
    if (searchQuestionQuery.value.trim()) {
      const query = searchQuestionQuery.value.toLowerCase().trim()
      const matchText = (q.text || '').toLowerCase().includes(query)
      const matchInst = (q.instruction || '').toLowerCase().includes(query)
      const matchAns = String(q.correct_answer || '').toLowerCase().includes(query)
      if (!matchText && !matchInst && !matchAns) return false
    }
    return true
  })
})

// Sections grouped for academic university exam paper layout
const organizedExamSections = computed(() => {
  const typeConfig: Record<string, { part: string; title: string; defaultInstruction: string }> = {
    true_false: {
      part: 'PART I',
      title: 'True or False Questions',
      defaultInstruction: 'Read each statement carefully and select True if the statement is correct; otherwise select False.',
    },
    matching: {
      part: 'PART II',
      title: 'Matching Questions',
      defaultInstruction: 'Match each item in Column A with its corresponding definition or counterpart in Column B.',
    },
    multiple_choice: {
      part: 'PART III',
      title: 'Multiple Choice Questions',
      defaultInstruction: 'Choose the single best answer for each question from the given alternatives (A, B, C, or D).',
    },
    fill_in_the_blank: {
      part: 'PART IV',
      title: 'Fill in the Blank Questions',
      defaultInstruction: 'Complete each sentence by writing the correct term, code, or value in the designated blank space.',
    },
    short_answer: {
      part: 'PART V',
      title: 'Short Answer & Descriptive Questions',
      defaultInstruction: 'Provide clear, concise, and structured written responses according to the allocated marks.',
    },
  }

  // If a single type is selected in tabs, only show that section
  const typesToRender = questionTypeFilter.value === 'all'
    ? ['true_false', 'matching', 'multiple_choice', 'fill_in_the_blank', 'short_answer']
    : [questionTypeFilter.value]

  const sections: any[] = []

  typesToRender.forEach(typeKey => {
    const questionsInType = filteredQuestionsList.value.filter((q: any) => q.normalizedType === typeKey)
    if (questionsInType.length > 0) {
      const cfg = typeConfig[typeKey] || {
        part: 'PART',
        title: `${typeKey.toUpperCase()} QUESTIONS`,
        defaultInstruction: 'Answer all questions in this section.',
      }
      
      const totalMarks = questionsInType.reduce((acc: number, q: any) => acc + Number(q.marks || 0), 0)
      
      // Determine common instruction
      const firstInst = questionsInType[0]?.instruction
      const sectionInstruction = firstInst || cfg.defaultInstruction

      sections.push({
        typeKey,
        partLabel: cfg.part,
        title: cfg.title,
        instruction: sectionInstruction,
        totalQuestions: questionsInType.length,
        totalMarks,
        questions: questionsInType,
      })
    }
  })

  return sections
})

const showPaperExportDropdown = ref(false)

const printOfficialPaper = () => {
  window.print()
}

const stripHtml = (html: string): string => {
  if (!html) return ''
  const tmp = document.createElement('DIV')
  tmp.innerHTML = html
  return (tmp.textContent || tmp.innerText || '').trim()
}

// ── Export Exam Paper For Hard-Copy & Paper System ─────────────────────────────
const generateHardCopyExamDocumentHtml = () => {
  if (!selectedExam.value) return ''
  const exam = selectedExam.value
  const course = exam.course || exam.courseName || 'Course'
  const dept = exam.department || 'Information Technology'
  const instructor = exam.instructor || 'Administrator'
  const duration = exam.duration || `${exam.duration_minutes || 60}m`
  const maxMarks = exam.totalMarks || exam.total_marks || questionTypeSummary.value.all.marks || 100

  let doc = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>${exam.title} - Official Examination Paper</title>
  <style>
    @page { size: A4; margin: 20mm; }
    body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; line-height: 1.4; color: #111; margin: 0; padding: 0; }
    .header-table { width: 100%; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 12px; }
    .univ-name { font-size: 16pt; font-weight: bold; text-align: center; text-transform: uppercase; margin: 0; }
    .office-name { font-size: 11pt; font-weight: bold; text-align: center; text-transform: uppercase; margin: 3px 0 6px 0; }
    .exam-title { font-size: 13pt; font-weight: bold; text-align: center; margin: 4px 0; }
    .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 10pt; }
    .meta-table td { border: 1px solid #999; padding: 4px 8px; }
    .student-slip { width: 100%; border-collapse: collapse; margin-bottom: 14px; border: 2px solid #000; font-size: 10pt; }
    .student-slip td { border: 1px solid #ccc; padding: 5px 8px; }
    .slip-header { background-color: #f2f2f2; font-weight: bold; text-transform: uppercase; font-size: 9pt; }
    .instructions-box { border: 1px solid #888; background-color: #fbfbfb; padding: 8px 12px; margin-bottom: 16px; font-size: 9.5pt; }
    .section-title { font-size: 12pt; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #444; padding-bottom: 3px; margin-top: 18px; margin-bottom: 6px; }
    .section-direction { font-style: italic; font-size: 9.5pt; margin-bottom: 10px; color: #333; }
    .question-block { margin-bottom: 14px; page-break-inside: avoid; break-inside: avoid; }
    .q-head { font-weight: bold; }
    .q-marks { float: right; font-weight: bold; font-size: 9.5pt; }
    .options-grid { width: 100%; margin-top: 5px; margin-left: 12px; }
    .options-grid td { padding: 3px 6px; width: 50%; vertical-align: top; }
    .matching-tbl { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 10pt; }
    .matching-tbl th, .matching-tbl td { border: 1px solid #777; padding: 6px 8px; vertical-align: top; }
    .matching-tbl th { background-color: #f0f0f0; text-align: left; }
    .answer-line { display: inline-block; border-bottom: 1px solid #000; min-width: 150px; height: 16px; }
    .ruled-space { border-bottom: 1px dotted #888; height: 22px; margin-top: 6px; }
    .invigilator-sign { width: 100%; margin-top: 30px; border-top: 1px solid #000; padding-top: 8px; font-size: 10pt; }
  </style>
</head>
<body>
  <div class="header-table">
    <div class="univ-name">Wollo University</div>
    <div class="office-name">Office of the Examination Controller & Academic Quality Directorate</div>
    <div class="exam-title">${exam.title}</div>
  </div>

  <table class="meta-table">
    <tr>
      <td style="width: 50%;"><strong>Course:</strong> ${course} (${exam.courseCode || exam.course_code || 'N/A'})</td>
      <td style="width: 50%;"><strong>Department:</strong> ${dept}</td>
    </tr>
    <tr>
      <td><strong>Instructor / Examiner:</strong> ${instructor}</td>
      <td><strong>Academic Year:</strong> ${exam.year || '1st Year'} • ${exam.semester || 'Semester 1'}</td>
    </tr>
    <tr>
      <td><strong>Time Allowed:</strong> ${duration}</td>
      <td><strong>Maximum Score:</strong> ${maxMarks} Marks</td>
    </tr>
  </table>

  <table class="student-slip">
    <tr class="slip-header">
      <td colspan="2">Candidate Identification Slip (To be filled in blue or black ink)</td>
    </tr>
    <tr>
      <td style="width: 50%;">Student Full Name: _____________________________________________</td>
      <td style="width: 50%;">Student ID Number: _______________________</td>
    </tr>
    <tr>
      <td>Section / Class: _________________________________________________</td>
      <td>Desk / Seat Number: ______________________</td>
    </tr>
  </table>

  <div class="instructions-box">
    <strong>GENERAL EXAMINATION INSTRUCTIONS:</strong>
    <ol style="margin: 4px 0 0 18px; padding: 0;">
      <li>Ensure that you write your Full Name and Student ID accurately on the slip above.</li>
      <li>Carefully read the specific directions for each examination section before proceeding.</li>
      <li>All cellphones, unauthorized books, notes, or smart accessories are strictly forbidden.</li>
      <li>Raise your hand quietly if you encounter any typographical error on this question paper.</li>
    </ol>
  </div>
`

  organizedExamSections.value.forEach(section => {
    doc += `
      <div class="section-title">
        ${section.partLabel}: ${section.title} (${section.totalQuestions} Questions • ${section.totalMarks} Marks)
      </div>
      <div class="section-direction">
        <strong>Direction:</strong> ${section.instruction}
      </div>
    `

    section.questions.forEach((q: any) => {
      doc += `
        <div class="question-block">
          <div>
            <span class="q-head">${q.globalIndex}.</span>
            <span>${stripHtml(q.text)}</span>
            <span class="q-marks">[${q.marks} Marks]</span>
          </div>
      `

      if (q.normalizedType === 'true_false') {
        doc += `
          <div style="margin-left: 20px; margin-top: 5px;">
            <span style="display: inline-block; width: 140px;">( &nbsp; ) &nbsp; <strong>A.</strong> True</span>
            <span style="display: inline-block; width: 140px;">( &nbsp; ) &nbsp; <strong>B.</strong> False</span>
          </div>
        `
      } else if (q.normalizedType === 'multiple_choice') {
        const opts = parseMultipleChoiceOptions(q)
        doc += `<table class="options-grid"><tr>`
        opts.forEach((opt: any, oIdx: number) => {
          if (oIdx > 0 && oIdx % 2 === 0) doc += `</tr><tr>`
          doc += `<td>( &nbsp; ) &nbsp; <strong>${opt.label}.</strong> ${stripHtml(opt.text)}</td>`
        })
        doc += `</tr></table>`
      } else if (q.normalizedType === 'matching') {
        const mData = parseMatchingData(q)
        doc += `
          <table class="matching-tbl">
            <thead>
              <tr>
                <th style="width: 50%;">${mData.columnATitle}</th>
                <th style="width: 50%;">${mData.columnBTitle}</th>
              </tr>
            </thead>
            <tbody>
        `
        const maxRows = Math.max(mData.columnA.length, mData.columnB.length)
        for (let i = 0; i < maxRows; i++) {
          const a = mData.columnA[i]
          const b = mData.columnB[i]
          doc += `
            <tr>
              <td>${a ? `<strong>${a.id}.</strong> ${stripHtml(a.text)}` : ''}</td>
              <td>${b ? `<strong>${b.label}.</strong> ${stripHtml(b.text)}` : ''}</td>
            </tr>
          `
        }
        doc += `
            </tbody>
          </table>
          <div style="margin-top: 6px; font-size: 9.5pt;">
            <strong>Write Your Matches Here:</strong> &nbsp;
            ${mData.columnA.map((a: any) => `<strong>${a.id}</strong>: [ &nbsp; &nbsp; ]`).join(' &nbsp; &nbsp; ')}
          </div>
        `
      } else if (q.normalizedType === 'fill_in_the_blank') {
        doc += `
          <div style="margin-left: 20px; margin-top: 6px;">
            <strong>Your Answer:</strong> <span class="answer-line" style="min-width: 250px;"></span>
          </div>
        `
      } else if (q.normalizedType === 'short_answer') {
        doc += `
          <div style="margin-left: 10px; margin-top: 6px;">
            <div class="ruled-space"></div>
            <div class="ruled-space"></div>
            <div class="ruled-space"></div>
          </div>
        `
      }

      doc += `</div>`
    })
  })

  doc += `
    <table class="invigilator-sign">
      <tr>
        <td style="width: 50%;">Invigilator / Proctor Signature: _______________________</td>
        <td style="width: 50%; text-align: right;">Date & Time: _______________________</td>
      </tr>
    </table>
  </body>
  </html>`

  return doc
}

const exportExamPaperAsWord = () => {
  if (!selectedExam.value) return
  const docHtml = generateHardCopyExamDocumentHtml()
  const exam = selectedExam.value
  const code = exam.code || exam.examCode || `EXM-${exam.id}`
  const cleanTitle = (exam.title || 'Exam').replace(/[^a-zA-Z0-9_-]/g, '_')

  const blob = new Blob(['\ufeff', docHtml], { type: 'application/msword' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${code}_${cleanTitle}_Exam_Paper.doc`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
  showToast('Examination paper exported successfully as Word Document (.doc).', 'success')
}

const exportExamPaperAsHtml = () => {
  if (!selectedExam.value) return
  const docHtml = generateHardCopyExamDocumentHtml()
  const exam = selectedExam.value
  const code = exam.code || exam.examCode || `EXM-${exam.id}`
  const cleanTitle = (exam.title || 'Exam').replace(/[^a-zA-Z0-9_-]/g, '_')

  const blob = new Blob([docHtml], { type: 'text/html;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${code}_${cleanTitle}_Exam_Paper.html`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
  showToast('Offline examination paper exported successfully (.html).', 'success')
}

const exportExamPaperAsText = () => {
  if (!selectedExam.value) return
  const exam = selectedExam.value
  const code = exam.code || exam.examCode || `EXM-${exam.id}`
  const cleanTitle = (exam.title || 'Exam').replace(/[^a-zA-Z0-9_-]/g, '_')

  let txt = `========================================================================\n`
  txt += `WOLLO UNIVERSITY - OFFICIAL EXAMINATION PAPER\n`
  txt += `========================================================================\n`
  txt += `Title: ${exam.title}\n`
  txt += `Course: ${exam.course || exam.courseName} (${exam.courseCode || exam.course_code})\n`
  txt += `Department: ${exam.department || 'Information Technology'}\n`
  txt += `Instructor: ${exam.instructor}\n`
  txt += `Time Allowed: ${exam.duration} | Maximum Marks: ${exam.totalMarks || exam.total_marks}\n`
  txt += `------------------------------------------------------------------------\n`
  txt += `CANDIDATE SLIP:\n`
  txt += `Student Name: __________________________  ID: ____________  Section: ___\n`
  txt += `========================================================================\n\n`

  organizedExamSections.value.forEach(section => {
    txt += `------------------------------------------------------------------------\n`
    txt += `${section.partLabel}: ${section.title.toUpperCase()} (${section.totalQuestions} Questions - ${section.totalMarks} Marks)\n`
    txt += `Direction: ${section.instruction}\n`
    txt += `------------------------------------------------------------------------\n\n`

    section.questions.forEach((q: any) => {
      txt += `${q.globalIndex}. ${stripHtml(q.text)} [${q.marks} Marks]\n`
      if (q.normalizedType === 'true_false') {
        txt += `   ( ) A. True\n   ( ) B. False\n\n`
      } else if (q.normalizedType === 'multiple_choice') {
        const opts = parseMultipleChoiceOptions(q)
        opts.forEach((opt: any) => {
          txt += `   ( ) ${opt.label}. ${stripHtml(opt.text)}\n`
        })
        txt += `\n`
      } else if (q.normalizedType === 'matching') {
        const mData = parseMatchingData(q)
        txt += `   Column A:                          Column B:\n`
        const maxR = Math.max(mData.columnA.length, mData.columnB.length)
        for (let i = 0; i < maxR; i++) {
          const a = mData.columnA[i] ? `${mData.columnA[i].id}. ${stripHtml(mData.columnA[i].text)}` : ''
          const b = mData.columnB[i] ? `${mData.columnB[i].label}. ${stripHtml(mData.columnB[i].text)}` : ''
          txt += `   ${a.padEnd(35)} ${b}\n`
        }
        txt += `\n`
      } else if (q.normalizedType === 'fill_in_the_blank') {
        txt += `   Answer: ___________________________________\n\n`
      } else if (q.normalizedType === 'short_answer') {
        txt += `   Response space:\n   ___________________________________________________________\n   ___________________________________________________________\n\n`
      }
    })
  })

  const blob = new Blob([txt], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${code}_${cleanTitle}_Questions.txt`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
  showToast('Examination questions exported as Plain Text (.txt).', 'success')
}

// ── Dropdown Controls ────────────────────────────────────────────────────────
const toggleActionDropdown = (id: number) => {
  activeActionDropdown.value = activeActionDropdown.value === id ? null : id
}

const handleActionClick = (action: () => void) => {
  activeActionDropdown.value = null
  action()
}

const closeDropdowns = () => {
  activeActionDropdown.value = null
  showExportDropdown.value = false
  showColumnDropdown.value = false
  showPaperExportDropdown.value = false
}

// ── Backend API Actions ───────────────────────────────────────────────────────
const fetchExams = async (silent = false) => {
  if (!silent) isLoading.value = true
  try {
    const res = await apiClient.get('/admin/exams')
    allExams.value = res.data.data.exams || []
    if (res.data.data.stats) {
      statsData.value = res.data.data.stats
    }
    if (res.data.data.courses) backendCourses.value = res.data.data.courses
    if (res.data.data.departments) backendDepartments.value = res.data.data.departments
    if (res.data.data.instructors) backendInstructors.value = res.data.data.instructors
  } catch (err) {
    console.error('Failed to fetch admin exams', err)
    showToast('Failed to load examination records.', 'error')
  } finally {
    isLoading.value = false
  }
}

const openDetailsPage = async (exam: any) => {
  selectedExam.value = exam
  showDetailsPage.value = true
  showAllQuestions.value = false
  window.scrollTo({ top: 0, behavior: 'smooth' })
  try {
    const res = await apiClient.get(`/admin/exams/${exam.id}`)
    selectedExam.value = res.data.data
  } catch (error) {
    console.error('Failed to load exam details', error)
    showToast('Could not load detailed examination data.', 'error')
  }
}

const openQuestionsPage = async (exam: any) => {
  selectedExam.value = exam
  showAllQuestions.value = true
  showDetailsPage.value = false
  window.scrollTo({ top: 0, behavior: 'smooth' })
  try {
    const res = await apiClient.get(`/admin/exams/${exam.id}`)
    selectedExam.value = res.data.data
  } catch (error) {
    console.error('Failed to load exam questions', error)
    showToast('Could not load questions for this examination.', 'error')
  }
}

// ── Cancel & Reinstate Actions ───────────────────────────────────────────────
const promptCancelModal = (exam: any) => {
  selectedExam.value = exam
  cancellationReason.value = ''
  showCancelModal.value = true
}

const executeCancelExam = async () => {
  if (!selectedExam.value) return
  if (!cancellationReason.value.trim() || cancellationReason.value.trim().length < 4) {
    showToast('Please provide a cancellation reason (minimum 4 characters).', 'error')
    return
  }

  isCancelling.value = true
  try {
    const res = await apiClient.post(`/admin/exams/${selectedExam.value.id}/cancel`, {
      reason: cancellationReason.value.trim()
    })
    showToast(res.data?.message || 'Examination cancelled successfully.', 'success')
    showCancelModal.value = false
    await fetchExams(true)
    if (showDetailsPage.value && selectedExam.value) {
      await openDetailsPage(selectedExam.value)
    }
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Failed to cancel examination.'
    showToast(msg, 'error')
  } finally {
    isCancelling.value = false
  }
}

const executeReinstateExam = async (exam: any) => {
  try {
    const res = await apiClient.post(`/admin/exams/${exam.id}/reinstate`)
    showToast(res.data?.message || 'Examination reinstated successfully.', 'success')
    await fetchExams(true)
    if (showDetailsPage.value && selectedExam.value?.id === exam.id) {
      await openDetailsPage(exam)
    }
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Failed to reinstate examination.'
    showToast(msg, 'error')
  }
}

// ── Delete Action ────────────────────────────────────────────────────────────
const confirmDelete = (exam: any) => {
  selectedExam.value = exam
  showDeleteModal.value = true
}

const deleteExam = async () => {
  if (!selectedExam.value) return
  try {
    await apiClient.delete(`/admin/exams/${selectedExam.value.id}`)
    showToast(`Exam "${selectedExam.value.title}" deleted successfully.`, 'success')
    showDeleteModal.value = false
    if (showDetailsPage.value || showAllQuestions.value) {
      showDetailsPage.value = false
      showAllQuestions.value = false
    }
    await fetchExams(true)
  } catch (error) {
    console.error('Failed to delete exam', error)
    showToast('Failed to delete examination.', 'error')
  }
}

// ── Create & Edit Modal Actions ──────────────────────────────────────────────
const openCreateModal = () => {
  isEditing.value = false
  examFormErrors.value = {}
  examForm.value = {
    title: '',
    course_code: backendCourses.value[0]?.code || '',
    course_name: backendCourses.value[0]?.title || '',
    section: 'Both Sections',
    duration_minutes: 60,
    total_marks: 100,
    status: 'published',
    scheduled_at: '',
    instructor_id: '',
    exam_type: 'Mid Exam',
    shuffle_questions: true,
    shuffle_options: true,
    allow_backtracking: true,
    fullscreen_mode: true,
    tab_monitoring: true,
    disable_right_click: true,
    disable_copy_paste: true,
    allow_calculator: false,
    show_one_question: false,
  }
  showFormModal.value = true
}

const openEditModal = (exam: any) => {
  isEditing.value = true
  selectedExam.value = exam
  examFormErrors.value = {}
  examForm.value = {
    title: exam.title || '',
    course_code: exam.courseCode || exam.course_code || '',
    course_name: exam.courseName || exam.course || '',
    section: exam.section || 'Both Sections',
    duration_minutes: exam.duration_minutes || 60,
    total_marks: exam.total_marks || exam.totalMarks || 100,
    status: exam.status || 'published',
    scheduled_at: exam.scheduled_at ? exam.scheduled_at.slice(0, 16) : '',
    instructor_id: exam.instructor_id ? String(exam.instructor_id) : '',
    exam_type: exam.type || exam.exam_type || 'Mid Exam',
    shuffle_questions: exam.settings?.shuffleQuestions ?? true,
    shuffle_options: exam.settings?.shuffleAnswers ?? true,
    allow_backtracking: exam.settings?.allowBacktracking ?? true,
    fullscreen_mode: exam.settings?.enableFullscreenMode ?? true,
    tab_monitoring: exam.settings?.enableBrowserTabMonitoring ?? true,
    disable_right_click: exam.settings?.disableRightClick ?? true,
    disable_copy_paste: exam.settings?.disableCopyPaste ?? true,
    allow_calculator: exam.settings?.allowCalculator ?? false,
    show_one_question: exam.settings?.showOneQuestionAtATime ?? false,
  }
  showFormModal.value = true
}

const onCourseSelect = (event: Event) => {
  const code = (event.target as HTMLSelectElement).value
  const found = backendCourses.value.find(c => c.code === code)
  if (found) {
    examForm.value.course_name = found.title
    examForm.value.course_code = found.code
  }
}

const saveExamForm = async () => {
  examFormErrors.value = {}
  if (!examForm.value.title.trim()) {
    examFormErrors.value.title = 'Examination title is required.'
  }
  if (!examForm.value.duration_minutes || examForm.value.duration_minutes < 1) {
    examFormErrors.value.duration_minutes = 'Duration must be at least 1 minute.'
  }
  if (!examForm.value.total_marks || examForm.value.total_marks < 1) {
    examFormErrors.value.total_marks = 'Total marks must be at least 1.'
  }

  if (Object.keys(examFormErrors.value).length > 0) return

  isSaving.value = true
  try {
    const payload = {
      title: examForm.value.title.trim(),
      course_code: examForm.value.course_code,
      course_name: examForm.value.course_name,
      section: examForm.value.section,
      duration_minutes: Number(examForm.value.duration_minutes),
      total_marks: Number(examForm.value.total_marks),
      status: examForm.value.status,
      scheduled_at: examForm.value.scheduled_at || null,
      instructor_id: examForm.value.instructor_id || null,
      settings: {
        exam_type: examForm.value.exam_type,
        shuffleQuestions: examForm.value.shuffle_questions,
        shuffleAnswers: examForm.value.shuffle_options,
        allowBacktracking: examForm.value.allow_backtracking,
        showOneQuestionAtATime: examForm.value.show_one_question,
        enableFullscreenMode: examForm.value.fullscreen_mode,
        enableBrowserTabMonitoring: examForm.value.tab_monitoring,
        disableRightClick: examForm.value.disable_right_click,
        disableCopyPaste: examForm.value.disable_copy_paste,
        allowCalculator: examForm.value.allow_calculator,
      }
    }

    if (isEditing.value && selectedExam.value) {
      await apiClient.put(`/admin/exams/${selectedExam.value.id}`, payload)
      showToast(`Exam "${payload.title}" updated successfully.`, 'success')
    } else {
      await apiClient.post('/admin/exams', payload)
      showToast(`Exam "${payload.title}" scheduled successfully.`, 'success')
    }

    showFormModal.value = false
    await fetchExams(true)
    if (showDetailsPage.value && selectedExam.value) {
      await openDetailsPage(selectedExam.value)
    }
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Failed to save examination.'
    showToast(msg, 'error')
  } finally {
    isSaving.value = false
  }
}

// ── Export Handling (CSV / PDF) ───────────────────────────────────────────────
const handleExport = async (format: 'csv' | 'pdf') => {
  showExportDropdown.value = false
  isExporting.value = true
  try {
    const token = localStorage.getItem('auth_token')
    const params = new URLSearchParams()
    params.set('format', format)
    if (statusFilter.value !== 'all') params.set('status', statusFilter.value)
    if (departmentFilter.value !== 'all') params.set('department', departmentFilter.value)
    if (courseFilter.value !== 'all') params.set('course', courseFilter.value)
    if (debouncedSearch.value) params.set('search', debouncedSearch.value)

    const res = await fetch(`http://localhost:8000/api/v1/admin/exams-export?${params.toString()}`, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    })
    if (!res.ok) throw new Error('Export failed')
    const data = await res.json()
    const byteChars = atob(data.file)
    const byteNums = new Array(byteChars.length)
    for (let i = 0; i < byteChars.length; i++) byteNums[i] = byteChars.charCodeAt(i)
    const blob = new Blob([new Uint8Array(byteNums)], {
      type: format === 'pdf' ? 'text/html' : 'text/csv',
    })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = data.filename || `wollo_university_exams_${new Date().toISOString().slice(0, 10)}.${format === 'pdf' ? 'html' : 'csv'}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)

    showToast(`Exams exported successfully as ${format.toUpperCase()}.`, 'success')
  } catch (err) {
    showToast('Failed to export exams report. Please try again.', 'error')
  } finally {
    isExporting.value = false
  }
}

onMounted(() => {
  fetchExams()
  document.addEventListener('click', closeDropdowns)
})

onUnmounted(() => {
  document.removeEventListener('click', closeDropdowns)
})
</script>

<template>
  <div class="space-y-6 min-w-0 w-full font-sans pb-12">

    <!-- ────────────────────────────────────────────────────────────────────────
         VIEW 1: EXAM LIST (MAIN ERP VIEW)
    ──────────────────────────────────────────────────────────────────────── -->
    <div v-if="!showDetailsPage && !showAllQuestions" class="space-y-6 min-w-0 w-full animate-in fade-in duration-200">

      <!-- Institutional Breadcrumbs & Page Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <span class="hover:text-indigo-600 cursor-pointer">Admin Portal</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <span class="hover:text-indigo-600 cursor-pointer">Examinations</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <span class="text-slate-700 font-bold">Exam Management Center</span>
          </div>
          <div class="flex items-center gap-3">
            <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">Examination Management Center</h1>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-[#4338ca] border border-indigo-100">
              {{ statsData.total }} Exams Registered
            </span>
          </div>
        </div>

        <!-- Header Actions: Refresh, Export, Create Exam -->
        <div class="flex items-center gap-2.5">
          <!-- Refresh -->
          <button
            @click="fetchExams(false)"
            :disabled="isLoading"
            class="p-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 transition-colors shadow-sm disabled:opacity-50"
            title="Refresh Data"
          >
            <svg class="w-4 h-4" :class="{ 'animate-spin': isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </button>

          <!-- Export Dropdown -->
          <div class="relative">
            <button
              @click.stop="showExportDropdown = !showExportDropdown"
              class="flex items-center gap-2 px-3.5 py-2.5 bg-white border border-slate-200 text-slate-700 font-bold rounded-xl text-xs hover:bg-slate-50 transition-colors shadow-sm"
            >
              <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              <span>Export</span>
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7 7" /></svg>
            </button>

            <div
              v-if="showExportDropdown"
              @click.stop
              class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-40 animate-in fade-in duration-100"
            >
              <button
                @click="handleExport('csv')"
                :disabled="isExporting"
                class="w-full text-left px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2 transition-colors"
              >
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <span>Export as CSV</span>
              </button>
              <button
                @click="handleExport('pdf')"
                :disabled="isExporting"
                class="w-full text-left px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2 transition-colors"
              >
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                <span>Export as PDF (Report)</span>
              </button>
            </div>
          </div>

          <!-- Create Exam Button -->
          <button
            @click="openCreateModal"
            class="flex items-center gap-2 px-4 py-2.5 bg-[#4338ca] text-white font-bold rounded-xl text-xs hover:bg-indigo-800 transition-colors shadow-sm shadow-indigo-200"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>Create Exam</span>
          </button>
        </div>
      </div>

      <!-- Real-Data Interactive KPI Cards (5 Cards) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4">
        <!-- 1. Total Exams -->
        <div
          @click="selectKpiStatus('all')"
          class="bg-white p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer shadow-sm group hover:border-indigo-300"
          :class="statusFilter === 'all' ? 'border-[#4338ca] ring-2 ring-indigo-100' : 'border-slate-100'"
        >
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Exams</span>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#4338ca] flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
            </div>
          </div>
          <h3 class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ statsData.total }}</h3>
          <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
            <span>Platform-wide exams</span>
          </p>
        </div>

        <!-- 2. Scheduled Exams -->
        <div
          @click="selectKpiStatus('scheduled')"
          class="bg-white p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer shadow-sm group hover:border-amber-300"
          :class="statusFilter === 'scheduled' ? 'border-amber-500 ring-2 ring-amber-100' : 'border-slate-100'"
        >
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Scheduled</span>
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            </div>
          </div>
          <h3 class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ statsData.scheduled }}</h3>
          <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
            <span>Upcoming timetable</span>
          </p>
        </div>

        <!-- 3. Published Exams -->
        <div
          @click="selectKpiStatus('published')"
          class="bg-white p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer shadow-sm group hover:border-emerald-300"
          :class="statusFilter === 'published' ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-slate-100'"
        >
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Published</span>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
          </div>
          <h3 class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ statsData.published }}</h3>
          <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <span>Active & available</span>
          </p>
        </div>

        <!-- 4. Completed Exams -->
        <div
          @click="selectKpiStatus('completed')"
          class="bg-white p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer shadow-sm group hover:border-indigo-300"
          :class="statusFilter === 'completed' ? 'border-[#4338ca] ring-2 ring-indigo-100' : 'border-slate-100'"
        >
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Completed</span>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#4338ca] flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </div>
          </div>
          <h3 class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ statsData.completed }}</h3>
          <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-[#4338ca]"></span>
            <span>Finished sessions</span>
          </p>
        </div>

        <!-- 5. Cancelled Exams -->
        <div
          @click="selectKpiStatus('cancelled')"
          class="bg-white p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer shadow-sm group hover:border-rose-300"
          :class="statusFilter === 'cancelled' ? 'border-rose-500 ring-2 ring-rose-100' : 'border-slate-100'"
        >
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Cancelled</span>
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
            </div>
          </div>
          <h3 class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ statsData.cancelled }}</h3>
          <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
            <span>Revoked exams</span>
          </p>
        </div>
      </div>

      <!-- Main Layout: Search, Toolbar, Table, Pagination -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm flex flex-col min-w-0 overflow-hidden">

        <!-- Toolbar Section -->
        <div class="p-4 sm:p-5 border-b border-slate-100 space-y-3">
          <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
            <!-- Search Input -->
            <div class="relative flex-1 min-w-[200px]">
              <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
              <input
                v-model="search"
                type="text"
                placeholder="Search exams by title, course, course code, instructor..."
                class="w-full pl-9 pr-4 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:border-[#4338ca] bg-slate-50/50 focus:bg-white transition-colors"
              />
            </div>

            <!-- Course Filter -->
            <select
              v-model="courseFilter"
              class="text-xs border border-slate-200 rounded-xl px-3 py-2 text-slate-600 bg-white focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">All Courses</option>
              <option v-for="c in allCourses" :key="c" :value="c">{{ c }}</option>
            </select>

            <!-- Department Filter -->
            <select
              v-model="departmentFilter"
              class="text-xs border border-slate-200 rounded-xl px-3 py-2 text-slate-600 bg-white focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">All Departments</option>
              <option v-for="d in allDepartments" :key="d" :value="d">{{ d }}</option>
            </select>

            <!-- Year Level Filter -->
            <select
              v-model="yearFilter"
              class="text-xs border border-slate-200 rounded-xl px-3 py-2 text-slate-600 bg-white focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">All Years</option>
              <option v-for="y in allYears" :key="y" :value="y">{{ y }}</option>
            </select>

            <!-- Semester Filter -->
            <select
              v-model="semesterFilter"
              class="text-xs border border-slate-200 rounded-xl px-3 py-2 text-slate-600 bg-white focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">All Semesters</option>
              <option v-for="s in allSemesters" :key="s" :value="s">{{ s }}</option>
            </select>

            <!-- Exam Type Filter -->
            <select
              v-model="typeFilter"
              class="text-xs border border-slate-200 rounded-xl px-3 py-2 text-slate-600 bg-white focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">All Exam Types</option>
              <option v-for="t in examTypes" :key="t" :value="t">{{ t }}</option>
            </select>

            <!-- Status Filter -->
            <select
              v-model="statusFilter"
              class="text-xs border border-slate-200 rounded-xl px-3 py-2 text-slate-600 bg-white focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">All Status</option>
              <option value="published">Published</option>
              <option value="scheduled">Scheduled</option>
              <option value="completed">Completed</option>
              <option value="draft">Draft</option>
              <option value="cancelled">Cancelled</option>
            </select>

            <!-- Reset Filters -->
            <button
              v-if="activeFilterChips.length > 0"
              @click="clearAllFilters"
              class="px-3 py-2 text-xs font-bold text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors"
            >
              Clear Filters
            </button>

            <!-- Column Visibility Toggle -->
            <div class="relative ml-auto">
              <button
                @click.stop="showColumnDropdown = !showColumnDropdown"
                class="p-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-colors"
                title="Visible Columns"
              >
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
              </button>

              <div
                v-if="showColumnDropdown"
                @click.stop
                class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 p-2.5 z-40 space-y-1 animate-in fade-in"
              >
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider px-2 py-1">Columns</p>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.course" class="rounded text-indigo-600" /> Course Details
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.department" class="rounded text-indigo-600" /> Department
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.instructor" class="rounded text-indigo-600" /> Instructor
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.type" class="rounded text-indigo-600" /> Exam Type
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.schedule" class="rounded text-indigo-600" /> Schedule
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.duration" class="rounded text-indigo-600" /> Duration & Marks
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.metrics" class="rounded text-indigo-600" /> Questions & Attempts
                </label>
              </div>
            </div>
          </div>

          <!-- Active Filter Chips Bar -->
          <div v-if="activeFilterChips.length > 0" class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Filtered By:</span>
            <div
              v-for="chip in activeFilterChips"
              :key="chip.id"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-lg text-xs font-semibold"
            >
              <span>{{ chip.label }}</span>
              <button @click="chip.clear" class="hover:text-indigo-900 font-bold ml-1">×</button>
            </div>
          </div>
        </div>

        <!-- Real Data Table -->
        <div class="overflow-x-auto min-w-0">
          <table class="w-full text-left whitespace-nowrap min-w-max">
            <thead>
              <tr class="border-b border-slate-100 bg-slate-50/60">
                <th class="px-5 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Exam Title & Ref</th>
                <th v-if="visibleColumns.course" class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Course</th>
                <th v-if="visibleColumns.department" class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Department</th>
                <th v-if="visibleColumns.instructor" class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Instructor</th>
                <th v-if="visibleColumns.type" class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Exam Type</th>
                <th v-if="visibleColumns.schedule" class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Date & Time</th>
                <th v-if="visibleColumns.duration" class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Duration</th>
                <th v-if="visibleColumns.metrics" class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Q / Attempts</th>
                <th v-if="visibleColumns.status" class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Status</th>
                <th class="px-5 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(exam, index) in paginated"
                :key="exam.id"
                class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors group"
              >
                <!-- Title & Code -->
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-2">
                    <div>
                      <p class="text-xs font-bold text-slate-800 leading-snug hover:text-indigo-600 cursor-pointer" @click="openDetailsPage(exam)">
                        {{ exam.title }}
                      </p>
                      <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="text-[10px] font-mono font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 px-1.5 py-0.2 rounded">
                          {{ exam.code || exam.examCode }}
                        </span>
                        <span v-if="exam.section && exam.section !== 'Both'" class="text-[10px] text-slate-400">
                          • {{ exam.section }}
                        </span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Course -->
                <td v-if="visibleColumns.course" class="px-4 py-3.5">
                  <p class="text-xs font-bold text-slate-700">{{ exam.course || exam.courseName }}</p>
                  <p class="text-[10px] font-mono text-slate-400 mt-0.5">{{ exam.courseCode || exam.course_code }}</p>
                </td>

                <!-- Department & Year -->
                <td v-if="visibleColumns.department" class="px-4 py-3.5">
                  <p class="text-xs font-semibold text-slate-700">{{ exam.department }}</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">{{ exam.year || 'Year 3' }} • {{ exam.semester || 'Semester 1' }}</p>
                </td>

                <!-- Instructor -->
                <td v-if="visibleColumns.instructor" class="px-4 py-3.5">
                  <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-indigo-100 text-[#4338ca] flex items-center justify-center text-[10px] font-bold shrink-0">
                      {{ (exam.instructor || 'A').slice(0, 1) }}
                    </div>
                    <div>
                      <p class="text-xs font-semibold text-slate-700 leading-none">{{ exam.instructor }}</p>
                      <p class="text-[10px] text-slate-400 mt-0.5">{{ exam.instructorEmail || 'Faculty' }}</p>
                    </div>
                  </div>
                </td>

                <!-- Exam Type Badge -->
                <td v-if="visibleColumns.type" class="px-4 py-3.5 text-center">
                  <span
                    class="text-[10px] font-bold px-2.5 py-1 rounded-lg border inline-block"
                    :class="typeConfig[exam.type] || 'bg-slate-50 text-slate-600 border-slate-200'"
                  >
                    {{ exam.type }}
                  </span>
                </td>

                <!-- Schedule -->
                <td v-if="visibleColumns.schedule" class="px-4 py-3.5">
                  <p class="text-xs font-semibold text-slate-700">{{ exam.examDate }}</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">{{ exam.examTime }}</p>
                </td>

                <!-- Duration & Marks -->
                <td v-if="visibleColumns.duration" class="px-4 py-3.5 text-center">
                  <p class="text-xs font-bold text-slate-700">{{ exam.duration }}</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">{{ exam.totalMarks }} Marks</p>
                </td>

                <!-- Metrics (Questions / Attempts) -->
                <td v-if="visibleColumns.metrics" class="px-4 py-3.5 text-center">
                  <div class="inline-flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-700" :title="'Total Questions'">
                      {{ exam.questions_count }} Qs
                    </span>
                    <span class="text-slate-300">/</span>
                    <span class="text-xs font-bold text-indigo-600" :title="'Student Attempts'">
                      {{ exam.attempts_count }} Att.
                    </span>
                  </div>
                </td>

                <!-- Status Badge -->
                <td v-if="visibleColumns.status" class="px-4 py-3.5 text-center">
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold rounded-full capitalize border"
                    :class="statusConfig[exam.status]?.badge || 'bg-slate-100 text-slate-600 border-slate-200'"
                  >
                    <span
                      class="w-1.5 h-1.5 rounded-full"
                      :class="statusConfig[exam.status]?.dot || 'bg-slate-400'"
                    ></span>
                    <span>{{ statusConfig[exam.status]?.label || exam.status }}</span>
                  </span>
                </td>

                <!-- Actions Column (Three-Dot Menu ⋮) -->
                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                  <div class="relative inline-block text-left">
                    <button
                      @click.stop="toggleActionDropdown(exam.id)"
                      type="button"
                      class="w-8 h-8 rounded-xl inline-flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 border border-transparent hover:border-indigo-100 transition-all focus:outline-none"
                      :class="{ 'bg-indigo-50 text-indigo-700 border-indigo-200': activeActionDropdown === exam.id }"
                      title="Exam Actions"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                      </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div
                      v-if="activeActionDropdown === exam.id"
                      @click.stop
                      class="absolute right-0 w-48 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-50 select-none animate-in fade-in zoom-in-95 duration-100"
                      :class="index >= paginated.length - 2 && paginated.length > 2 ? 'bottom-full mb-1.5' : 'top-full mt-1.5'"
                    >
                      <!-- View Details -->
                      <button
                        @click="handleActionClick(() => openDetailsPage(exam))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>View Details</span>
                      </button>

                      <!-- View Questions -->
                      <button
                        @click="handleActionClick(() => openQuestionsPage(exam))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>View Questions ({{ exam.questions_count }})</span>
                      </button>

                      <!-- Cancel or Reinstate Action -->
                      <div class="h-px bg-slate-100 my-1"></div>

                      <button
                        v-if="!exam.is_cancelled && exam.status !== 'cancelled'"
                        @click="handleActionClick(() => promptCancelModal(exam))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-amber-500 group-hover/item:text-amber-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        <span>Cancel Exam</span>
                      </button>

                      <button
                        v-else
                        @click="handleActionClick(() => executeReinstateExam(exam))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-emerald-500 group-hover/item:text-emerald-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Reinstate Exam</span>
                      </button>
                    </div>
                  </div>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="paginated.length === 0">
                <td :colspan="10" class="py-16 text-center">
                  <div class="max-w-xs mx-auto space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    </div>
                    <p class="text-sm font-bold text-slate-700">No Examinations Found</p>
                    <p class="text-xs text-slate-400">Try adjusting your search criteria, clearing active filters, or creating a new examination.</p>
                    <button
                      v-if="activeFilterChips.length > 0"
                      @click="clearAllFilters"
                      class="px-4 py-2 bg-indigo-50 text-indigo-700 font-bold text-xs rounded-xl hover:bg-indigo-100 transition-colors"
                    >
                      Clear All Filters
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-5 py-4 border-t border-slate-100 bg-slate-50/40">
          <div class="text-xs text-slate-500">
            Showing <span class="font-bold text-slate-700">{{ filtered.length > 0 ? (currentPage - 1) * perPage + 1 : 0 }}</span> to
            <span class="font-bold text-slate-700">{{ Math.min(currentPage * perPage, filtered.length) }}</span> of
            <span class="font-bold text-slate-700">{{ filtered.length }}</span> exams
          </div>

          <div class="flex items-center gap-1.5">
            <button
              @click="currentPage = Math.max(1, currentPage - 1)"
              :disabled="currentPage === 1"
              class="p-2 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 transition-colors"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </button>

            <button
              v-for="p in totalPages"
              :key="p"
              @click="currentPage = p"
              class="w-8 h-8 rounded-xl border text-xs font-bold transition-colors"
              :class="currentPage === p ? 'bg-[#4338ca] text-white border-[#4338ca]' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
            >
              {{ p }}
            </button>

            <button
              @click="currentPage = Math.min(totalPages, currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="p-2 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 transition-colors"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Real Data Bottom Analytics Widgets -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">

        <!-- 1. Real Exam Overview Chart -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 sm:p-6">
          <h3 class="text-sm font-bold text-slate-800 mb-4">Exam Distribution Overview</h3>
          <div class="relative h-44 w-full">
            <Doughnut :data="chartData" :options="chartOptions" />
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
              <h4 class="text-xl font-black text-slate-800 leading-none">{{ statsData.total }}</h4>
              <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">Total Exams</p>
            </div>
          </div>
          <div class="space-y-2 mt-5">
            <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-emerald-500"></span><span class="font-semibold text-slate-700">Published</span></div>
              <span class="text-slate-500 font-bold">{{ statsData.published }} <span class="font-normal text-slate-400">({{ calcPct(statsData.published) }})</span></span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-500"></span><span class="font-semibold text-slate-700">Scheduled</span></div>
              <span class="text-slate-500 font-bold">{{ statsData.scheduled }} <span class="font-normal text-slate-400">({{ calcPct(statsData.scheduled) }})</span></span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-[#4338ca]"></span><span class="font-semibold text-slate-700">Completed</span></div>
              <span class="text-slate-500 font-bold">{{ statsData.completed }} <span class="font-normal text-slate-400">({{ calcPct(statsData.completed) }})</span></span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-slate-400"></span><span class="font-semibold text-slate-700">Draft / Cancelled</span></div>
              <span class="text-slate-500 font-bold">{{ statsData.draft + statsData.cancelled }} <span class="font-normal text-slate-400">({{ calcPct(statsData.draft + statsData.cancelled) }})</span></span>
            </div>
          </div>
        </div>

        <!-- 2. Real Top Exam Types -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 sm:p-6">
          <h3 class="text-sm font-bold text-slate-800 mb-4">Top Exam Types</h3>
          <div class="space-y-3">
            <div
              v-for="item in topExamTypes"
              :key="item.name"
              class="flex items-center justify-between text-xs p-2.5 rounded-xl bg-slate-50/80 border border-slate-100"
            >
              <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                <span class="font-semibold text-slate-700">{{ item.name }}</span>
              </div>
              <span class="font-black text-[#4338ca] bg-white border border-indigo-100 px-2 py-0.5 rounded-lg text-[11px]">
                {{ item.count }}
              </span>
            </div>
            <div v-if="topExamTypes.length === 0" class="text-center py-6 text-xs text-slate-400">
              No exam types recorded yet.
            </div>
          </div>
        </div>

        <!-- 3. Real Upcoming Exams -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 sm:p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-slate-800">Upcoming Schedule</h3>
            <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">Realtime</span>
          </div>
          <div class="space-y-3">
            <div
              v-for="exam in upcomingExams"
              :key="exam.id"
              class="flex items-start gap-3 p-2 rounded-xl hover:bg-slate-50 transition-colors cursor-pointer"
              @click="openDetailsPage(exam)"
            >
              <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-slate-800 truncate">{{ exam.title }}</p>
                <p class="text-[10px] text-slate-400 mt-0.5">{{ exam.examDate }} {{ exam.examTime }}</p>
              </div>
            </div>
            <div v-if="upcomingExams.length === 0" class="text-center py-6 text-xs text-slate-400">
              No upcoming examinations scheduled.
            </div>
          </div>
        </div>

        <!-- 4. Quick University Administration Actions -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 sm:p-6">
          <h3 class="text-sm font-bold text-slate-800 mb-4">Administrative Actions</h3>
          <div class="grid grid-cols-2 gap-3">
            <button
              @click="openCreateModal"
              class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/60 transition-colors text-[#4338ca]"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
              <span class="text-[10px] font-bold text-center">Create New<br>Exam</span>
            </button>
            <button
              @click="handleExport('csv')"
              class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors text-slate-700"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
              <span class="text-[10px] font-bold text-center">Export CSV<br>Spreadsheet</span>
            </button>
            <button
              @click="handleExport('pdf')"
              class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors text-slate-700"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              <span class="text-[10px] font-bold text-center">Print / PDF<br>Report</span>
            </button>
            <button
              @click="fetchExams(false)"
              class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors text-slate-700"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
              <span class="text-[10px] font-bold text-center">Sync &<br>Refresh</span>
            </button>
          </div>
        </div>

      </div>

    </div>

    <!-- ────────────────────────────────────────────────────────────────────────
         VIEW 2: MODERNIZED EXECUTIVE EXAM DETAILS VIEW
    ──────────────────────────────────────────────────────────────────────── -->
    <div v-if="showDetailsPage && !showAllQuestions" class="space-y-6 pb-12 min-w-0 w-full animate-in fade-in duration-200">
      
      <!-- Top Breadcrumbs & Header Bar -->
      <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-5">
        <div class="space-y-2">
          <!-- Breadcrumb navigation -->
          <nav class="flex items-center gap-2 text-xs font-semibold text-slate-400">
            <button @click="showDetailsPage = false" class="hover:text-indigo-600 transition-colors flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
              <span>Examinations</span>
            </button>
            <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <button @click="showDetailsPage = false" class="hover:text-indigo-600 transition-colors">Exam Management Center</button>
            <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <span class="text-slate-800 font-bold truncate max-w-[200px] sm:max-w-xs">{{ selectedExam?.title }}</span>
          </nav>

          <!-- Exam Title & Badges -->
          <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">{{ selectedExam?.title }}</h1>
            
            <!-- Real Status Badge -->
            <span
              class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full border shadow-2xs"
              :class="statusConfig[selectedExam?.status]?.badge || 'bg-slate-100 text-slate-700 border-slate-200'"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="statusConfig[selectedExam?.status]?.dot || 'bg-slate-400'"></span>
              <span>{{ statusConfig[selectedExam?.status]?.label || selectedExam?.status }}</span>
            </span>

            <!-- Official Exam Code Badge -->
            <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-lg bg-indigo-50/80 text-[#4338ca] border border-indigo-100/80 shadow-2xs">
              {{ selectedExam?.code || selectedExam?.examCode }}
            </span>

            <!-- Course Code -->
            <span v-if="selectedExam?.courseCode || selectedExam?.course_code" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 border border-slate-200">
              {{ selectedExam?.courseCode || selectedExam?.course_code }}
            </span>

            <!-- Exam Type Pill -->
            <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-purple-50 text-purple-700 border border-purple-200/60">
              {{ selectedExam?.type || selectedExam?.exam_type || 'Examination' }}
            </span>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2.5 self-start lg:self-center shrink-0">
          <!-- Questions View Button -->
          <button
            @click="openQuestionsPage(selectedExam)"
            class="px-4 py-2.5 bg-[#4338ca] hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition-all shadow-sm hover:shadow flex items-center gap-2"
          >
            <svg class="w-4 h-4 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            <span>Questions ({{ selectedExam?.questions?.length || selectedExam?.questions_count || 0 }})</span>
          </button>

          <!-- Cancel / Reinstate Exam Buttons -->
          <button
            v-if="!selectedExam?.is_cancelled && selectedExam?.status !== 'cancelled'"
            @click="promptCancelModal(selectedExam)"
            class="px-3.5 py-2.5 bg-white border border-rose-200 text-rose-700 hover:bg-rose-50 font-bold rounded-xl text-xs transition-colors shadow-2xs flex items-center gap-1.5"
            title="Cancel or suspend this examination"
          >
            <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            <span>Cancel Exam</span>
          </button>
          
          <button
            v-else
            @click="executeReinstateExam(selectedExam)"
            class="px-3.5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs transition-colors shadow-sm flex items-center gap-1.5"
            title="Reinstate this examination"
          >
            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            <span>Reinstate Exam</span>
          </button>

          <!-- Back Button -->
          <button
            @click="showDetailsPage = false"
            class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 font-bold rounded-xl text-xs hover:bg-slate-50 transition-colors shadow-2xs flex items-center gap-2"
          >
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            <span>Back to Exam List</span>
          </button>
        </div>
      </div>

      <!-- Cancellation Warning Banner (if cancelled) -->
      <div
        v-if="selectedExam?.is_cancelled || selectedExam?.status === 'cancelled'"
        class="bg-rose-50/90 border border-rose-200 rounded-2xl p-5 flex items-start gap-4 text-rose-800 shadow-sm"
      >
        <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
        </div>
        <div class="flex-1">
          <div class="flex items-center gap-2">
            <h3 class="text-sm font-bold text-rose-900">This Examination Has Been Cancelled by Administration</h3>
            <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-rose-200 text-rose-800">Inactive</span>
          </div>
          <p class="text-xs text-rose-700 mt-1">
            Official Reason: <span class="font-semibold text-rose-900">{{ selectedExam?.cancellation_reason || 'Administrative revocation' }}</span>
          </p>
          <p v-if="selectedExam?.cancelled_by_name" class="text-[11px] text-rose-600 mt-1">
            Revoked by <span class="font-semibold">{{ selectedExam?.cancelled_by_name }}</span> on {{ selectedExam?.cancelled_at ? new Date(selectedExam?.cancelled_at).toLocaleString() : 'N/A' }}
          </p>
        </div>
        <button
          @click="executeReinstateExam(selectedExam)"
          class="px-4 py-2 bg-white border border-rose-300 text-rose-700 hover:bg-rose-100 text-xs font-bold rounded-xl transition-colors shrink-0 shadow-2xs"
        >
          Reinstate Exam
        </button>
      </div>

      <!-- Executive Overview Summary Metrics (5 Modern Cards) -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <!-- Card 1: Schedule & Timing -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-1.5">
          <div class="flex items-center justify-between text-slate-400">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Scheduled Time</span>
            <div class="w-7 h-7 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            </div>
          </div>
          <div class="text-sm font-black text-slate-800 truncate">{{ selectedExam?.examDate || 'Scheduled' }}</div>
          <div class="text-[11px] font-medium text-slate-500">{{ selectedExam?.examTime || 'Time TBD' }} • {{ selectedExam?.duration || '60m' }}</div>
        </div>

        <!-- Card 2: Total Questions -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-1.5">
          <div class="flex items-center justify-between text-slate-400">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Exam Questions</span>
            <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            </div>
          </div>
          <div class="text-sm font-black text-slate-800">{{ allExamQuestions.length || selectedExam?.questions_count || 0 }} Questions</div>
          <div class="text-[11px] font-medium text-slate-500">
            {{ Object.keys(questionTypeSummary).filter(k => k !== 'all' && questionTypeSummary[k].count > 0).length }} Question Types
          </div>
        </div>

        <!-- Card 3: Marks & Weight -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-1.5">
          <div class="flex items-center justify-between text-slate-400">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Marks</span>
            <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
          </div>
          <div class="text-sm font-black text-slate-800">{{ selectedExam?.totalMarks || selectedExam?.total_marks || questionTypeSummary.all.marks }} Marks</div>
          <div class="text-[11px] font-medium text-slate-500">Pass mark: ~50% ({{ Math.round((selectedExam?.totalMarks || selectedExam?.total_marks || questionTypeSummary.all.marks || 100) * 0.5) }} pts)</div>
        </div>

        <!-- Card 4: Candidate Activity -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-1.5">
          <div class="flex items-center justify-between text-slate-400">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Submissions</span>
            <div class="w-7 h-7 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
            </div>
          </div>
          <div class="text-sm font-black text-slate-800">{{ selectedExam?.attempts_count || 0 }} Attempts</div>
          <div class="text-[11px] font-medium text-slate-500">Cohort: {{ selectedExam?.section || 'Section A' }}</div>
        </div>

        <!-- Card 5: Security Level -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-1.5 col-span-2 md:col-span-1">
          <div class="flex items-center justify-between text-slate-400">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Security Profile</span>
            <div class="w-7 h-7 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            </div>
          </div>
          <div class="text-sm font-black text-slate-800 flex items-center gap-1.5">
            <span>{{ selectedExam?.fullscreen_mode && selectedExam?.tab_monitoring ? 'Enforced Lock' : 'Standard' }}</span>
          </div>
          <div class="text-[11px] font-medium text-emerald-600 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <span>Anti-Cheat Active</span>
          </div>
        </div>
      </div>

      <!-- Details Specification Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">

        <!-- 1. Examination Metadata -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-3.5">
          <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2 pb-2.5 border-b border-slate-100">
            <div class="w-6 h-6 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <span>Course & Faculty Specifications</span>
          </h2>

          <div class="flex items-center justify-between text-xs border-b border-slate-50 pb-2">
            <span class="text-slate-500">Course</span>
            <div class="text-right">
              <span class="font-bold text-slate-800">{{ selectedExam?.course || selectedExam?.courseName || 'Not specified' }}</span>
              <p class="text-[10px] font-mono text-slate-400">({{ selectedExam?.courseCode || selectedExam?.course_code || 'N/A' }})</p>
            </div>
          </div>

          <div class="flex items-center justify-between text-xs border-b border-slate-50 pb-2">
            <span class="text-slate-500">Department</span>
            <span class="font-bold text-slate-800 capitalize">{{ selectedExam?.department || 'Information Technology' }}</span>
          </div>

          <div class="flex items-center justify-between text-xs border-b border-slate-50 pb-2">
            <span class="text-slate-500">Academic Year / Semester</span>
            <span class="font-bold text-slate-800">{{ selectedExam?.year || '1st Year' }} • {{ selectedExam?.semester || 'First Semester' }}</span>
          </div>

          <div class="flex items-center justify-between text-xs border-b border-slate-50 pb-2">
            <span class="text-slate-500">Section Assigned</span>
            <span class="font-bold text-slate-800 bg-slate-50 px-2 py-0.5 rounded border border-slate-100">{{ selectedExam?.section || 'Both Sections' }}</span>
          </div>

          <div class="flex items-center justify-between text-xs border-b border-slate-50 pb-2">
            <span class="text-slate-500">Instructor / Proctor</span>
            <div class="text-right flex items-center gap-2">
              <div class="w-6 h-6 rounded-full bg-indigo-100 text-[#4338ca] text-[10px] font-bold flex items-center justify-center shrink-0 uppercase">
                {{ (selectedExam?.instructor || 'A').slice(0, 1) }}
              </div>
              <div>
                <span class="font-bold text-slate-800 block">{{ selectedExam?.instructor || 'Administrator' }}</span>
                <p class="text-[10px] text-slate-400">{{ selectedExam?.instructorEmail || 'No email' }}</p>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between text-xs border-b border-slate-50 pb-2">
            <span class="text-slate-500">Exam Classification</span>
            <span class="font-bold text-[#4338ca] bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">{{ selectedExam?.type || 'Mid Exam' }}</span>
          </div>

          <div class="flex items-center justify-between text-xs border-b border-slate-50 pb-2">
            <span class="text-slate-500">Duration & Weight</span>
            <span class="font-bold text-slate-800">{{ selectedExam?.duration }} • {{ selectedExam?.totalMarks || selectedExam?.total_marks }} Marks</span>
          </div>

          <div class="flex items-center justify-between text-xs">
            <span class="text-slate-500">Scheduled Date & Time</span>
            <span class="font-bold text-slate-800">{{ selectedExam?.examDate }} {{ selectedExam?.examTime }}</span>
          </div>
        </div>

        <!-- 2. Exam Behavior Settings -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
          <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2 pb-2.5 border-b border-slate-100">
            <div class="w-6 h-6 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
            </div>
            <span>Exam Behavior Parameters</span>
          </h2>

          <div class="space-y-3">
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-700 font-semibold">Randomize Question Sequence</span>
              <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] border" :class="selectedExam?.shuffle_questions ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'">
                {{ selectedExam?.shuffle_questions ? 'Enabled' : 'Disabled' }}
              </span>
            </div>

            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-700 font-semibold">Shuffle Answer Options</span>
              <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] border" :class="selectedExam?.shuffle_options ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'">
                {{ selectedExam?.shuffle_options ? 'Enabled' : 'Disabled' }}
              </span>
            </div>

            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-700 font-semibold">Allow Backtracking</span>
              <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] border" :class="selectedExam?.allow_backtracking ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'">
                {{ selectedExam?.allow_backtracking ? 'Allowed' : 'Locked' }}
              </span>
            </div>

            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-700 font-semibold">Question Display Format</span>
              <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-slate-100 text-slate-700 border border-slate-200">
                {{ selectedExam?.show_one_question ? 'One by One' : 'All at Once' }}
              </span>
            </div>

            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-700 font-semibold">Review Screen Before Submit</span>
              <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] border" :class="selectedExam?.show_review_screen ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'">
                {{ selectedExam?.show_review_screen ? 'Enabled' : 'Disabled' }}
              </span>
            </div>
          </div>
        </div>

        <!-- 3. Proctoring & Security Matrix -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
          <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2 pb-2.5 border-b border-slate-100">
            <div class="w-6 h-6 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            </div>
            <span>Security & Anti-Cheat Controls</span>
          </h2>

          <div class="space-y-3">
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-700 font-semibold">Enforce Fullscreen Mode</span>
              <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] border" :class="selectedExam?.fullscreen_mode ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'">
                {{ selectedExam?.fullscreen_mode ? 'Enforced' : 'Off' }}
              </span>
            </div>

            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-700 font-semibold">Browser Tab Switching Lock</span>
              <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] border" :class="selectedExam?.tab_monitoring ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'">
                {{ selectedExam?.tab_monitoring ? 'Monitored' : 'Off' }}
              </span>
            </div>

            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-700 font-semibold">Disable Right-Click Context Menu</span>
              <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] border" :class="selectedExam?.disable_right_click ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'">
                {{ selectedExam?.disable_right_click ? 'Disabled' : 'Allowed' }}
              </span>
            </div>

            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-700 font-semibold">Clipboard / Copy-Paste Lock</span>
              <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] border" :class="selectedExam?.disable_copy_paste ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'">
                {{ selectedExam?.disable_copy_paste ? 'Blocked' : 'Allowed' }}
              </span>
            </div>

            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-700 font-semibold">On-Screen Calculator</span>
              <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] border" :class="selectedExam?.allow_calculator ? 'bg-indigo-50 text-[#4338ca] border-indigo-200' : 'bg-slate-100 text-slate-500 border-slate-200'">
                {{ selectedExam?.allow_calculator ? 'Available' : 'Disabled' }}
              </span>
            </div>

            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-700 font-semibold">Real-Time Webcam Proctoring</span>
              <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] border" :class="selectedExam?.webcam_monitoring ? 'bg-indigo-50 text-[#4338ca] border-indigo-200' : 'bg-slate-100 text-slate-500 border-slate-200'">
                {{ selectedExam?.webcam_monitoring ? 'Active' : 'Disabled' }}
              </span>
            </div>
          </div>
        </div>

      </div>

      <!-- Question Composition Breakdown Panel on Details Page -->
      <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
          <div>
            <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
              <svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
              <span>Question Paper Composition & Structure</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Distribution of question types and mark weights configured for this exam</p>
          </div>
          <button
            @click="openQuestionsPage(selectedExam)"
            class="px-4 py-2 bg-indigo-50 border border-indigo-200 text-[#4338ca] font-bold rounded-xl text-xs hover:bg-indigo-100 transition-colors shadow-2xs flex items-center gap-1.5 self-start sm:self-auto"
          >
            <span>Inspect Official Question Paper</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
          </button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/50 space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">True / False</span>
            <div class="text-base font-black text-slate-800">{{ questionTypeSummary.true_false.count }} Items</div>
            <div class="text-[11px] font-semibold text-emerald-600">{{ questionTypeSummary.true_false.marks }} Marks</div>
          </div>

          <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/50 space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Matching</span>
            <div class="text-base font-black text-slate-800">{{ questionTypeSummary.matching.count }} Sets</div>
            <div class="text-[11px] font-semibold text-emerald-600">{{ questionTypeSummary.matching.marks }} Marks</div>
          </div>

          <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/50 space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Multiple Choice</span>
            <div class="text-base font-black text-slate-800">{{ questionTypeSummary.multiple_choice.count }} Questions</div>
            <div class="text-[11px] font-semibold text-emerald-600">{{ questionTypeSummary.multiple_choice.marks }} Marks</div>
          </div>

          <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/50 space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Fill in the Blank</span>
            <div class="text-base font-black text-slate-800">{{ questionTypeSummary.fill_in_the_blank.count }} Questions</div>
            <div class="text-[11px] font-semibold text-emerald-600">{{ questionTypeSummary.fill_in_the_blank.marks }} Marks</div>
          </div>

          <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/50 space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Short Answer</span>
            <div class="text-base font-black text-slate-800">{{ questionTypeSummary.short_answer.count }} Questions</div>
            <div class="text-[11px] font-semibold text-emerald-600">{{ questionTypeSummary.short_answer.marks }} Marks</div>
          </div>
        </div>
      </div>

    </div>

    <!-- ────────────────────────────────────────────────────────────────────────
         VIEW 3: MODERNIZED OFFICIAL QUESTION PAPER (PROPER 5-TYPE RENDERING)
    ──────────────────────────────────────────────────────────────────────── -->
    <div v-if="showAllQuestions" class="space-y-6 pb-16 min-w-0 w-full animate-in fade-in duration-200">
      
      <!-- Top Breadcrumbs & Control Header -->
      <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-5 print:hidden">
        <div class="space-y-2">
          <!-- Breadcrumb navigation -->
          <nav class="flex items-center gap-2 text-xs font-semibold text-slate-400">
            <button @click="showAllQuestions = false; showDetailsPage = false" class="hover:text-indigo-600 transition-colors flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
              <span>Examinations</span>
            </button>
            <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <button @click="showAllQuestions = false; showDetailsPage = true" class="hover:text-indigo-600 transition-colors truncate max-w-[160px] sm:max-w-xs">
              {{ selectedExam?.title }}
            </button>
            <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <span class="text-slate-800 font-bold">Official Question Paper</span>
          </nav>

          <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Examination Question Paper</h1>
            
            <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-lg bg-indigo-50 text-[#4338ca] border border-indigo-100 shadow-2xs">
              {{ allExamQuestions.length }} Questions Attached
            </span>
            <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">
              {{ selectedExam?.totalMarks || selectedExam?.total_marks || questionTypeSummary.all.marks }} Total Marks
            </span>
            <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 border border-slate-200">
              {{ selectedExam?.duration }}
            </span>
          </div>
        </div>

        <!-- Master View Controls & Actions -->
        <div class="flex flex-wrap items-center gap-2.5 self-start lg:self-center shrink-0">
          
          <!-- Export Paper Dropdown -->
          <div class="relative">
            <button
              @click.stop="showPaperExportDropdown = !showPaperExportDropdown"
              class="px-4 py-2.5 bg-[#4338ca] hover:bg-[#3730a3] text-white font-bold rounded-xl text-xs transition-all shadow-sm flex items-center gap-2 group"
              title="Export examination paper for hard copy duplication and physical testing"
            >
              <svg class="w-4 h-4 text-indigo-200 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              <span>Export Paper</span>
              <svg class="w-3.5 h-3.5 text-indigo-300 transition-transform duration-200" :class="{ 'rotate-180': showPaperExportDropdown }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="showPaperExportDropdown"
              @click.stop
              class="absolute right-0 mt-2 w-72 bg-white rounded-2xl shadow-xl border border-slate-200 p-2 z-50 animate-in fade-in zoom-in-95 duration-150"
            >
              <div class="px-3 py-2 border-b border-slate-100">
                <p class="text-[11px] font-black uppercase tracking-wider text-slate-400">Hard-Copy Paper Export</p>
                <p class="text-xs text-slate-500 font-medium">Ready for physical examination & printing</p>
              </div>

              <div class="py-1 space-y-0.5">
                <!-- 1. Microsoft Word (.doc) -->
                <button
                  @click="showPaperExportDropdown = false; exportExamPaperAsWord()"
                  class="w-full text-left px-3 py-2.5 rounded-xl hover:bg-indigo-50/70 text-slate-700 hover:text-indigo-900 transition-colors flex items-start gap-3 group"
                >
                  <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 mt-0.5 border border-blue-100">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zM8.5 18H7l2.2-8h1.7l1.3 4.8 1.3-4.8h1.7l2.2 8h-1.5l-1.3-5-1.3 5h-1.5l-1.3-5-1.3 5z"/>
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-slate-800 group-hover:text-indigo-900 flex items-center justify-between">
                      <span>Word Document</span>
                      <span class="text-[10px] font-mono px-1.5 py-0.2 bg-blue-100/60 text-blue-700 rounded font-semibold">.doc</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-snug">Includes candidate slip, instructions & answer spaces for university duplicating press.</p>
                  </div>
                </button>

                <!-- 2. Print / Save as PDF -->
                <button
                  @click="showPaperExportDropdown = false; printOfficialPaper()"
                  class="w-full text-left px-3 py-2.5 rounded-xl hover:bg-indigo-50/70 text-slate-700 hover:text-indigo-900 transition-colors flex items-start gap-3 group"
                >
                  <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 mt-0.5 border border-rose-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-slate-800 group-hover:text-indigo-900 flex items-center justify-between">
                      <span>Print / PDF Document</span>
                      <span class="text-[10px] font-mono px-1.5 py-0.2 bg-rose-100/60 text-rose-700 rounded font-semibold">PDF</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-snug">Print-ready A4 hard copy with candidate slip and zero answers revealed.</p>
                  </div>
                </button>

                <!-- 3. Standalone Offline HTML -->
                <button
                  @click="showPaperExportDropdown = false; exportExamPaperAsHtml()"
                  class="w-full text-left px-3 py-2.5 rounded-xl hover:bg-indigo-50/70 text-slate-700 hover:text-indigo-900 transition-colors flex items-start gap-3 group"
                >
                  <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5 border border-emerald-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-slate-800 group-hover:text-indigo-900 flex items-center justify-between">
                      <span>Standalone Offline HTML</span>
                      <span class="text-[10px] font-mono px-1.5 py-0.2 bg-emerald-100/60 text-emerald-700 rounded font-semibold">.html</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-snug">Single self-contained file readable & printable on any browser offline.</p>
                  </div>
                </button>

                <!-- 4. Plain Text File (.txt) -->
                <button
                  @click="showPaperExportDropdown = false; exportExamPaperAsText()"
                  class="w-full text-left px-3 py-2.5 rounded-xl hover:bg-indigo-50/70 text-slate-700 hover:text-indigo-900 transition-colors flex items-start gap-3 group"
                >
                  <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 mt-0.5 border border-slate-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-slate-800 group-hover:text-indigo-900 flex items-center justify-between">
                      <span>Plain Text Document</span>
                      <span class="text-[10px] font-mono px-1.5 py-0.2 bg-slate-200 text-slate-700 rounded font-semibold">.txt</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-snug">Plain text format for archiving and standard text printing.</p>
                  </div>
                </button>
              </div>
            </div>
          </div>

          <!-- Direct Print Action Button -->
          <button
            @click="printOfficialPaper"
            class="px-3.5 py-2.5 bg-white border border-slate-200 text-slate-700 font-bold rounded-xl text-xs hover:bg-slate-50 transition-colors shadow-2xs flex items-center gap-1.5"
            title="Print examination paper directly via browser"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
            <span>Print</span>
          </button>

          <!-- Back to Exam Details -->
          <button
            @click="showAllQuestions = false; showDetailsPage = true"
            class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 font-bold rounded-xl text-xs hover:bg-slate-50 transition-colors shadow-2xs flex items-center gap-2"
          >
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            <span>Back to Exam Details</span>
          </button>
        </div>
      </div>

      <!-- Filter Tabs & Search Bar -->
      <div class="bg-white p-3 sm:p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3 print:hidden">
        <!-- Question Type Segmented Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0 scrollbar-none text-xs">
          <button
            @click="questionTypeFilter = 'all'"
            class="px-3 py-1.5 rounded-xl font-bold transition-all shrink-0 flex items-center gap-1.5"
            :class="questionTypeFilter === 'all' ? 'bg-[#4338ca] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          >
            <span>All Questions</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="questionTypeFilter === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
              {{ questionTypeSummary.all.count }}
            </span>
          </button>

          <button
            v-if="questionTypeSummary.true_false.count > 0"
            @click="questionTypeFilter = 'true_false'"
            class="px-3 py-1.5 rounded-xl font-bold transition-all shrink-0 flex items-center gap-1.5"
            :class="questionTypeFilter === 'true_false' ? 'bg-[#4338ca] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          >
            <span>True / False</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="questionTypeFilter === 'true_false' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
              {{ questionTypeSummary.true_false.count }}
            </span>
          </button>

          <button
            v-if="questionTypeSummary.matching.count > 0"
            @click="questionTypeFilter = 'matching'"
            class="px-3 py-1.5 rounded-xl font-bold transition-all shrink-0 flex items-center gap-1.5"
            :class="questionTypeFilter === 'matching' ? 'bg-[#4338ca] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          >
            <span>Matching</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="questionTypeFilter === 'matching' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
              {{ questionTypeSummary.matching.count }}
            </span>
          </button>

          <button
            v-if="questionTypeSummary.multiple_choice.count > 0"
            @click="questionTypeFilter = 'multiple_choice'"
            class="px-3 py-1.5 rounded-xl font-bold transition-all shrink-0 flex items-center gap-1.5"
            :class="questionTypeFilter === 'multiple_choice' ? 'bg-[#4338ca] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          >
            <span>Multiple Choice</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="questionTypeFilter === 'multiple_choice' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
              {{ questionTypeSummary.multiple_choice.count }}
            </span>
          </button>

          <button
            v-if="questionTypeSummary.fill_in_the_blank.count > 0"
            @click="questionTypeFilter = 'fill_in_the_blank'"
            class="px-3 py-1.5 rounded-xl font-bold transition-all shrink-0 flex items-center gap-1.5"
            :class="questionTypeFilter === 'fill_in_the_blank' ? 'bg-[#4338ca] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          >
            <span>Fill in Blank</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="questionTypeFilter === 'fill_in_the_blank' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
              {{ questionTypeSummary.fill_in_the_blank.count }}
            </span>
          </button>

          <button
            v-if="questionTypeSummary.short_answer.count > 0"
            @click="questionTypeFilter = 'short_answer'"
            class="px-3 py-1.5 rounded-xl font-bold transition-all shrink-0 flex items-center gap-1.5"
            :class="questionTypeFilter === 'short_answer' ? 'bg-[#4338ca] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          >
            <span>Short Answer</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="questionTypeFilter === 'short_answer' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
              {{ questionTypeSummary.short_answer.count }}
            </span>
          </button>
        </div>

        <!-- Search Input -->
        <div class="relative w-full md:w-64">
          <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
          <input
            v-model="searchQuestionQuery"
            type="text"
            placeholder="Search question text or key..."
            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-[#4338ca] focus:bg-white transition-all"
          />
          <button
            v-if="searchQuestionQuery"
            @click="searchQuestionQuery = ''"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 font-bold text-xs"
          >
            ×
          </button>
        </div>
      </div>

      <!-- Print-Only Publication Slip for Hard-Copy Examination -->
      <div class="hidden print:block border-2 border-black p-5 mb-6 text-black bg-white">
        <div class="text-center border-b-2 border-black pb-3 mb-4">
          <h2 class="text-xl font-bold uppercase tracking-wider">Wollo University</h2>
          <h3 class="text-xs font-semibold uppercase tracking-wide">Office of the Examination Controller & Directorate of Academic Quality</h3>
          <p class="text-base font-bold mt-1 text-slate-900">{{ selectedExam?.title }}</p>
        </div>

        <table class="w-full text-xs border-collapse mb-4">
          <tr>
            <td class="border border-slate-500 p-2 font-bold w-1/2">Course: {{ selectedExam?.course || selectedExam?.courseName }} ({{ selectedExam?.courseCode || selectedExam?.course_code || 'N/A' }})</td>
            <td class="border border-slate-500 p-2 font-bold w-1/2">Department: {{ selectedExam?.department || 'Information Technology' }}</td>
          </tr>
          <tr>
            <td class="border border-slate-500 p-2">Instructor / Examiner: {{ selectedExam?.instructor }}</td>
            <td class="border border-slate-500 p-2">Academic Year: {{ selectedExam?.year }} • {{ selectedExam?.section || 'Section A' }}</td>
          </tr>
          <tr>
            <td class="border border-slate-500 p-2">Time Allowed: {{ selectedExam?.duration }}</td>
            <td class="border border-slate-500 p-2 font-bold">Maximum Score: {{ selectedExam?.totalMarks || selectedExam?.total_marks || questionTypeSummary.all.marks }} Marks</td>
          </tr>
        </table>

        <!-- Candidate Identification Slip -->
        <div class="border-2 border-black p-3 bg-slate-50 mb-4 text-xs">
          <p class="font-bold uppercase tracking-wider mb-2 border-b border-black pb-1">Candidate Identification Slip (To be filled in blue or black ink):</p>
          <div class="grid grid-cols-2 gap-y-3 gap-x-4">
            <div>Student Full Name: _____________________________________________</div>
            <div>Student ID Number: ________________________</div>
            <div>Section / Class: _________________________________________________</div>
            <div>Desk / Seat Number: _______________________</div>
          </div>
        </div>

        <div class="border border-slate-400 p-2.5 text-[11px] leading-relaxed">
          <strong>GENERAL EXAMINATION INSTRUCTIONS:</strong>
          <ol class="list-decimal list-inside mt-1 space-y-0.5">
            <li>Ensure your Student ID and Full Name are written legibly on the identification slip above.</li>
            <li>Carefully review instructions for each specific question section before writing your responses.</li>
            <li>All unauthorized books, notes, smart devices, and phones are strictly prohibited.</li>
            <li>Raise your hand quietly if you notice any printing defect or illegible question.</li>
          </ol>
        </div>
      </div>

      <!-- Official Academic Paper Header Banner -->
      <div class="bg-gradient-to-r from-slate-900 to-indigo-950 rounded-2xl p-6 sm:p-7 text-white shadow-md relative overflow-hidden print:hidden">
        <div class="relative z-10 space-y-4">
          <!-- Institutional Header -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/10 pb-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" /></svg>
              </div>
              <div>
                <h2 class="text-sm font-black tracking-wider text-amber-200 uppercase">Wollo University • Academic Examination Center</h2>
                <p class="text-xs text-slate-300">Office of the Examination Controller & Quality Assurance</p>
              </div>
            </div>

            <div class="text-left sm:text-right">
              <span class="inline-block px-2.5 py-1 rounded bg-white/10 border border-white/20 font-mono text-[11px] font-bold text-indigo-200">
                {{ selectedExam?.code || selectedExam?.examCode }}
              </span>
            </div>
          </div>

          <!-- Exam Course Meta Row -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
            <div>
              <span class="text-[10px] text-slate-400 uppercase tracking-wider block font-semibold">Course</span>
              <span class="font-bold text-white text-xs mt-0.5 block truncate">{{ selectedExam?.course || selectedExam?.courseName }} ({{ selectedExam?.courseCode || selectedExam?.course_code }})</span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 uppercase tracking-wider block font-semibold">Instructor / Examiner</span>
              <span class="font-bold text-white text-xs mt-0.5 block truncate">{{ selectedExam?.instructor }}</span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 uppercase tracking-wider block font-semibold">Target Cohort</span>
              <span class="font-bold text-white text-xs mt-0.5 block">{{ selectedExam?.year }} • {{ selectedExam?.section || 'Section A' }}</span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 uppercase tracking-wider block font-semibold">Allocated Time & Weight</span>
              <span class="font-bold text-white text-xs mt-0.5 block">{{ selectedExam?.duration }} • {{ selectedExam?.totalMarks || selectedExam?.total_marks }} Marks</span>
            </div>
          </div>

          <!-- General Candidate Instructions -->
          <div class="bg-white/5 border border-white/10 rounded-xl p-3 text-[11px] text-slate-300 leading-relaxed">
            <strong class="text-amber-200 uppercase tracking-wider font-bold block mb-1">Standard Examination Protocol:</strong>
            Candidates must review instructions for each specific section before attempting questions. Ensure all required responses are clearly indicated. Academic dishonesty and parameter tampering are subject to disciplinary measures.
          </div>
        </div>
      </div>

      <!-- Real Questions Renders: Empty State -->
      <div v-if="!selectedExam?.questions || selectedExam?.questions.length === 0" class="bg-white p-12 rounded-2xl border border-slate-200 text-center space-y-3 shadow-sm">
        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
        </div>
        <p class="text-sm font-bold text-slate-700">No Questions Created Yet</p>
        <p class="text-xs text-slate-400 max-w-md mx-auto">
          This examination has not yet been populated with questions in the database by the assigned course instructor.
        </p>
      </div>

      <!-- Filtered Results Empty State -->
      <div v-else-if="filteredQuestionsList.length === 0" class="bg-white p-12 rounded-2xl border border-slate-200 text-center space-y-3 shadow-sm">
        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center mx-auto">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        </div>
        <p class="text-sm font-bold text-slate-800">No Questions Match Filter Criteria</p>
        <p class="text-xs text-slate-400 max-w-md mx-auto">
          No questions matched the active search query "{{ searchQuestionQuery }}" or type filter. Clear filters to see all examination questions.
        </p>
        <button
          @click="questionTypeFilter = 'all'; searchQuestionQuery = ''"
          class="px-4 py-2 bg-indigo-50 text-[#4338ca] hover:bg-indigo-100 font-bold text-xs rounded-xl transition-colors"
        >
          Reset Filters
        </button>
      </div>

      <!-- Real Questions Grouped by Examination Part/Section -->
      <div v-else class="space-y-8">
        <div
          v-for="section in organizedExamSections"
          :key="section.typeKey"
          class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-7 space-y-6"
        >
          <!-- Section Banner (Rendered ONCE per section, NEVER repeated per question!) -->
          <div class="bg-gradient-to-r from-slate-50 to-indigo-50/30 border border-slate-200 rounded-xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded-md bg-[#4338ca] text-white text-[11px] font-black uppercase tracking-wider">
                  {{ section.partLabel }}
                </span>
                <h3 class="text-base font-bold text-slate-900">{{ section.title }}</h3>
              </div>
              <p class="text-xs text-slate-600 font-medium mt-1.5 leading-relaxed">
                <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block sm:inline sm:mr-1">Section Direction:</span>
                {{ section.instruction }}
              </p>
            </div>

            <div class="flex items-center gap-2 shrink-0 self-start sm:self-center">
              <span class="text-xs font-bold px-3 py-1 rounded-full bg-white border border-slate-200 text-slate-700 shadow-2xs">
                {{ section.totalQuestions }} {{ section.totalQuestions === 1 ? 'Item' : 'Items' }}
              </span>
              <span class="text-xs font-bold px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 shadow-2xs">
                {{ section.totalMarks }} Marks
              </span>
            </div>
          </div>

          <!-- Questions List for this Section -->
          <div class="space-y-6">
            <div
              v-for="q in section.questions"
              :key="q.id"
              class="rounded-xl border border-slate-200/90 bg-white p-5 sm:p-6 hover:border-indigo-200 transition-all shadow-xs space-y-4 relative"
            >
              <!-- Question Header Row -->
              <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3 flex-1 min-w-0">
                  <!-- Global Continuous Question Number (1 to N) -->
                  <span class="w-8 h-8 rounded-xl bg-indigo-50 border border-indigo-100 text-[#4338ca] font-black text-xs sm:text-sm flex items-center justify-center shrink-0 shadow-2xs">
                    {{ q.globalIndex }}
                  </span>
                  
                  <!-- Question Prompt Text -->
                  <div class="flex-1 min-w-0">
                    <!-- If Fill in the Blank, render formatted blanks -->
                    <div
                      v-if="q.normalizedType === 'fill_in_the_blank'"
                      class="text-sm sm:text-[15px] font-medium text-slate-800 leading-relaxed prose prose-sm max-w-none"
                      v-html="renderFillInBlankHtml(q.text)"
                    ></div>

                    <!-- Regular Question Text -->
                    <div
                      v-else
                      class="text-sm sm:text-[15px] font-medium text-slate-800 leading-relaxed prose prose-sm max-w-none"
                      v-html="q.text"
                    ></div>

                    <!-- Question Specific Note / Direction (only if distinct) -->
                    <p
                      v-if="q.instruction && q.instruction !== section.instruction"
                      class="text-xs text-slate-500 italic mt-1.5 flex items-center gap-1.5"
                    >
                      <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      <span>Guidance Note: {{ q.instruction }}</span>
                    </p>
                  </div>
                </div>

                <!-- Marks & Type Badges -->
                <div class="flex items-center gap-2 shrink-0">
                  <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-600 uppercase tracking-wider border border-slate-200">
                    {{ q.type }}
                  </span>
                  <span class="text-[11px] font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-md shadow-2xs">
                    {{ q.marks }} Marks
                  </span>
                </div>
              </div>

              <!-- ==========================================================
                   1. DEDICATED RENDERER: TRUE / FALSE (NO ANSWER DISPLAY)
              ========================================================== -->
              <div v-if="q.normalizedType === 'true_false'" class="pt-2">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-xl">
                  <div
                    v-for="opt in parseTrueFalse(q)"
                    :key="opt.label"
                    class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 text-slate-700 text-xs sm:text-sm font-semibold flex items-center gap-3 transition-all hover:bg-slate-100/70"
                  >
                    <span class="w-6 h-6 rounded-lg text-xs font-black flex items-center justify-center shrink-0 border border-slate-300 bg-white text-slate-600">
                      {{ opt.label }}
                    </span>
                    <span>{{ opt.text }}</span>
                  </div>
                </div>
              </div>

              <!-- ==========================================================
                   2. DEDICATED RENDERER: MULTIPLE CHOICE (NO ANSWER DISPLAY)
              ========================================================== -->
              <div v-else-if="q.normalizedType === 'multiple_choice'" class="pt-2">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  <div
                    v-for="opt in parseMultipleChoiceOptions(q)"
                    :key="opt.label"
                    class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/40 text-slate-700 text-xs sm:text-sm flex items-center gap-3 transition-all hover:bg-slate-100/70"
                  >
                    <span class="w-6 h-6 rounded-lg text-xs font-black flex items-center justify-center shrink-0 border border-slate-300 bg-white text-slate-600">
                      {{ opt.label }}
                    </span>
                    <span class="leading-relaxed" v-html="opt.text"></span>
                  </div>
                </div>
              </div>

              <!-- ==========================================================
                   3. DEDICATED RENDERER: MATCHING (TWO COLUMNS, NO KEY DISPLAY)
              ========================================================== -->
              <div v-else-if="q.normalizedType === 'matching'" class="pt-2 space-y-4">
                <!-- Two Column Matching Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4 rounded-xl bg-slate-50/50 border border-slate-200/80">
                  
                  <!-- Column A: Premises / Terms -->
                  <div class="space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                      <h4 class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-[#4338ca]"></span>
                        <span>{{ parseMatchingData(q).columnATitle }}</span>
                      </h4>
                      <span class="text-[10px] font-bold text-slate-400">Premises</span>
                    </div>

                    <div class="space-y-2">
                      <div
                        v-for="item in parseMatchingData(q).columnA"
                        :key="item.id"
                        class="p-2.5 rounded-lg bg-white border border-slate-200 flex items-start gap-2.5 shadow-2xs"
                      >
                        <span class="w-5 h-5 rounded-md bg-slate-800 text-white font-black text-[11px] flex items-center justify-center shrink-0 mt-0.5">
                          {{ item.id }}
                        </span>
                        <span class="text-xs font-bold text-slate-800 leading-relaxed pt-0.5">{{ item.text }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- Column B: Responses / Definitions -->
                  <div class="space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                      <h4 class="text-xs font-black text-indigo-700 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        <span>{{ parseMatchingData(q).columnBTitle }}</span>
                      </h4>
                      <span class="text-[10px] font-bold text-slate-400">Definitions</span>
                    </div>

                    <div class="space-y-2">
                      <div
                        v-for="item in parseMatchingData(q).columnB"
                        :key="item.label"
                        class="p-2.5 rounded-lg bg-white border border-indigo-100 flex items-start gap-2.5 shadow-2xs"
                      >
                        <span class="w-5 h-5 rounded-md bg-indigo-50 border border-indigo-200 text-[#4338ca] font-black text-[11px] flex items-center justify-center shrink-0 mt-0.5">
                          {{ item.label }}
                        </span>
                        <span class="text-xs font-medium text-slate-700 leading-relaxed pt-0.5">{{ item.text }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ==========================================================
                   4. DEDICATED RENDERER: FILL IN THE BLANK (NO ANSWER DISPLAY)
              ========================================================== -->
              <div v-else-if="q.normalizedType === 'fill_in_the_blank'" class="pt-2">
                <div class="p-3.5 rounded-xl bg-slate-50 border border-dashed border-slate-200 text-slate-500 text-xs flex items-center justify-between">
                  <span class="font-mono text-slate-500">[ Complete the missing term in the blank space provided above ]</span>
                  <span class="font-bold text-slate-500">Weight: {{ q.marks }} Marks</span>
                </div>
              </div>

              <!-- ==========================================================
                   5. DEDICATED RENDERER: SHORT ANSWER (NO ANSWER DISPLAY)
              ========================================================== -->
              <div v-else-if="q.normalizedType === 'short_answer'" class="pt-2">
                <div class="p-4 rounded-xl bg-slate-50 border border-dashed border-slate-200 space-y-2 text-xs text-slate-400">
                  <div class="flex items-center justify-between text-[11px] font-bold text-slate-500">
                    <span>Candidate Written Response Space</span>
                    <span>Max Marks: {{ q.marks }}</span>
                  </div>
                  <div class="space-y-2 py-2">
                    <div class="border-b border-slate-200 h-4"></div>
                    <div class="border-b border-slate-200 h-4"></div>
                    <div class="border-b border-slate-200 h-4"></div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

      <!-- Print-Only Invigilator Certification Slip -->
      <div class="hidden print:block border-t-2 border-black pt-5 mt-10 text-xs text-black bg-white break-inside-avoid">
        <div class="flex justify-between items-end">
          <div class="space-y-2">
            <p>Senior Invigilator / Proctor Name: _________________________________________</p>
            <p>Signature: ________________________________  Date & Time: __________________</p>
          </div>
          <div class="text-right border border-black p-3 rounded text-[10px]">
            <p class="font-bold uppercase tracking-wider">Official Examination Seal</p>
            <p class="text-slate-500 mt-1">Wollo University Registrar / Faculty</p>
          </div>
        </div>
      </div>

    </div>

    <!-- ────────────────────────────────────────────────────────────────────────
         MODALS & DIALOGS
    ──────────────────────────────────────────────────────────────────────── -->

    <!-- 1. Create / Edit Exam Modal -->
    <Teleport to="body">
      <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden my-8 animate-in fade-in zoom-in-95 duration-150">
          <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 class="text-base font-bold text-slate-800">{{ isEditing ? 'Edit Examination' : 'Schedule New Examination' }}</h3>
              <p class="text-xs text-slate-400 mt-0.5">Configure institutional exam parameters and security policies</p>
            </div>
            <button @click="showFormModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">×</button>
          </div>

          <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto text-xs">
            <!-- Title -->
            <div>
              <label class="font-bold text-slate-700 block mb-1">Examination Title *</label>
              <input
                v-model="examForm.title"
                type="text"
                placeholder="e.g. Advanced Operating Systems - Midterm Exam"
                class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:border-[#4338ca]"
              />
              <p v-if="examFormErrors.title" class="text-[11px] text-rose-500 mt-1">{{ examFormErrors.title }}</p>
            </div>

            <!-- Course Selection -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="font-bold text-slate-700 block mb-1">Course *</label>
                <select
                  :value="examForm.course_code"
                  @change="onCourseSelect"
                  class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 bg-white focus:outline-none focus:border-[#4338ca]"
                >
                  <option v-for="c in backendCourses" :key="c.id" :value="c.code">
                    {{ c.title }} ({{ c.code }})
                  </option>
                </select>
              </div>

              <div>
                <label class="font-bold text-slate-700 block mb-1">Assigned Instructor</label>
                <select
                  v-model="examForm.instructor_id"
                  class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 bg-white focus:outline-none focus:border-[#4338ca]"
                >
                  <option value="">Administrator Managed</option>
                  <option v-for="inst in backendInstructors" :key="inst.id" :value="inst.id">
                    {{ inst.name }} ({{ inst.email }})
                  </option>
                </select>
              </div>
            </div>

            <!-- Section & Exam Type -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="font-bold text-slate-700 block mb-1">Section</label>
                <select
                  v-model="examForm.section"
                  class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 bg-white focus:outline-none focus:border-[#4338ca]"
                >
                  <option value="Both Sections">Both Sections</option>
                  <option value="Section A">Section A</option>
                  <option value="Section B">Section B</option>
                </select>
              </div>

              <div>
                <label class="font-bold text-slate-700 block mb-1">Exam Type</label>
                <select
                  v-model="examForm.exam_type"
                  class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 bg-white focus:outline-none focus:border-[#4338ca]"
                >
                  <option value="Mid Exam">Mid Exam</option>
                  <option value="Final Exam">Final Exam</option>
                  <option value="Quiz">Quiz</option>
                  <option value="Assignment">Assignment</option>
                  <option value="Practical Exam">Practical Exam</option>
                </select>
              </div>
            </div>

            <!-- Duration, Total Marks, Status -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="font-bold text-slate-700 block mb-1">Duration (Minutes) *</label>
                <input
                  v-model.number="examForm.duration_minutes"
                  type="number"
                  min="1"
                  class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:border-[#4338ca]"
                />
              </div>

              <div>
                <label class="font-bold text-slate-700 block mb-1">Total Marks *</label>
                <input
                  v-model.number="examForm.total_marks"
                  type="number"
                  min="1"
                  class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:border-[#4338ca]"
                />
              </div>

              <div>
                <label class="font-bold text-slate-700 block mb-1">Status</label>
                <select
                  v-model="examForm.status"
                  class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 bg-white focus:outline-none focus:border-[#4338ca]"
                >
                  <option value="published">Published</option>
                  <option value="scheduled">Scheduled</option>
                  <option value="draft">Draft</option>
                  <option value="completed">Completed</option>
                </select>
              </div>
            </div>

            <!-- Scheduled Date & Time -->
            <div>
              <label class="font-bold text-slate-700 block mb-1">Scheduled Date & Time</label>
              <input
                v-model="examForm.scheduled_at"
                type="datetime-local"
                class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:border-[#4338ca]"
              />
            </div>

            <!-- Security & Anti-Cheat Controls -->
            <div class="pt-2 border-t border-slate-100">
              <label class="font-bold text-slate-700 block mb-2">Proctoring & Anti-Cheat Policies</label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <label class="flex items-center gap-2 text-slate-700 cursor-pointer">
                  <input type="checkbox" v-model="examForm.fullscreen_mode" class="rounded text-indigo-600" />
                  <span>Enforce Fullscreen Mode</span>
                </label>
                <label class="flex items-center gap-2 text-slate-700 cursor-pointer">
                  <input type="checkbox" v-model="examForm.tab_monitoring" class="rounded text-indigo-600" />
                  <span>Browser Tab Monitoring</span>
                </label>
                <label class="flex items-center gap-2 text-slate-700 cursor-pointer">
                  <input type="checkbox" v-model="examForm.disable_right_click" class="rounded text-indigo-600" />
                  <span>Disable Right-Click</span>
                </label>
                <label class="flex items-center gap-2 text-slate-700 cursor-pointer">
                  <input type="checkbox" v-model="examForm.disable_copy_paste" class="rounded text-indigo-600" />
                  <span>Disable Copy / Paste</span>
                </label>
                <label class="flex items-center gap-2 text-slate-700 cursor-pointer">
                  <input type="checkbox" v-model="examForm.shuffle_questions" class="rounded text-indigo-600" />
                  <span>Shuffle Question Sequence</span>
                </label>
                <label class="flex items-center gap-2 text-slate-700 cursor-pointer">
                  <input type="checkbox" v-model="examForm.shuffle_options" class="rounded text-indigo-600" />
                  <span>Shuffle Option Answers</span>
                </label>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-6 border-t border-slate-100 bg-slate-50/50">
            <button
              @click="showFormModal = false"
              class="px-4 py-2.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="saveExamForm"
              :disabled="isSaving"
              class="px-5 py-2.5 text-xs font-bold text-white bg-[#4338ca] hover:bg-indigo-800 rounded-xl transition-colors shadow-sm disabled:opacity-50"
            >
              {{ isSaving ? 'Saving...' : (isEditing ? 'Update Examination' : 'Save & Schedule Exam') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 2. Cancel Exam Confirmation & Reason Modal -->
    <Teleport to="body">
      <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150">
          <div class="p-6 text-center space-y-3">
            <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto text-amber-600">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
            </div>
            <h3 class="text-base font-bold text-slate-800">Cancel Examination Session</h3>
            <p class="text-xs text-slate-500">
              Cancelling <span class="font-bold text-slate-800">{{ selectedExam?.title }}</span> will immediately revoke active student attempts. Please provide an administrative reason.
            </p>

            <div class="text-left mt-3">
              <label class="block text-xs font-bold text-slate-700 mb-1">Administrative Cancellation Reason *</label>
              <textarea
                v-model="cancellationReason"
                rows="3"
                placeholder="e.g. Power disruption across Block 4 halls, rescheduled to tomorrow..."
                class="w-full text-xs border border-slate-200 rounded-xl p-3 focus:outline-none focus:border-amber-500"
              ></textarea>
            </div>
          </div>

          <div class="flex items-center gap-3 px-6 pb-6">
            <button
              @click="showCancelModal = false"
              class="flex-1 py-2.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors"
            >
              Dismiss
            </button>
            <button
              @click="executeCancelExam"
              :disabled="isCancelling || !cancellationReason.trim()"
              class="flex-1 py-2.5 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl transition-colors shadow-sm disabled:opacity-50"
            >
              {{ isCancelling ? 'Cancelling...' : 'Confirm Cancellation' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 3. Delete Exam Confirmation Modal -->
    <Teleport to="body">
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden animate-in fade-in zoom-in-95 duration-150">
          <div class="p-6 text-center">
            <div class="w-14 h-14 bg-rose-50 rounded-2xl flex items-center justify-center mx-auto mb-4 text-rose-500">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </div>
            <h3 class="text-base font-bold text-slate-800 mb-2">Delete Examination?</h3>
            <p class="text-xs text-slate-500">
              Permanently delete <span class="font-bold text-slate-700">{{ selectedExam?.title }}</span>? This will remove all associated question schedules.
            </p>
          </div>
          <div class="flex items-center gap-3 px-6 pb-6">
            <button @click="showDeleteModal = false" class="flex-1 py-2.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="deleteExam" class="flex-1 py-2.5 text-xs font-bold text-white bg-rose-500 hover:bg-rose-600 rounded-xl transition-colors shadow-sm">Delete</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 4. Global Toast Notification -->
    <Teleport to="body">
      <div
        v-if="toast.show"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 px-4 py-3 rounded-xl shadow-2xl text-xs font-bold transition-all border animate-in slide-in-from-bottom duration-200"
        :class="{
          'bg-emerald-600 text-white border-emerald-700': toast.type === 'success',
          'bg-rose-600 text-white border-rose-700': toast.type === 'error',
          'bg-slate-800 text-white border-slate-900': toast.type === 'info',
        }"
      >
        <span>{{ toast.message }}</span>
      </div>
    </Teleport>

  </div>
</template>

<style>
@media print {
  /* Hide Admin Header, Admin Sidebar, navigation, buttons, inputs, modals, and toasts */
  header,
  aside,
  nav,
  button,
  input,
  select,
  .print\:hidden {
    display: none !important;
  }

  /* Reset layout for full-width crisp paper printing */
  body, html {
    background-color: #ffffff !important;
    color: #000000 !important;
    font-size: 11pt !important;
    line-height: 1.4 !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  main {
    margin: 0 !important;
    padding: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
  }

  /* Remove screen shadows and adapt borders for crisp duplication press output */
  .shadow-sm,
  .shadow-md,
  .shadow-lg,
  .shadow-xl,
  .shadow-2xl,
  .shadow-xs,
  .shadow-2xs {
    box-shadow: none !important;
  }

  .border,
  .border-slate-200,
  .border-slate-300 {
    border-color: #555555 !important;
  }

  /* Prevent questions and section headers from splitting across pages */
  .break-inside-avoid {
    break-inside: avoid !important;
    page-break-inside: avoid !important;
  }
}
</style>
