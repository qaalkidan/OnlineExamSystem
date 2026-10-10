import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '../../../core/api/apiClient'

export interface DashboardStats {
  course_code?: string
  course_name?: string
  department_name?: string
  year_level?: string
  section?: string
  totalExams: number
  publishedExams?: number
  upcomingExams: number
  totalStudents: number
  averageScore: number
  highestScore?: number | null
  lowestScore?: number | null
  attemptsCount?: number
  performanceByExam?: Array<{
    exam_id: number
    title: string
    attempts_count: number
    average_score: number | null
  }>
}

export interface Exam {
  id: number
  title: string
  course_code: string
  course_name: string
  section?: string | null
  scheduled_at: string | null
  duration_minutes: number
  total_marks: number
  status: 'draft' | 'published' | 'scheduled' | 'completed' | 'active' | 'archived'
  students_count?: number
  questions_count?: number
  questions?: any[]
  settings?: any
  created_at: string
}

// ─────────────────────────────────────────────
// Fallback mock data (used when backend is down)
// ─────────────────────────────────────────────
const MOCK_STATS: DashboardStats = {
  course_code: 'SWE-301',
  course_name: 'Software Engineering',
  totalExams: 5,
  upcomingExams: 2,
  totalStudents: 120,
  averageScore: 84.5,
}

const MOCK_RECENT_EXAMS: Exam[] = [
  {
    id: 1,
    title: 'Midterm — Software Engineering',
    course_code: 'SWE-301',
    course_name: 'Software Engineering',
    status: 'completed',
    duration_minutes: 120,
    total_marks: 100,
    students_count: 45,
    scheduled_at: new Date(Date.now() - 10 * 86400000).toISOString(),
    created_at: new Date(Date.now() - 15 * 86400000).toISOString(),
  },
  {
    id: 2,
    title: 'Final Exam — Software Engineering',
    course_code: 'SWE-301',
    course_name: 'Software Engineering',
    status: 'scheduled',
    duration_minutes: 180,
    total_marks: 100,
    students_count: 60,
    scheduled_at: new Date(Date.now() + 7 * 86400000).toISOString(),
    created_at: new Date(Date.now() - 2 * 86400000).toISOString(),
  },
  {
    id: 3,
    title: 'Quiz 1 — Software Engineering',
    course_code: 'SWE-301',
    course_name: 'Software Engineering',
    status: 'scheduled',
    duration_minutes: 60,
    total_marks: 50,
    students_count: 55,
    scheduled_at: new Date(Date.now() + 3 * 86400000).toISOString(),
    created_at: new Date(Date.now() - 86400000).toISOString(),
  },
  {
    id: 4,
    title: 'Assignment Test — Software Engineering',
    course_code: 'SWE-301',
    course_name: 'Software Engineering',
    status: 'draft',
    duration_minutes: 90,
    total_marks: 75,
    students_count: 0,
    scheduled_at: null,
    created_at: new Date().toISOString(),
  },
  {
    id: 5,
    title: 'Semester End — Software Engineering',
    course_code: 'SWE-301',
    course_name: 'Software Engineering',
    status: 'published',
    duration_minutes: 180,
    total_marks: 100,
    students_count: 72,
    scheduled_at: new Date(Date.now() + 14 * 86400000).toISOString(),
    created_at: new Date().toISOString(),
  },
]

const MOCK_UPCOMING_EXAMS: Exam[] = MOCK_RECENT_EXAMS.filter(
  (e) => e.status === 'scheduled' || e.status === 'published'
)

// ─────────────────────────────────────────────
// Pinia Store
// ─────────────────────────────────────────────
export const useInstructorStore = defineStore('instructor', () => {
  const stats = ref<DashboardStats>({ ...MOCK_STATS })
  const recentExams = ref<Exam[]>([...MOCK_RECENT_EXAMS])
  const upcomingExams = ref<Exam[]>([...MOCK_UPCOMING_EXAMS])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const usingMockData = ref(false)

  const fetchDashboardData = async () => {
    isLoading.value = true
    error.value = null
    usingMockData.value = false

    try {
      const [statsRes, recentRes, upcomingRes] = await Promise.all([
        apiClient.get('/instructor/dashboard-stats'),
        apiClient.get('/instructor/recent-exams'),
        apiClient.get('/instructor/upcoming-exams'),
      ])

      stats.value = statsRes.data.data
      recentExams.value = recentRes.data.data
      upcomingExams.value = upcomingRes.data.data
    } catch (err: any) {
      // Network error → fall back to mock data so the UI still renders
      if (err.code === 'ERR_NETWORK' || !err.response) {
        console.warn('[InstructorStore] Backend unreachable. Using mock data for development.')
        stats.value = { ...MOCK_STATS }
        recentExams.value = [...MOCK_RECENT_EXAMS]
        upcomingExams.value = [...MOCK_UPCOMING_EXAMS]
        usingMockData.value = true
      } else {
        // Real server error (401, 500, etc.)
        console.error('[InstructorStore] API error:', err)
        error.value = err.response?.data?.message || 'Failed to load dashboard data.'
      }
    } finally {
      isLoading.value = false
    }
  }

  return {
    stats,
    recentExams,
    upcomingExams,
    isLoading,
    error,
    usingMockData,
    fetchDashboardData,
  }
})
