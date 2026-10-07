import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'
import { useSettingsStore } from '../../../store/settingsStore'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<any>(JSON.parse(localStorage.getItem('auth_user') || 'null'))
  const token = ref(localStorage.getItem('auth_token') || null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const pendingInstructor = ref<any>(null)
  const selectedContext = ref<any>(JSON.parse(localStorage.getItem('instructor_context') || 'null'))
  const notAssignedModal = ref<{
    show: boolean
    instructor?: { name: string; email: string; department?: string } | null
    message: string
  }>({
    show: false,
    instructor: null,
    message: ''
  })
  const router = useRouter()

  const login = async (credentials: any) => {
    isLoading.value = true
    error.value = null
    pendingInstructor.value = null
    notAssignedModal.value.show = false
    const settingsStore = useSettingsStore()

    try {
      let userData: any = null
      let authToken: string = ''
      let assignedOptions: any = null

      try {
        const response = await apiClient.post('/login', credentials)
        userData = response.data.data.user
        authToken = response.data.data.token
        assignedOptions = response.data.data.assigned_options
      } catch (apiErr: any) {
        // Fallback to mock login if backend is unreachable (ERR_NETWORK)
        if (apiErr.code === 'ERR_NETWORK' || !apiErr.response) {
          console.warn('Backend unreachable. Using mock login data for development.')
          await new Promise(resolve => setTimeout(resolve, 600))
          
          userData = {
            id: 1,
            name: 'Dr. Abebe Kebede',
            email: credentials.login || 'instructor@wollo.edu.et',
            role: credentials.role === 'staff' ? 'admin' : (credentials.role || 'instructor')
          }
          authToken = 'mock_jwt_token_for_frontend_dev'
          assignedOptions = {
            departments: [
              { id: 1, name: 'Computer Science' },
              { id: 2, name: 'Information Technology' }
            ],
            courses: [
              { id: 101, title: 'Web Development & Applications', code: 'CS301', department_id: 1, section: 'Section A' },
              { id: 102, title: 'Database Systems', code: 'CS202', department_id: 1, section: 'Section B' },
              { id: 201, title: 'System Administration', code: 'IT401', department_id: 2, section: 'Section A' }
            ],
            sections: ['Section A', 'Section B', 'Section C']
          }
        } else {
          throw apiErr
        }
      }

      // Check if user is instructor -> Require Step 2 Selection
      if (userData.role === 'instructor') {
        // Ensure default assigned options exist if null
        if (!assignedOptions || (!assignedOptions.departments?.length && !assignedOptions.courses?.length)) {
          assignedOptions = {
            departments: [{ id: userData.department_id || 1, name: userData.department?.name || 'Computer Science' }],
            courses: [{ id: 1, title: userData.course_name || 'Assigned Course', code: userData.course_code || 'CS101', department_id: userData.department_id || 1, section: userData.section || 'Section A' }],
            sections: [userData.section || 'Section A', 'Section B', 'Section C']
          }
        }

        pendingInstructor.value = {
          user: userData,
          token: authToken,
          assignedOptions
        }
        return { step: 2 }
      }

      // Standard user flow
      user.value = userData
      token.value = authToken
      localStorage.setItem('auth_token', authToken)
      localStorage.setItem('auth_user', JSON.stringify(userData))
      
      // Redirect based on user role
      if (user.value.role === 'dept_head') {
        router.push('/dept-head/dashboard')
      } else if (user.value.role === 'admin') {
        router.push('/admin/dashboard')
      } else if (user.value.role === 'student') {
        router.push('/student')
      } else {
        router.push('/instructor/dashboard')
      }
      
      // Fetch settings so the academic term badge appears immediately
      settingsStore.fetchSettings()
      return { step: 1 }
      
    } catch (err: any) {
      const respData = err.response?.data
      const errorMsg = respData?.message || (Array.isArray(respData?.errors?.login) ? respData.errors.login[0] : 'An unexpected error occurred or invalid credentials.')

      const isNotAssigned = respData?.error_type === 'instructor_not_assigned' ||
                            errorMsg.toLowerCase().includes('not been assigned to any course')

      if (isNotAssigned) {
        error.value = null // Suppress top error message banner
        notAssignedModal.value = {
          show: true,
          instructor: respData?.instructor || null,
          message: errorMsg
        }
      } else {
        error.value = errorMsg
      }
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const completeInstructorSetup = (selection: { department: string; course: string; section: string }) => {
    if (!pendingInstructor.value) return

    const { user: userData, token: authToken } = pendingInstructor.value
    user.value = userData
    token.value = authToken
    selectedContext.value = selection

    localStorage.setItem('auth_token', authToken)
    localStorage.setItem('auth_user', JSON.stringify(userData))
    localStorage.setItem('instructor_context', JSON.stringify(selection))

    pendingInstructor.value = null

    const settingsStore = useSettingsStore()
    settingsStore.fetchSettings()

    router.push('/instructor/dashboard')
  }

  const fetchCurrentUser = async () => {
    if (!token.value) return
    try {
      const response = await apiClient.get('/user/me')
      if (response.data?.data) {
        user.value = response.data.data
        localStorage.setItem('auth_user', JSON.stringify(response.data.data))
      }
    } catch (e) {
      console.warn('Failed to fetch current user profile:', e)
    }
  }

  const updateProfilePhoto = (newPhotoUrl: string, newPhotoPath?: string) => {
    if (user.value) {
      user.value.profile_picture = newPhotoPath || newPhotoUrl
      user.value.profile_picture_url = newPhotoUrl
      localStorage.setItem('auth_user', JSON.stringify(user.value))
    }
  }

  const updateUserProfile = async (profileData: any) => {
    const response = await apiClient.put('/user/profile', profileData)
    if (response.data?.data) {
      user.value = response.data.data
      localStorage.setItem('auth_user', JSON.stringify(response.data.data))
    }
    return response.data
  }

  const logout = async () => {
    try {
      await apiClient.post('/logout')
    } catch (err) {
      console.warn('Backend unreachable. Proceeding with local logout.')
    } finally {
      user.value = null
      token.value = null
      pendingInstructor.value = null
      selectedContext.value = null
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
      localStorage.removeItem('instructor_context')
      router.push('/login')
    }
  }

  const closeNotAssignedModal = () => {
    notAssignedModal.value.show = false
  }

  return {
    user,
    token,
    isLoading,
    error,
    pendingInstructor,
    selectedContext,
    notAssignedModal,
    closeNotAssignedModal,
    login,
    completeInstructorSetup,
    fetchCurrentUser,
    updateProfilePhoto,
    updateUserProfile,
    logout
  }
})
