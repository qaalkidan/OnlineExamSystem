import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '../../../core/api/apiClient'

export interface QbTypes {
  mcq: number
  sa: number
  essay: number
  tf?: number
}

export interface QuestionBank {
  id: number
  title: string
  description?: string
  course_code: string
  course_name: string
  total_questions: number
  types: QbTypes
  created_at?: string
  updated_at: string
  status: string
}

export interface QbStats {
  total_banks: number
  total_questions: number
  mcq_questions: number
  sa_questions: number
  essay_questions: number
  last_created?: {
    title: string
    created_at: string
  } | null
  last_updated?: {
    title: string
    updated_at: string
  } | null
}

const MOCK_STATS: QbStats = {
  total_banks: 12,
  total_questions: 856,
  mcq_questions: 652,
  sa_questions: 124,
  essay_questions: 80,
}

const MOCK_BANKS: QuestionBank[] = [
  { id: 1, title: 'Database Systems Questions', description: 'All database related questions', course_name: 'Software Engineering', course_code: 'SWE-301', total_questions: 78, types: { mcq: 60, sa: 12, essay: 6 }, created_at: '2025-05-20T10:00:00.000Z', updated_at: '2025-05-20T10:00:00.000Z', status: 'Active' },
  { id: 2, title: 'Web Programming Questions', description: 'HTML, CSS, JavaScript, PHP', course_name: 'Software Engineering', course_code: 'SWE-301', total_questions: 95, types: { mcq: 72, sa: 15, essay: 8 }, created_at: '2025-05-18T10:00:00.000Z', updated_at: '2025-05-18T10:00:00.000Z', status: 'Active' },
]

