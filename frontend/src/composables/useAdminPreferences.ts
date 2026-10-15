import { ref, watch } from 'vue'

export type TableDensity = 'comfortable' | 'compact'
export type DateDisplayFormat = 'MMM DD, YYYY' | 'YYYY-MM-DD' | 'DD/MM/YYYY'
export type TimezoneDisplay = '(UTC+03:00) Addis Ababa' | 'UTC'

const getStoredPreference = <T extends string>(key: string, defaultValue: T): T => {
  if (typeof window === 'undefined') return defaultValue
  return (localStorage.getItem(key) as T) || defaultValue
}

// Global reactive singletons so all components share the exact same state
const currentDensity = ref<TableDensity>(getStoredPreference('admin_table_density', 'comfortable'))
const currentDateFormat = ref<DateDisplayFormat>(getStoredPreference('admin_date_format', 'MMM DD, YYYY'))
const currentTimezone = ref<TimezoneDisplay>(getStoredPreference('admin_timezone_display', '(UTC+03:00) Addis Ababa'))

export function useAdminPreferences() {
  /**
   * Applies the active density attribute to document.documentElement
   */
  const applyDensityToDom = (density: TableDensity) => {
    if (typeof document !== 'undefined') {
      document.documentElement.setAttribute('data-table-density', density)
    }
  }

  /**
   * Update and persist density
   */
  const setDensity = (density: TableDensity) => {
    currentDensity.value = density
    if (typeof window !== 'undefined') {
      localStorage.setItem('admin_table_density', density)
    }
    applyDensityToDom(density)
  }

  /**
   * Update and persist date format
   */
  const setDateFormat = (format: DateDisplayFormat) => {
    currentDateFormat.value = format
    if (typeof window !== 'undefined') {
      localStorage.setItem('admin_date_format', format)
    }
  }

  /**
   * Update and persist timezone display
   */
  const setTimezoneDisplay = (timezone: TimezoneDisplay) => {
    currentTimezone.value = timezone
    if (typeof window !== 'undefined') {
      localStorage.setItem('admin_timezone_display', timezone)
    }
  }

  /**
   * Universal date formatter respecting admin preference
   */
  const formatDate = (dateInput: string | Date | null | undefined): string => {
    if (!dateInput) return '—'
    const d = typeof dateInput === 'string' ? new Date(dateInput) : dateInput
    if (isNaN(d.getTime())) return String(dateInput)

    const year = d.getFullYear()
    const monthIndex = d.getMonth()
    const day = String(d.getDate()).padStart(2, '0')

    const monthNamesShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    const monthShort = monthNamesShort[monthIndex]
    const monthNum = String(monthIndex + 1).padStart(2, '0')

    switch (currentDateFormat.value) {
      case 'YYYY-MM-DD':
        return `${year}-${monthNum}-${day}`
      case 'DD/MM/YYYY':
        return `${day}/${monthNum}/${year}`
      case 'MMM DD, YYYY':
      default:
        return `${monthShort} ${d.getDate()}, ${year}`
    }
  }

  /**
   * Universal date & time formatter respecting admin preference
   */
  const formatDateTime = (dateInput: string | Date | null | undefined): string => {
    if (!dateInput) return '—'
    const dateFormatted = formatDate(dateInput)
    const d = typeof dateInput === 'string' ? new Date(dateInput) : dateInput
    if (isNaN(d.getTime())) return String(dateInput)

    const hours = String(d.getHours()).padStart(2, '0')
    const minutes = String(d.getMinutes()).padStart(2, '0')
    const tzLabel = currentTimezone.value.includes('Addis Ababa') ? 'EAT' : 'UTC'

    return `${dateFormatted} at ${hours}:${minutes} ${tzLabel}`
  }

  // Ensure DOM attribute is initialized
  applyDensityToDom(currentDensity.value)

  return {
    density: currentDensity,
    dateFormat: currentDateFormat,
    timezone: currentTimezone,
    setDensity,
    setDateFormat,
    setTimezoneDisplay,
    applyDensityToDom,
    formatDate,
    formatDateTime
  }
}
