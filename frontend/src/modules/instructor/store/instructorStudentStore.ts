import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '../../../core/api/apiClient'

export interface Student {
  id: number
  name: string
  email: string
  id_number: string
  course_code: string
  course_name: string
  gender: string
  status: string
  exams_taken: number
  average_score: number
  created_at: string
}

export interface StudentStats {
  total_students: number
  active_students: number
  average_score: number
  top_performers: number
  average_attendance?: number
}

export interface CourseOverview {
  course_name: string
  course_code: string
  section: string
  semester: string
  academic_year: string
  instructor: string
}

export interface StudentProgress {
  completed_exams_percent: number
  pending_exams_percent: number
  average_attendance: number
  average_exam_score: number
  students_at_risk_percent: number
  completed_exams_count: number
  pending_exams_count: number
  students_at_risk_count: number
  total_students: number
}

const DEFAULT_COURSE_OVERVIEW: CourseOverview = {
  course_name: 'Database Systems',
  course_code: 'CS-304',
  section: 'CS-304-A',
  semester: 'Semester I',
  academic_year: '2025/2026',
  instructor: 'Dr. Abebe Kebede'
}

const DEFAULT_STUDENT_PROGRESS: StudentProgress = {
  completed_exams_percent: 58.3,
  pending_exams_percent: 41.7,
  average_attendance: 78.4,
  average_exam_score: 72.6,
  students_at_risk_percent: 12.5,
  completed_exams_count: 7,
  pending_exams_count: 5,
  students_at_risk_count: 1,
  total_students: 8
}

const MOCK_STATS: StudentStats = {
  total_students: 320,
  active_students: 298,
  average_score: 72.4,
  top_performers: 46,
  average_attendance: 78.4
}

const MOCK_STUDENTS: Student[] = [
  { id: 1, name: 'Abebe Tesfaye', email: 'abebe.tesfaye@wollo.edu.et', id_number: 'WU/2021/CS/001', course_code: 'SWE-301', course_name: 'Software Engineering', gender: 'Male', status: 'Active', exams_taken: 12, average_score: 85.6, created_at: '2025-01-15T10:00:00.000Z' },
  { id: 2, name: 'Selamawit Getachew', email: 'selamawit.get@wollo.edu.et', id_number: 'WU/2021/SE/015', course_code: 'SWE-301', course_name: 'Software Engineering', gender: 'Female', status: 'Active', exams_taken: 10, average_score: 78.3, created_at: '2025-01-15T10:00:00.000Z' },
  { id: 3, name: 'Kebede Assefa', email: 'kebede.assefa@wollo.edu.et', id_number: 'WU/2022/CS/027', course_code: 'SWE-301', course_name: 'Software Engineering', gender: 'Male', status: 'Active', exams_taken: 8, average_score: 68.7, created_at: '2025-02-10T10:00:00.000Z' },
  { id: 4, name: 'Hanna Mengesha', email: 'hanna.mengesha@wollo.edu.et', id_number: 'WU/2022/IT/031', course_code: 'SWE-301', course_name: 'Software Engineering', gender: 'Female', status: 'Active', exams_taken: 9, average_score: 82.1, created_at: '2025-02-10T10:00:00.000Z' },
  { id: 5, name: 'Mekonnen Worku', email: 'mekonnen.worku@wollo.edu.et', id_number: 'WU/2021/CS/045', course_code: 'SWE-301', course_name: 'Software Engineering', gender: 'Male', status: 'Active', exams_taken: 6, average_score: 45.2, created_at: '2025-01-15T10:00:00.000Z' },
  { id: 6, name: 'Rahel Solomon', email: 'rahel.solomon@wollo.edu.et', id_number: 'WU/2023/SE/058', course_code: 'SWE-301', course_name: 'Software Engineering', gender: 'Female', status: 'Active', exams_taken: 4, average_score: 75.9, created_at: '2025-03-01T10:00:00.000Z' },
  { id: 7, name: 'Yonas Alemu', email: 'yonas.alemu@wollo.edu.et', id_number: 'WU/2023/CS/064', course_code: 'SWE-301', course_name: 'Software Engineering', gender: 'Male', status: 'Active', exams_taken: 4, average_score: 62.4, created_at: '2025-03-01T10:00:00.000Z' },
  { id: 8, name: 'Lidya Gebremedhin', email: 'lidya.gebremedhin@wollo.edu.et', id_number: 'WU/2023/IT/072', course_code: 'SWE-301', course_name: 'Software Engineering', gender: 'Female', status: 'Active', exams_taken: 5, average_score: 88.6, created_at: '2025-03-01T10:00:00.000Z' },
]

