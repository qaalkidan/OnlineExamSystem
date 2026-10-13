/**
 * useStudentProfile — fetches the authenticated student's real profile from the backend
 * and maps it onto the StudentProfile shape used by student components.
 */
import { ref } from 'vue'
import { useAuthStore } from '../../auth/store/authStore'
import { useSettingsStore } from '../../../store/settingsStore'
import apiClient from '../../../core/api/apiClient'
import type { StudentProfile } from '../types'

/** Default avatar when no photo is set */
const DEFAULT_AVATAR =
  'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=256'

/**
 * Maps a raw backend user object → StudentProfile shape.
 */
function mapToProfile(user: any, settingsStore?: any): StudentProfile {
  const department =
    (user.department?.name ?? user.department ?? 'Software Engineering') as string

  const photo =
    user.profile_picture_url ||
    user.avatar ||
    user.profile_photo_url ||
    (user.name
      ? `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=6366f1&color=fff&size=256`
      : DEFAULT_AVATAR)

  const formattedDept = department
    ? department.split(' ').map((w: string) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ')
    : 'General Studies'

  // Dynamic Instructor/Course/Settings Academic Year resolution
  const rawYear =
    user.instructor_academic_year ||
    user.academic_year ||
    (settingsStore?.academicYear ? settingsStore.academicYear : '') ||
    '2025/2026'

  const cleanYear = String(rawYear).replace(/\s*academic\s*year\s*/i, '').trim()
  const formattedAcademicYear = cleanYear ? `${cleanYear} Academic Year` : 'Academic Year'

  // Dynamic Instructor/Course/Settings Semester resolution
  const resolvedSemester =
    user.instructor_semester ||
    user.course_semester ||
    user.semester ||
    user.assigned_course?.semester ||
    (settingsStore?.semester ? settingsStore.semester : '') ||
    'First Semester'

  return {
    name: user.name ?? 'Student',
    id: user.id_no ?? user.student_id ?? user.username ?? (user.id ? `WU/${user.id}/18` : 'WU/Student'),
    email: user.email ?? '',
    department: formattedDept,
    program: user.program ?? `Bachelor of Science (B.Sc.) in ${formattedDept}`,
    semester: resolvedSemester,
    academicYear: formattedAcademicYear,
    instructorAcademicYear: user.instructor_academic_year || user.academic_year,
    instructorSemester: user.instructor_semester || user.semester,
    courseSemester: user.course_semester || user.assigned_course?.semester,
    assignedInstructor: user.assigned_instructor,
    assignedCourse: user.assigned_course,
    avatar: photo,
    cgpa: Number(user.cgpa) || 0,
    creditsCompleted: Number(user.credits_completed) || 0,
    phone: user.phone ?? '',
    gender: user.gender ?? 'male',
    section: user.section ?? 'Section A',
    yearLevel: user.year_level ?? '1st Year',
    office: user.office ?? '',
    username: user.username ?? '',
    status: user.status ?? 'Active',
    rawUser: user,
  }
}

export function useStudentProfile() {
  const authStore = useAuthStore()
  const settingsStore = useSettingsStore()
  const isFetching = ref(false)
  const isSaving = ref(false)
  const isUploadingPhoto = ref(false)
  const fetchError = ref<string | null>(null)

  // Start from authStore.user (set immediately after login) so there is
  // never a blank state while the network call is in flight.
  const profile = ref<StudentProfile>(
    authStore.user ? mapToProfile(authStore.user, settingsStore) : mapToProfile({}, settingsStore)
  )

  /**
   * Fetch the latest profile from the backend (/v1/user) and
   * merge it back into both the local ref and the auth store.
   */
  const fetchProfile = async () => {
    const token = localStorage.getItem('auth_token')
    if (!token) return

    isFetching.value = true
    fetchError.value = null

    try {
      // Ensure global settings are also loaded
      if (!settingsStore.academicYear || settingsStore.academicYear === '2026') {
        settingsStore.fetchSettings().catch(() => {})
      }

      const response = await apiClient.get('/user')
      const serverUser = response.data

      authStore.user = { ...authStore.user, ...serverUser }
      profile.value = mapToProfile(serverUser, settingsStore)
    } catch (err: any) {
      fetchError.value = err.message ?? 'Failed to fetch profile'
      console.warn('[useStudentProfile] Using cached login data:', fetchError.value)

      if (authStore.user) {
        profile.value = mapToProfile(authStore.user, settingsStore)
      }
    } finally {
      isFetching.value = false
    }
  }

  /**
   * Update student personal information via PUT /v1/user/profile.
   */
  const updatePersonalProfile = async (payload: {
    name?: string
    phone?: string
    gender?: string
    office?: string
    notification_preferences?: any
  }) => {
    isSaving.value = true
    try {
      const res = await apiClient.put('/user/profile', payload)
      const updatedUser = res.data?.data || res.data?.user || res.data
      authStore.user = { ...authStore.user, ...updatedUser }
      profile.value = mapToProfile(authStore.user)
      return { success: true, message: res.data?.message || 'Profile updated successfully.' }
    } catch (err: any) {
      const msg = err.response?.data?.message || err.message || 'Failed to update profile.'
      const errors = err.response?.data?.errors
      return { success: false, message: msg, errors }
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Upload profile photo via POST /v1/user/profile-photo.
   */
  const uploadPhoto = async (file: File) => {
    isUploadingPhoto.value = true
    try {
      const formData = new FormData()
      formData.append('profile_picture', file)
      const res = await apiClient.post('/user/profile-photo', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      const photoUrl = res.data?.profile_picture_url
      if (photoUrl) {
        if (authStore.user) {
          authStore.user.profile_picture_url = photoUrl
          authStore.user.profile_picture = res.data?.profile_picture
        }
        profile.value.avatar = photoUrl
      }
      return { success: true, message: res.data?.message || 'Profile photo updated successfully.' }
    } catch (err: any) {
      const msg = err.response?.data?.message || err.message || 'Failed to upload photo.'
      return { success: false, message: msg }
    } finally {
      isUploadingPhoto.value = false
    }
  }

  /**
   * Remove profile photo via DELETE /v1/user/profile-photo.
   */
  const removePhoto = async () => {
    isUploadingPhoto.value = true
    try {
      const res = await apiClient.delete('/user/profile-photo')
      if (authStore.user) {
        authStore.user.profile_picture_url = null
        authStore.user.profile_picture = null
      }
      profile.value.avatar = DEFAULT_AVATAR
      return { success: true, message: res.data?.message || 'Profile photo removed.' }
    } catch (err: any) {
      const msg = err.response?.data?.message || err.message || 'Failed to remove photo.'
      return { success: false, message: msg }
    } finally {
      isUploadingPhoto.value = false
    }
  }

  /**
   * Change password via PUT /v1/user/change-password.
   */
  const changePassword = async (payload: {
    current_password: string
    new_password: string
    new_password_confirmation: string
  }) => {
    isSaving.value = true
    try {
      const res = await apiClient.put('/user/change-password', payload)
      return { success: true, message: res.data?.message || 'Password changed successfully.' }
    } catch (err: any) {
      const msg = err.response?.data?.message || err.message || 'Failed to change password.'
      const errors = err.response?.data?.errors
      return { success: false, message: msg, errors }
    } finally {
      isSaving.value = false
    }
  }

  return {
    profile,
    isFetching,
    isSaving,
    isUploadingPhoto,
    fetchError,
    fetchProfile,
    updatePersonalProfile,
    uploadPhoto,
    removePhoto,
    changePassword,
  }
}