export const useInstructorQbStore = defineStore('instructorQb', () => {
  const banks = ref<QuestionBank[]>([])
  const stats = ref<QbStats>({ ...MOCK_STATS })
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const usingMockData = ref(false)

  const fetchQuestionBanks = async (search?: string) => {
    isLoading.value = true
    error.value = null
    usingMockData.value = false

    try {
      const params: Record<string, string> = {}
      if (search && search.trim()) {
        params.search = search.trim()
      }
      const response = await apiClient.get('/instructor/question-banks', { params })
      banks.value = response.data.data.banks
      stats.value = response.data.data.stats
    } catch (err: any) {
      if (err.code === 'ERR_NETWORK' || !err.response) {
        console.warn('[InstructorQbStore] Backend unreachable. Using mock data.')
        banks.value = [...MOCK_BANKS]
        stats.value = { ...MOCK_STATS }
        usingMockData.value = true
      } else {
        error.value = err.response?.data?.message || 'Failed to load question banks.'
      }
    } finally {
      isLoading.value = false
    }
  }

  const fetchQuestionBank = async (id: number | string) => {
    try {
      if (!usingMockData.value) {
        const response = await apiClient.get(`/instructor/question-banks/${id}`)
        return response.data.data
      }
      
      // Fallback for mock mode
      const bank = banks.value.find(b => b.id === Number(id))
      return {
        bank,
        questions: [] // Return empty if mocked
      }
    } catch (err: any) {
      console.error("Failed to fetch question bank details", err)
      throw err
    }
  }

  const createQuestionBank = async (payload: { title: string, description: string }) => {
    try {
      if (usingMockData.value) {
        // Mock creation
        const newBank: QuestionBank = {
          id: Date.now(),
          title: payload.title,
          description: payload.description,
          course_code: 'SWE-301',
          course_name: 'Software Engineering',
          total_questions: 0,
          types: { mcq: 0, sa: 0, essay: 0 },
          updated_at: new Date().toISOString(),
          status: 'Active'
        }
        banks.value.unshift(newBank)
        stats.value.total_banks++
        return newBank
      } else {
        const response = await apiClient.post('/instructor/question-banks', payload)
        banks.value.unshift(response.data.data)
        stats.value.total_banks++
        return response.data.data
      }
    } catch (err: any) {
      console.error("Failed to create question bank", err)
      throw err
    }
  }

  const deleteQuestionBank = async (id: number) => {
    try {
      if (usingMockData.value) {
        banks.value = banks.value.filter(b => b.id !== id)
        stats.value.total_banks--
      } else {
        await apiClient.delete(`/instructor/question-banks/${id}`)
        banks.value = banks.value.filter(b => b.id !== id)
        stats.value.total_banks--
      }
    } catch (err: any) {
      console.error("Failed to delete question bank", err)
      throw err
    }
  }

  const fetchInstructions = async (type?: string, bankId?: string | number) => {
    try {
      if (!usingMockData.value) {
        const queryParams = new URLSearchParams()
        if (type) queryParams.append('type', type)
        if (bankId) queryParams.append('bank_id', String(bankId))
        
        const queryString = queryParams.toString()
        const url = queryString ? `/instructor/instructor-instructions?${queryString}` : '/instructor/instructor-instructions'
        
        const response = await apiClient.get(url)
        return response.data.data
      }
      return []
    } catch (err: any) {
      console.error("Failed to fetch instructions", err)
      return []
    }
  }

  const updateQuestionBank = async (id: number, payload: { title: string, description: string }) => {
    try {
      if (!usingMockData.value) {
        await apiClient.put(`/instructor/question-banks/${id}`, payload)
      }
      // Update local state
      const idx = banks.value.findIndex(b => b.id === id)
      if (idx !== -1) {
        banks.value[idx] = { ...banks.value[idx], ...payload, updated_at: new Date().toISOString() }
      }
    } catch (err: any) {
      console.error("Failed to update question bank", err)
      throw err
    }
  }

  const addQuestion = async (bankId: number | string, questionData: any) => {
    try {
      if (!usingMockData.value) {
        const response = await apiClient.post(`/instructor/question-banks/${bankId}/questions`, questionData)
        return response.data
      }
      return null
    } catch (err: any) {
      console.error("Failed to add question", err)
      throw err
    }
  }

  const importQuestions = async (bankId: number | string, formData: FormData) => {
    try {
      if (!usingMockData.value) {
        const response = await apiClient.post(`/instructor/question-banks/${bankId}/import`, formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })
        return response.data
      }
      return { imported: 0 }
    } catch (err: any) {
      console.error("Failed to import questions", err)
      throw err
    }
  }


  const draftQuestions = ref<any[]>([])

  const saveQuestionDraft = async (bankId: number | string, questionData: any) => {
    try {
      const payload = {
        ...questionData,
        status: 'draft'
      }
      if (!usingMockData.value) {
        const response = await apiClient.post(`/instructor/question-banks/${bankId}/questions`, payload)
        const saved = response.data.data
        draftQuestions.value.unshift(saved)
        return saved
      }
      const mockSaved = { ...payload, id: Date.now(), question_bank_id: Number(bankId) }
      draftQuestions.value.unshift(mockSaved)
      return mockSaved
    } catch (err: any) {
      console.error("Failed to save draft question", err)
      if (err.response && err.response.data && err.response.data.errors) {
        throw new Error(Object.values(err.response.data.errors).flat().join(', '))
      }
      throw err
    }
  }

  const fetchDraftQuestions = async (bankId: number | string) => {
    try {
      if (!usingMockData.value) {
        const response = await apiClient.get(`/instructor/question-banks/${bankId}/drafts`)
        draftQuestions.value = response.data.data || []
        return draftQuestions.value
      }
      return draftQuestions.value
    } catch (err: any) {
      console.error("Failed to fetch draft questions", err)
      throw err
    }
  }

  const publishQuestions = async (bankId: number | string) => {
    try {
      if (!usingMockData.value) {
        const response = await apiClient.post(`/instructor/question-banks/${bankId}/publish`)
        draftQuestions.value = []
        return response.data
      }
      draftQuestions.value = []
      return { message: 'All questions published successfully!' }
    } catch (err: any) {
      console.error("Failed to publish questions", err)
      throw err
    }
  }

  const updateQuestion = async (questionId: number | string, questionData: any) => {
    try {
      if (!usingMockData.value) {
        const response = await apiClient.put(`/instructor/questions/${questionId}`, questionData)
        return response.data
      }
      return { data: { ...questionData, id: questionId } }
    } catch (err: any) {
      console.error("Failed to update question", err)
      throw err
    }
  }

  const deleteQuestion = async (questionId: number | string) => {
    try {
      if (!usingMockData.value) {
        await apiClient.delete(`/instructor/questions/${questionId}`)
      }
      draftQuestions.value = draftQuestions.value.filter(q => q.id !== Number(questionId) && q.id !== String(questionId))
    } catch (err: any) {
      console.error("Failed to delete question", err)
      throw err
    }
  }

  return {
    banks,
    stats,
    isLoading,
    error,
    usingMockData,
    draftQuestions,
    fetchQuestionBanks,
    fetchQuestionBank,
    fetchInstructions,
    createQuestionBank,
    updateQuestionBank,
    deleteQuestionBank,
    addQuestion,
    saveQuestionDraft,
    fetchDraftQuestions,
    publishQuestions,
    importQuestions,
    updateQuestion,
    deleteQuestion,
  }
})
