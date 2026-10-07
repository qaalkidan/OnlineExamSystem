import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../core/api/apiClient'

// ── Types ──────────────────────────────────────────────────
export interface EventCategory {
  id: number
  name: string
  description: string | null
  color: string
  type: 'system' | 'custom'
  status: 'active' | 'inactive'
  created_by: number | null
  created_at: string
  updated_at: string
}

export interface CategoryStats {
  total: number
  active: number
  system: number
  custom: number
}

export interface AcademicEvent {
  id: number
  title: string
  description: string | null
  category_id: number | null
  category_name: string | null
  category_color: string | null
  academic_year: string
  semester: string
  start_date: string   // 'YYYY-MM-DD'
  end_date: string     // 'YYYY-MM-DD'
  all_day: boolean
  start_time: string | null
  end_time: string | null
  status: 'upcoming' | 'ongoing' | 'completed' | 'cancelled'
  color: string
  is_recurring: boolean
  created_at?: string
}

export interface EventStats {
  total: number
  upcoming: number
  ongoing: number
  completed?: number
  cancelled?: number
  holidays: number
  exams: number
  deadlines?: number
  academic_weeks?: number | null
  days_remaining?: number | null
  period_start?: string | null
  period_end?: string | null
  current_year?: string
  current_semester?: string
  available_years?: string[]
  available_semesters?: string[]
}

// ── Store ───────────────────────────────────────────────────
export const useCalendarStore = defineStore('calendar', () => {
  // --- Categories ---
  const categories = ref<EventCategory[]>([])
  const stats = ref<CategoryStats>({ total: 0, active: 0, system: 0, custom: 0 })

  // --- Events ---
  const events = ref<AcademicEvent[]>([])
  const eventStats = ref<EventStats>({
    total: 0,
    upcoming: 0,
    ongoing: 0,
    completed: 0,
    cancelled: 0,
    holidays: 0,
    exams: 0,
    deadlines: 0,
    academic_weeks: null,
    days_remaining: null,
    period_start: null,
    period_end: null,
    current_year: '',
    current_semester: '',
    available_years: [],
    available_semesters: []
  })

  // --- UI state ---
  const isLoading = ref(false)
  const isLoadingEvents = ref(false)
  const isSaving = ref(false)
  const error = ref<string | null>(null)

  // ── Computed ────────────────────────────────────────────
  const upcomingEvents = computed(() =>
    events.value
      .filter(e => e.status === 'upcoming' || e.status === 'ongoing')
      .sort((a, b) => a.start_date.localeCompare(b.start_date))
      .slice(0, 5)
  )

  /** Returns events whose date range covers a specific day (YYYY-MM-DD) */
  function eventsOnDay(dateStr: string): AcademicEvent[] {
    return events.value.filter(e => e.start_date <= dateStr && e.end_date >= dateStr)
  }

  /** Returns all events for a given month (YYYY, MM 1-based) */
  function eventsInMonth(year: number, month: number): AcademicEvent[] {
    const pad = (n: number) => String(n).padStart(2, '0')
    const monthStr = `${year}-${pad(month)}`
    return events.value.filter(
      e => e.start_date.startsWith(monthStr) || e.end_date.startsWith(monthStr) ||
           (e.start_date < `${year}-${pad(month)}-01` && e.end_date >= `${year}-${pad(month)}-01`)
    )
  }

  // ── Category CRUD ────────────────────────────────────────
  async function fetchCategories() {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/admin/calendar/categories')
      categories.value = response.data.data
      stats.value = response.data.stats
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to load categories.'
    } finally {
      isLoading.value = false
    }
  }

  async function addCategory(payload: {
    name: string; description?: string; color: string
    type: 'system' | 'custom'; status?: 'active' | 'inactive'
  }) {
    isSaving.value = true; error.value = null
    try {
      await api.post('/admin/calendar/categories', payload)
      await fetchCategories()
      return { success: true }
    } catch (err: any) {
      const msg = err?.response?.data?.errors
        ? Object.values(err.response.data.errors).flat().join(' ')
        : err?.response?.data?.message ?? 'Failed to save category.'
      error.value = msg
      return { success: false, message: msg }
    } finally { isSaving.value = false }
  }

  async function updateCategory(id: number, payload: {
    name: string; description?: string; color: string
    type: 'system' | 'custom'; status?: 'active' | 'inactive'
  }) {
    isSaving.value = true; error.value = null
    try {
      await api.put(`/admin/calendar/categories/${id}`, payload)
      await fetchCategories()
      return { success: true }
    } catch (err: any) {
      const msg = err?.response?.data?.errors
        ? Object.values(err.response.data.errors).flat().join(' ')
        : err?.response?.data?.message ?? 'Failed to update category.'
      error.value = msg
      return { success: false, message: msg }
    } finally { isSaving.value = false }
  }

  async function deleteCategory(id: number) {
    error.value = null
    try {
      await api.delete(`/admin/calendar/categories/${id}`)
      await fetchCategories()
      return { success: true }
    } catch (err: any) {
      const msg = err?.response?.data?.message ?? 'Failed to delete category.'
      error.value = msg
      return { success: false, message: msg }
    }
  }

  // ── Event CRUD ───────────────────────────────────────────
  async function fetchEvents(filters?: {
    academic_year?: string
    semester?: string
    category_id?: string | number
    status?: string
    search?: string
  }) {
    isLoadingEvents.value = true
    error.value = null
    try {
      const response = await api.get('/admin/calendar/events', { params: filters })
      events.value = response.data.data
      eventStats.value = response.data.stats
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Failed to load events.'
      console.error(err)
    } finally {
      isLoadingEvents.value = false
    }
  }

  async function addEvent(payload: Partial<AcademicEvent>) {
    isSaving.value = true; error.value = null
    try {
      const response = await api.post('/admin/calendar/events', payload)
      await fetchEvents()
      return { success: true, data: response.data.data }
    } catch (err: any) {
      const msg = err?.response?.data?.errors
        ? Object.values(err.response.data.errors).flat().join(' ')
        : err?.response?.data?.message ?? 'Failed to save event.'
      error.value = msg
      return { success: false, message: msg }
    } finally { isSaving.value = false }
  }

  async function updateEvent(id: number, payload: Partial<AcademicEvent>) {
    isSaving.value = true; error.value = null
    try {
      await api.put(`/admin/calendar/events/${id}`, payload)
      await fetchEvents()
      return { success: true }
    } catch (err: any) {
      const msg = err?.response?.data?.errors
        ? Object.values(err.response.data.errors).flat().join(' ')
        : err?.response?.data?.message ?? 'Failed to update event.'
      error.value = msg
      return { success: false, message: msg }
    } finally { isSaving.value = false }
  }

  async function deleteEvent(id: number) {
    error.value = null
    try {
      await api.delete(`/admin/calendar/events/${id}`)
      events.value = events.value.filter(e => e.id !== id)
      eventStats.value.total = events.value.length
      return { success: true }
    } catch (err: any) {
      const msg = err?.response?.data?.message ?? 'Failed to delete event.'
      error.value = msg
      return { success: false, message: msg }
    }
  }

  return {
    // state
    categories, stats,
    events, eventStats,
    isLoading, isLoadingEvents, isSaving, error,
    // computed
    upcomingEvents,
    // helpers
    eventsOnDay, eventsInMonth,
    // actions
    fetchCategories, addCategory, updateCategory, deleteCategory,
    fetchEvents, addEvent, updateEvent, deleteEvent,
  }
})