export const useInstructorStudentStore = defineStore('instructorStudent', () => {
  const students = ref<Student[]>([])
  const stats = ref<StudentStats>({ ...MOCK_STATS })
  const courseOverview = ref<CourseOverview>({ ...DEFAULT_COURSE_OVERVIEW })
  const studentProgress = ref<StudentProgress>({ ...DEFAULT_STUDENT_PROGRESS })
  const isLoading = ref(false)
  const isExporting = ref(false)
  const error = ref<string | null>(null)
  const usingMockData = ref(false)

  const fetchStudents = async () => {
    isLoading.value = true
    error.value = null
    usingMockData.value = false

    try {
      const storedContext = localStorage.getItem('instructor_context')
      const context = storedContext ? JSON.parse(storedContext) : {}
      
      const response = await apiClient.get('/instructor/students', {
        params: {
          department: context.department || '',
          course: context.course || '',
          section: context.section || ''
        }
      })

      if (response.data?.data) {
        students.value = response.data.data.students || []
        if (response.data.data.stats) {
          stats.value = response.data.data.stats
        }
        if (response.data.data.course_overview) {
          courseOverview.value = response.data.data.course_overview
        }
        if (response.data.data.student_progress) {
          studentProgress.value = response.data.data.student_progress
        }
      }
    } catch (err: any) {
      if (err.code === 'ERR_NETWORK' || !err.response) {
        console.warn('[InstructorStudentStore] Backend unreachable. Using fallback data.')
        students.value = [...MOCK_STUDENTS]
        stats.value = { ...MOCK_STATS }
        courseOverview.value = { ...DEFAULT_COURSE_OVERVIEW }
        studentProgress.value = { ...DEFAULT_STUDENT_PROGRESS }
        usingMockData.value = true
      } else {
        error.value = err.response?.data?.message || 'Failed to load students.'
      }
    } finally {
      isLoading.value = false
    }
  }

  const exportStudents = async () => {
    isExporting.value = true
    try {
      const storedContext = localStorage.getItem('instructor_context')
      const context = storedContext ? JSON.parse(storedContext) : {}
      
      const response = await apiClient.get('/instructor/students/export', {
        params: {
          department: context.department || '',
          course: context.course || '',
          section: context.section || ''
        }
      })

      if (response.data?.file) {
        const byteChars = atob(response.data.file)
        const byteNums = new Array(byteChars.length)
        for (let i = 0; i < byteChars.length; i++) byteNums[i] = byteChars.charCodeAt(i)
        const blob = new Blob([new Uint8Array(byteNums)], { type: 'text/csv;charset=utf-8;' })
        const url = URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = response.data.filename || `students_${courseOverview.value.course_code}_${new Date().toISOString().slice(0, 10)}.csv`
        document.body.appendChild(link)
        link.click()
        link.remove()
        URL.revokeObjectURL(url)
        return true
      }
      return false
    } catch (err: any) {
      console.error('Failed to export students:', err)
      throw err
    } finally {
      isExporting.value = false
    }
  }

  const importStudents = async (studentsToImport: any[]) => {
    try {
      const response = await apiClient.post('/instructor/students/import', {
        students: studentsToImport
      })
      await fetchStudents()
      return response.data
    } catch (err: any) {
      console.error('Failed to import students:', err)
      throw err
    }
  }

  const sendAnnouncement = async (payload: { title: string; message: string }) => {
    try {
      const response = await apiClient.post('/instructor/students/announcement', payload)
      return response.data
    } catch (err: any) {
      console.error('Failed to send announcement:', err)
      throw err
    }
  }

  const downloadReport = () => {
    const rows = [
      ['Student ID', 'Full Name', 'Email', 'Gender', 'Course Name', 'Course Code', 'Section', 'Semester', 'Academic Year', 'Exams Taken', 'Average Score (%)', 'Status'],
      ...students.value.map(s => [
        `"${(s.id_number || '').replace(/"/g, '""')}"`,
        `"${(s.name || '').replace(/"/g, '""')}"`,
        `"${(s.email || '').replace(/"/g, '""')}"`,
        `"${(s.gender || '').replace(/"/g, '""')}"`,
        `"${(courseOverview.value.course_name || '').replace(/"/g, '""')}"`,
        `"${(courseOverview.value.course_code || '').replace(/"/g, '""')}"`,
        `"${(courseOverview.value.section || '').replace(/"/g, '""')}"`,
        `"${(courseOverview.value.semester || '').replace(/"/g, '""')}"`,
        `"${(courseOverview.value.academic_year || '').replace(/"/g, '""')}"`,
        s.exams_taken,
        `"${s.average_score}%"`,
        `"${(s.status || '').replace(/"/g, '""')}"`
      ])
    ]
    const csvContent = '\uFEFF' + rows.map(r => r.join(',')).join('\n')
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `Student_Performance_Report_${courseOverview.value.course_code || 'Course'}_${new Date().toISOString().slice(0, 10)}.csv`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  }

  return {
    students,
    stats,
    courseOverview,
    studentProgress,
    isLoading,
    isExporting,
    error,
    usingMockData,
    fetchStudents,
    exportStudents,
    importStudents,
    sendAnnouncement,
    downloadReport
  }
})
