import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '../../../core/api/apiClient'
import { useAuthStore } from '../../auth/store/authStore'

export interface InstructorProfile {
  id: number
  name: string
  email: string
  username: string
  id_no: string
  phone: string
  office: string
  location: string
  gender: string
  status: string
  role: string
  department_id?: number
  department?: any
  department_name?: string
  course_code?: string
  course_name?: string
  academic_year?: string
  year_level?: string
  semester?: string
  section?: string
  employment_type?: string
  member_since: string
  created_at?: string
  profile_picture?: string | null
  profile_picture_url?: string | null
  notification_preferences: {
    email_notifications: boolean
    exam_notifications: boolean
    result_notifications: boolean
    system_notifications: boolean
    announcements: boolean
    security_notifications: boolean
  }
  preferences: {
    language: string
    timezone: string
    date_format: string
    dark_mode: boolean
  }
  stats?: {
    exams_created: number
    questions_created: number
    students_assessed: number
    reports_generated: number
    hours_saved: number
  }
}

export interface ActivityItem {
  id: number
  action: string
  module: string
  description: string
  created_at: string | null
  formatted_time: string
  relative_time: string
}

export const useProfileStore = defineStore('instructorProfile', () => {
  const authStore = useAuthStore()

  const profile = ref<InstructorProfile | null>(null)
  const activeTab = ref<'personal' | 'security' | 'notifications' | 'password'>('personal')
  const isLoading = ref(false)
  const isSaving = ref(false)
  const isUploadingPhoto = ref(false)
  const isChangingPassword = ref(false)
  const activities = ref<ActivityItem[]>([])
  const allActivities = ref<ActivityItem[]>([])
  const activityPagination = ref({
    total: 0,
    current_page: 1,
    last_page: 1,
    per_page: 10
  })
  const activityModalOpen = ref(false)
  const isLoadingAllActivities = ref(false)

  // Floating Toast
  const toast = ref<{
    show: boolean
    message: string
    type: 'success' | 'error' | 'info'
  }>({
    show: false,
    message: '',
    type: 'success'
  })

  let toastTimer: any = null

  const showToast = (message: string, type: 'success' | 'error' | 'info' = 'success', duration = 3500) => {
    if (toastTimer) clearTimeout(toastTimer)
    toast.value = { show: true, message, type }
    toastTimer = setTimeout(() => {
      toast.value.show = false
    }, duration)
  }

  // Fetch full profile data
  const fetchProfile = async () => {
    isLoading.value = true
    try {
      const response = await apiClient.get('/user/me')
      if (response.data?.data) {
        profile.value = response.data.data
        // Also sync authStore user
        authStore.user = {
          ...authStore.user,
          ...response.data.data
        }
        localStorage.setItem('auth_user', JSON.stringify(authStore.user))
      }
    } catch (err: any) {
      console.error('Failed to fetch instructor profile:', err)
      showToast(err.response?.data?.message || 'Failed to load profile data', 'error')
    } finally {
      isLoading.value = false
    }
  }

  // Update profile fields
  const updateProfile = async (payload: {
    name?: string
    email?: string
    phone?: string
    gender?: string
    office?: string
    location?: string
    address?: string
  }) => {
    isSaving.value = true
    try {
      const response = await apiClient.put('/user/profile', payload)
      if (response.data?.data) {
        profile.value = response.data.data
        authStore.user = {
          ...authStore.user,
          ...response.data.data
        }
        localStorage.setItem('auth_user', JSON.stringify(authStore.user))
      }
      showToast('Profile updated successfully!', 'success')
      fetchRecentActivities()
      return { success: true }
    } catch (err: any) {
      const msg = err.response?.data?.message || err.response?.data?.error || 'Failed to update profile'
      showToast(msg, 'error')
      throw err
    } finally {
      isSaving.value = false
    }
  }

  // Upload avatar photo
  const uploadPhoto = async (file: File) => {
    isUploadingPhoto.value = true
    const formData = new FormData()
    formData.append('profile_picture', file)

    try {
      const response = await apiClient.post('/user/profile-photo', formData)
      const data = response.data
      if (profile.value) {
        profile.value.profile_picture = data.profile_picture
        profile.value.profile_picture_url = data.profile_picture_url
      }
      authStore.updateProfilePhoto(data.profile_picture_url, data.profile_picture)
      showToast('Profile photo updated successfully!', 'success')
      fetchRecentActivities()
      return data
    } catch (err: any) {
      const msg = err.response?.data?.message || 'Failed to upload photo. Please check size (max 2MB).'
      showToast(msg, 'error')
      throw err
    } finally {
      isUploadingPhoto.value = false
    }
  }

  // Change password
  const changePassword = async (payload: {
    current_password: string
    new_password: string
    new_password_confirmation: string
  }) => {
    isChangingPassword.value = true
    try {
      const response = await apiClient.put('/user/change-password', payload)
      showToast(response.data?.message || 'Password changed successfully!', 'success')
      fetchRecentActivities()
      return { success: true }
    } catch (err: any) {
      const errors = err.response?.data?.errors
      let errorMsg = err.response?.data?.message || 'Failed to change password'
      if (errors) {
        const firstKey = Object.keys(errors)[0]
        if (errors[firstKey] && errors[firstKey].length) {
          errorMsg = errors[firstKey][0]
        }
      }
      showToast(errorMsg, 'error')
      throw err
    } finally {
      isChangingPassword.value = false
    }
  }

  // Update notification preferences
  const updateNotificationPreferences = async (preferences: Partial<InstructorProfile['notification_preferences']>) => {
    if (!profile.value) return
    const updated = {
      ...profile.value.notification_preferences,
      ...preferences
    }
    profile.value.notification_preferences = updated

    try {
      await apiClient.put('/user/profile', {
        notification_preferences: updated
      })
      showToast('Notification preferences updated', 'success')
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to save notifications', 'error')
    }
  }

  // Update system preferences (Language, Timezone, Date Format, Dark Mode)
  const updatePreferences = async (newPrefs: Partial<InstructorProfile['preferences']>) => {
    if (!profile.value) return
    const updated = {
      ...profile.value.preferences,
      ...newPrefs
    }
    profile.value.preferences = updated

    try {
      await apiClient.put('/user/profile', {
        preferences: updated
      })
      showToast('Preferences updated successfully', 'success')
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to save preferences', 'error')
    }
  }

  // Fetch recent activities (5 latest)
  const fetchRecentActivities = async () => {
    try {
      const response = await apiClient.get('/user/activity-logs', {
        params: { per_page: 5 }
      })
      if (response.data?.data) {
        activities.value = response.data.data
      }
    } catch (err) {
      console.warn('Failed to load recent activity logs:', err)
    }
  }

  // Fetch all activities (paginated for modal)
  const fetchAllActivities = async (page = 1, perPage = 10) => {
    isLoadingAllActivities.value = true
    try {
      const response = await apiClient.get('/user/activity-logs', {
        params: { page, per_page: perPage }
      })
      if (response.data?.data) {
        allActivities.value = response.data.data
        activityPagination.value = response.data.pagination || {
          total: response.data.data.length,
          current_page: page,
          last_page: 1,
          per_page: perPage
        }
      }
    } catch (err) {
      console.warn('Failed to load full activity logs:', err)
    } finally {
      isLoadingAllActivities.value = false
    }
  }

  const openActivityModal = () => {
    activityModalOpen.value = true
    fetchAllActivities(1)
  }

  const closeActivityModal = () => {
    activityModalOpen.value = false
  }

  return {
    profile,
    activeTab,
    isLoading,
    isSaving,
    isUploadingPhoto,
    isChangingPassword,
    activities,
    allActivities,
    activityPagination,
    activityModalOpen,
    isLoadingAllActivities,
    toast,
    showToast,
    fetchProfile,
    updateProfile,
    uploadPhoto,
    changePassword,
    updateNotificationPreferences,
    updatePreferences,
    fetchRecentActivities,
    fetchAllActivities,
    openActivityModal,
    closeActivityModal
  }
})
