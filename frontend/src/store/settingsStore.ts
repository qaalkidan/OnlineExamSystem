import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../core/api/apiClient'

export interface SystemInfo {
  application_name: string
  app_environment: string
  app_debug: string
  php_version: string
  laravel_version: string
  server_software: string
  server_time: string
  server_timezone: string
  database_driver: string
  database_status: string
  database_latency: string
  database_version: string
  storage_writable: string
  cache_driver: string
  mail_driver: string
}

export const useSettingsStore = defineStore('settings', () => {
  const academicYear = ref('2026')
  const semester = ref('Second Semester')
  const settings = ref<Record<string, string>>({
    universityName: 'Wollo University',
    systemTitle: 'Online Examination System',
    institutionCode: 'WU',
    campusLocation: 'Dessie & Kombolcha, Ethiopia',
    supportEmail: 'admin@wollo.edu.et',
    timezone: 'Africa/Addis_Ababa',
    language: 'English',
    academicYear: '2026',
    semester: 'Second Semester',
    maintenanceMode: 'false'
  })
  const systemInfo = ref<SystemInfo | null>(null)
  const isLoading = ref(false)

  const formattedAcademicTerm = computed(() => {
    return `${academicYear.value} ${semester.value}`
  })

  async function fetchSettings() {
    try {
      const response = await api.get('/settings')
      if (response.data) {
        settings.value = { ...settings.value, ...response.data }
        if (response.data.academicYear) academicYear.value = response.data.academicYear
        if (response.data.semester) semester.value = response.data.semester
      }
    } catch (error) {
      console.error('Failed to fetch settings', error)
    }
  }

  async function updateTerm(year: string, sem: string) {
    isLoading.value = true
    try {
      await api.post('/admin/settings', {
        academicYear: year,
        semester: sem
      })
      academicYear.value = year
      semester.value = sem
      settings.value.academicYear = year
      settings.value.semester = sem
    } catch (error) {
      console.error('Failed to update term settings', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function updateSettings(newSettings: Record<string, string>) {
    isLoading.value = true
    try {
      const response = await api.post('/admin/settings', newSettings)
      if (response.data?.settings) {
        settings.value = { ...settings.value, ...response.data.settings }
      } else {
        settings.value = { ...settings.value, ...newSettings }
      }
      if (settings.value.academicYear) academicYear.value = settings.value.academicYear
      if (settings.value.semester) semester.value = settings.value.semester
      return response.data
    } catch (error) {
      console.error('Failed to update settings', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function fetchSystemInfo(): Promise<SystemInfo | null> {
    try {
      const response = await api.get('/admin/settings/info')
      if (response.data) {
        systemInfo.value = response.data
        return response.data
      }
    } catch (error) {
      console.error('Failed to fetch system info', error)
    }
    return null
  }

  async function resetDefaults() {
    isLoading.value = true
    try {
      const response = await api.post('/admin/settings/reset-defaults')
      if (response.data?.settings) {
        settings.value = { ...settings.value, ...response.data.settings }
        if (settings.value.academicYear) academicYear.value = settings.value.academicYear
        if (settings.value.semester) semester.value = settings.value.semester
      }
      return response.data
    } catch (error) {
      console.error('Failed to reset defaults', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  return {
    academicYear,
    semester,
    settings,
    systemInfo,
    isLoading,
    formattedAcademicTerm,
    fetchSettings,
    updateTerm,
    updateSettings,
    fetchSystemInfo,
    resetDefaults
  }
})

