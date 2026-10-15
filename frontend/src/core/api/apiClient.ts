import axios, { type InternalAxiosRequestConfig } from 'axios'

// Determine the most stable base URL.
// When running in the browser on Windows, '127.0.0.1' avoids IPv6 loopback ('::1') DNS flushes
// which Chrome triggers during network interface changes (ERR_NETWORK_CHANGED).
const resolveDefaultBaseUrl = (): string => {
  if (import.meta.env.VITE_API_BASE_URL) {
    return import.meta.env.VITE_API_BASE_URL
  }
  if (typeof window !== 'undefined' && window.location.hostname === '127.0.0.1') {
    return 'http://127.0.0.1:8000/api/v1'
  }
  return 'http://localhost:8000/api/v1'
}

const switchLoopbackHost = (url?: string): string | undefined => {
  if (!url) return url
  if (url.includes('localhost:8000')) {
    return url.replace('localhost:8000', '127.0.0.1:8000')
  }
  if (url.includes('127.0.0.1:8000')) {
    return url.replace('127.0.0.1:8000', 'localhost:8000')
  }
  return url
}

const isNetworkChangedOrDropped = (error: any): boolean => {
  if (!error) return false
  const msg = String(error.message || '').toLowerCase()
  const code = String(error.code || '').toLowerCase()

  return (
    code === 'err_network_changed' ||
    msg.includes('err_network_changed') ||
    code === 'err_network' ||
    msg.includes('network error') ||
    code === 'econnaborted' ||
    code === 'econnreset' ||
    code === 'econnrefused' ||
    code === 'etimedout' ||
    (!error.response && (msg.includes('network') || msg.includes('failed to fetch') || code === 'err_failed'))
  )
}

const isTransientServerError = (error: any): boolean => {
  if (error?.response?.data?.error_type === 'system_maintenance' || error?.response?.data?.maintenance) {
    return false
  }
  const status = error?.response?.status
  return status === 502 || status === 503 || status === 504
}

const apiClient = axios.create({
  baseURL: resolveDefaultBaseUrl(),
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  }
})

// Request Interceptor: Attach token if it exists
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    // If payload is FormData, let the browser set the boundary automatically
    if (config.data instanceof FormData) {
      delete config.headers['Content-Type']
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response Interceptor: Handle 401 Unauthorized globally and retry transient network glitches
apiClient.interceptors.response.use(
  (response) => {
    // If the request was a mutation, dispatch an event so the sidebar can refresh the log count
    const method = response.config.method?.toUpperCase()
    if (method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
      // Ignore the log read endpoints themselves to prevent loop/overhead
      if (!response.config.url?.includes('activity-logs')) {
        window.dispatchEvent(new Event('activity-logged'))
      }
    }
    return response
  },
  async (error) => {
    // 1. Handle 401 Unauthorized globally
    if (error.response && error.response.status === 401) {
      // Only redirect to login if we are NOT already on the login page
      // This prevents an infinite reload loop when unauthenticated requests
      // are made (e.g., fetching settings before login)
      if (typeof window !== 'undefined' && window.location.pathname !== '/') {
        localStorage.removeItem('auth_token')
        window.location.href = '/'
      }
      return Promise.reject(error)
    }

    const config = error.config as (InternalAxiosRequestConfig & {
      __retryCount?: number
      __swappedHost?: boolean
    }) | undefined

    if (!config) {
      return Promise.reject(error)
    }

    const method = (config.method || 'get').toLowerCase()
    const isIdempotent = ['get', 'head', 'options'].includes(method)
    const isNetworkDrop = isNetworkChangedOrDropped(error)
    const isTransientServer = isTransientServerError(error)

    // 2. Automatically retry transient network drops (like ERR_NETWORK_CHANGED) or 502-504
    if (isNetworkDrop || isTransientServer) {
      config.__retryCount = config.__retryCount || 0
      // Max 2 retries for idempotent requests, 1 retry for network drops before response
      const maxRetries = isIdempotent ? 2 : (!error.response ? 1 : 0)

      if (config.__retryCount < maxRetries) {
        config.__retryCount += 1
        const delay = config.__retryCount === 1 ? 300 : 700

        // If localhost failed with a network error, attempt to swap to 127.0.0.1 to bypass IPv6 DNS teardown
        if (isNetworkDrop && !config.__swappedHost) {
          if (config.baseURL) {
            config.baseURL = switchLoopbackHost(config.baseURL)
          }
          if (config.url) {
            config.url = switchLoopbackHost(config.url) || config.url
          }
          config.__swappedHost = true

          // Also update default baseURL for subsequent requests
          if (apiClient.defaults.baseURL) {
            apiClient.defaults.baseURL = switchLoopbackHost(apiClient.defaults.baseURL) || apiClient.defaults.baseURL
          }
        }

        await new Promise((resolve) => setTimeout(resolve, delay))
        return apiClient(config)
      }
    }

    return Promise.reject(error)
  }
)

export default apiClient
