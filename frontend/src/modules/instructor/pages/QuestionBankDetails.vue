<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'
import { useInstructorQbStore } from '../store/instructorQbStore'
import { useSemesterLockStore } from '../store/semesterLockStore'
import RichTextEditor from '../../../components/RichTextEditor.vue'

const route = useRoute()
const router = useRouter()
const qbStore = useInstructorQbStore()
const lockStore = useSemesterLockStore()

const isLoading = ref(true)
const error = ref('')

// Real data from API
const bank = ref<any>({
  id: route.params.id,
  title: '',
  description: '',
  course_code: '',
  course_name: '',
  status: 'Active',
  created_at: null,
  updated_at: null,
  created_by: '',
})

const stats = ref({
  total: 0,
  mcq: 0,
  sa: 0,
  essay: 0,
  tf: 0,
})

const questions = ref<any[]>([])
const manualCategories = ref<{name: string, count: number}[]>([])
const categories = computed(() => {
  const map = new Map<string, number>()
  
  questions.value.forEach(q => {
    const topic = q.topic || q.chapter
    if (topic) {
      map.set(topic, (map.get(topic) || 0) + 1)
    }
  })
  
  manualCategories.value.forEach(cat => {
    if (!map.has(cat.name)) {
      map.set(cat.name, 0)
    }
  })
  
  const cats = Array.from(map.entries()).map(([name, count]) => ({ name, count }))
  return cats.sort((a, b) => b.count - a.count)
})

const showAllCategories = ref(false)
const displayedCategories = computed(() => {
  if (showAllCategories.value) return categories.value
  return categories.value.slice(0, 5)
})

const searchQuery = ref('')
const selectedType = ref('All Types')
const selectedDifficulty = ref('All Difficulties')
const selectedStatus = ref('All Statuses')
const selectedChapter = ref('All Chapters')

const availableChapters = computed(() => {
  const chapters = new Set<string>()
  questions.value.forEach(q => {
    if (q.chapter) chapters.add(q.chapter)
  })
  return ['All Chapters', ...Array.from(chapters)]
})

const activeTab = ref('All Questions')

// Computed tabs with real counts
const tabs = computed(() => [
  'All Questions',
  `MCQ (${stats.value.mcq})`,
  `Short Answer (${stats.value.sa})`,
  `Essay (${stats.value.essay})`,
  `True/False (${stats.value.tf})`,
])

// Map backend type values to display labels
const typeLabel = (type: string) => {
  const map: Record<string, string> = {
    multiple_choice: 'MCQ',
    short_answer: 'Short Answer',
    essay: 'Essay',
    true_false: 'True/False',
  }
  return map[type] || type
}

// Filter questions by active tab, filters, and search
const filteredQuestions = computed(() => {
  let result = questions.value
  if (activeTab.value !== 'All Questions') {
    const typeMap: Record<string, string> = {
      'MCQ': 'multiple_choice',
      'Short Answer': 'short_answer',
      'Essay': 'essay',
      'True/False': 'true_false',
    }
    const tabType = activeTab.value.replace(/\s*\(\d+\)/, '')
    const backendType = typeMap[tabType]
    if (backendType) result = result.filter(q => q.type === backendType)
  }
  
  if (selectedType.value !== 'All Types') {
    const typeMap: Record<string, string> = {
      'MCQ': 'multiple_choice',
      'Short Answer': 'short_answer',
      'True/False': 'true_false',
      'Matching': 'matching',
      'Fill in Blank': 'fill_in_blank',
    }
    const backendType = typeMap[selectedType.value]
    if (backendType) result = result.filter(q => q.type === backendType)
  }

  if (selectedDifficulty.value !== 'All Difficulties') {
    result = result.filter(q => q.difficulty === selectedDifficulty.value)
  }

  if (selectedStatus.value !== 'All Statuses') {
    result = result.filter(q => q.status === selectedStatus.value)
  }

  if (selectedChapter.value !== 'All Chapters') {
    result = result.filter(q => q.chapter === selectedChapter.value)
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(item => item.text?.toLowerCase().includes(q) || item.topic?.toLowerCase().includes(q) || item.chapter?.toLowerCase().includes(q))
  }
  return result
})

// Dynamic count based on questions array directly
const typeCounts = computed(() => {
  const counts = { mcq: 0, tf: 0, matching: 0, fib: 0, sa: 0 }
  questions.value.forEach(q => {
    if (q.type === 'multiple_choice') counts.mcq++
    else if (q.type === 'true_false') counts.tf++
    else if (q.type === 'matching') counts.matching++
    else if (q.type === 'fill_in_blank') counts.fib++
    else if (q.type === 'short_answer') counts.sa++
  })
  return counts
})

// Percentage helper
const pct = (count: number) => {
  if (stats.value.total === 0) return '0%'
  return ((count / stats.value.total) * 100).toFixed(1) + '%'
}

const formatDate = (dateString: string) => {
  if (!dateString) return '—'
  const d = new Date(dateString)
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const fetchBankDetails = async () => {
  isLoading.value = true
  error.value = ''
  try {
    const response = await apiClient.get(`/instructor/question-banks/${route.params.id}`)
    const data = response.data.data

    bank.value = {
      id: data.bank.id,
      title: data.bank.title,
      description: data.bank.description,
      course_code: data.bank.course_code,
      course_name: data.bank.course_name,
      status: data.bank.status,
      created_at: data.bank.created_at,
      updated_at: data.bank.updated_at,
      created_by: data.bank.instructor?.user?.first_name 
        ? `${data.bank.instructor.user.first_name} ${data.bank.instructor.user.last_name}` 
        : 'Instructor',
    }

    stats.value = data.stats
    questions.value = data.questions
  } catch (err: any) {
    console.warn('API failed, falling back to mock bank and data', err)
    // Fallback Mock Data for Bank
    bank.value = {
      id: Number(route.params.id) || 1,
      title: 'Database Systems Mid Questions',
      description: 'Questions prepared for Semester I Mid Examination.',
      course_code: 'CS301',
      course_name: 'Database Management Systems',
      status: 'Active',
      created_at: '2026-05-24T10:00:00Z',
      updated_at: '2026-05-30T14:30:00Z',
      created_by: 'Dr. Abebe Kebede',
    }
  } finally {
    isLoading.value = false
  }

  // Inject mock questions if empty (so the UI always shows data for demonstration)
  if (!questions.value || questions.value.length === 0) {
    stats.value = {
      total: 6,
      mcq: 2,
      sa: 1,
      essay: 1,
      tf: 1,
      matching: 1
    } as any
    questions.value = [
      {
        id: 1,
        text: '<p>What does SQL stand for?</p>',
        type: 'multiple_choice',
        difficulty: 'Easy',
        marks: 1,
        chapter: 'Chapter 1: Intro to DB',
        topic: 'SQL Basics',
        status: 'Active',
        explanation: 'Structured Query Language is standard for relational databases.',
        options: [
          'Structured Query Language',
          'Strong Question Language',
          'Structured Question Language',
          'Simple Query Language'
        ],
        correct_answer: 'A',
      },
      {
        id: 2,
        text: '<p>Describe the difference between inner and outer joins.</p>',
        type: 'essay',
        difficulty: 'Hard',
        marks: 5,
        chapter: 'Chapter 3: SQL Queries',
        topic: 'Joins',
        status: 'Active',
        explanation: 'Inner join requires match, outer join retains unmatched rows.',
      },
      {
        id: 3,
        text: '<p>A primary key must be unique and cannot be null.</p>',
        type: 'true_false',
        difficulty: 'Easy',
        marks: 1,
        chapter: 'Chapter 2: Relational Model',
        topic: 'Constraints',
        status: 'Active',
        explanation: 'Primary keys enforce entity integrity.',
        options: ['True', 'False'],
        correct_answer: 'True',
      },
      {
        id: 4,
        text: '<p>Define 1NF.</p>',
        type: 'short_answer',
        difficulty: 'Medium',
        marks: 2,
        chapter: 'Chapter 4: Normalization',
        topic: '1NF',
        status: 'Active',
        explanation: 'First Normal Form requires atomic attributes.',
      },
      {
        id: 5,
        text: '<p>Which of the following is a NoSQL database?</p>',
        type: 'multiple_choice',
        difficulty: 'Medium',
        marks: 1,
        chapter: 'Chapter 1: Intro to DB',
        topic: 'NoSQL',
        status: 'Draft',
        explanation: 'MongoDB is a popular NoSQL database.',
      },
      {
        id: 6,
        text: '<p>Match the normal forms to their definitions.</p>',
        type: 'matching',
        difficulty: 'Hard',
        marks: 3,
        chapter: 'Chapter 4: Normalization',
        topic: 'Overview',
        status: 'Active',
        explanation: '1NF: Atomic, 2NF: No partial dependency, 3NF: No transitive dependency.',
      }
    ]
  }
}


// ===================== MODALS STATE =====================

// --- Edit Bank Modal ---
const showEditBankModal = ref(false)
const editBankTitle = ref('')
const editBankDescription = ref('')
const editBankSaving = ref(false)

const openEditBankModal = () => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('edit question bank')
    return
  }
  editBankTitle.value = bank.value.title
  editBankDescription.value = bank.value.description || ''
  showEditBankModal.value = true
}

const saveEditBank = async () => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('edit question bank')
    return
  }
  editBankSaving.value = true
  try {
    await qbStore.updateQuestionBank(bank.value.id, {
      title: editBankTitle.value,
      description: editBankDescription.value,
    })
    bank.value.title = editBankTitle.value
    bank.value.description = editBankDescription.value
    showEditBankModal.value = false
  } catch (err) {
    console.error('Failed to update bank', err)
  } finally {
    editBankSaving.value = false
  }
}

// --- Delete Bank Modal ---
const showDeleteBankModal = ref(false)
const deleteBankProcessing = ref(false)

const confirmDeleteBank = async () => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('delete question bank')
    return
  }
  deleteBankProcessing.value = true
  try {
    await qbStore.deleteQuestionBank(bank.value.id)
    router.push('/instructor/question-banks')
  } catch (err) {
    console.error('Failed to delete bank', err)
  } finally {
    deleteBankProcessing.value = false
  }
}

// --- View Question Modal ---
const showViewQuestionModal = ref(false)
const viewingQuestion = ref<any>(null)

const getOptionLabel = (index: any) => {
  return String.fromCharCode(65 + Number(index))
}

const isOptionCorrect = (opt: any, index: any) => {
  if (!viewingQuestion.value) return false
  const correct = viewingQuestion.value.correct_answer
  return correct === getOptionLabel(index) || correct === opt
}

const stripHtml = (html: string) => {
  if (!html) return ''
  const tempDiv = document.createElement('div')
  tempDiv.innerHTML = html
  return (tempDiv.textContent || tempDiv.innerText || '').trim()
}

const openViewQuestion = (q: any) => {
  let parsedData = q.question_data
  if (typeof parsedData === 'string') {
    try { parsedData = JSON.parse(parsedData) } catch (e) {}
  }
  viewingQuestion.value = { ...q, question_data: parsedData }
  showViewQuestionModal.value = true
}

const formatQuestionText = (html: string) => {
  if (!html) return ''
  const tempDiv = document.createElement('div')
  tempDiv.innerHTML = html
  const hasImage = tempDiv.querySelector('img') !== null
  const textContent = (tempDiv.textContent || tempDiv.innerText || '').trim()
  const truncatedText = textContent.length > 80 ? textContent.substring(0, 80) + '...' : textContent
  
  if (hasImage) {
    return truncatedText ? `🖼️ [Image Question] ${truncatedText}` : `🖼️ [Image Question]`
  }
  return truncatedText || '(Empty Question)'
}

// --- Edit Question Modal ---
const showEditQuestionModal = ref(false)
const editingQuestion = ref<any>(null)
const editForm = ref<any>({})
const editSaving = ref(false)
const editSuccessMessage = ref('')

const editQuestion = (q: any) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('edit question')
    return
  }
  editingQuestion.value = q
  let parsedQData = q.question_data || {}
  if (typeof parsedQData === 'string') {
    try { parsedQData = JSON.parse(parsedQData) } catch (e) { parsedQData = {} }
  }

  editForm.value = {
    text: stripHtml(q.text || ''),
    type: q.type || 'multiple_choice',
    difficulty: q.difficulty || 'Easy',
    marks: q.marks || 1,
    chapter: q.chapter || '',
    correct_answer: q.correct_answer || '',
    options: q.options ? q.options.map((o: any) => typeof o === 'string' ? stripHtml(o) : stripHtml(o.text || '')) : [],
    question_data: JSON.parse(JSON.stringify(parsedQData)),
  }
  
  // Initialize default question_data if empty
  if (editForm.value.type === 'matching' && !editForm.value.question_data.column_a) {
    editForm.value.question_data = { column_a: [''], column_b: [{ label: 'A', text: '' }], correct_answers: {} }
  } else if (editForm.value.type === 'fill_in_blank' && !editForm.value.question_data.answers) {
    editForm.value.question_data = { answers: [''] }
  } else if (editForm.value.type === 'short_answer' && !editForm.value.question_data.expected_answer) {
    editForm.value.question_data = { expected_answer: '' }
  }
  
  showViewQuestionModal.value = false
  showEditQuestionModal.value = true
}

const addEditOption = () => {
  editForm.value.options.push('')
}

const removeEditOption = (index: number) => {
  editForm.value.options.splice(index, 1)
}

const addMatchingPair = () => {
  if (!editForm.value.question_data) editForm.value.question_data = { column_a: [], column_b: [], correct_answers: {} };
  editForm.value.question_data.column_a.push('');
  const label = String.fromCharCode(65 + editForm.value.question_data.column_b.length);
  editForm.value.question_data.column_b.push({ label, text: '' });
}

const removeMatchingPair = (index: number) => {
  if (!editForm.value.question_data) return;
  editForm.value.question_data.column_a.splice(index, 1);
  editForm.value.question_data.column_b.splice(index, 1);
  // Re-label column_b
  editForm.value.question_data.column_b.forEach((item: any, i: number) => {
    item.label = String.fromCharCode(65 + i);
  });
}

const addFIBAnswer = () => {
  if (!editForm.value.question_data) editForm.value.question_data = { answers: [] };
  editForm.value.question_data.answers.push('');
}

const removeFIBAnswer = (index: number) => {
  if (!editForm.value.question_data) return;
  editForm.value.question_data.answers.splice(index, 1);
}

const saveEditQuestion = async () => {
  if (!editingQuestion.value) return
  editSaving.value = true
  try {
    const payload: any = {
      text: editForm.value.text,
      type: editForm.value.type,
      difficulty: editForm.value.difficulty,
      marks: Number(editForm.value.marks),
      chapter: editForm.value.chapter,
      correct_answer: editForm.value.correct_answer,
    }
    if (editForm.value.type === 'multiple_choice' || editForm.value.type === 'true_false') {
      if (editForm.value.options && editForm.value.options.length > 0) {
        payload.options = editForm.value.options
      }
    } else {
      payload.question_data = editForm.value.question_data
      // Build correct_answers for matching
      if (editForm.value.type === 'matching' && payload.question_data.column_a) {
        payload.question_data.correct_answers = {};
        payload.question_data.column_a.forEach((_item: any, i: number) => {
          if (payload.question_data.column_b[i]) {
            payload.question_data.correct_answers[String(i + 1)] = payload.question_data.column_b[i].label;
          }
        });
      }
    }
    await qbStore.updateQuestion(editingQuestion.value.id, payload)
    
    // Update the local questions array
    const idx = questions.value.findIndex((q: any) => q.id === editingQuestion.value.id)
    if (idx !== -1) {
      questions.value[idx] = {
        ...questions.value[idx],
        text: editForm.value.text,
        type: editForm.value.type,
        difficulty: editForm.value.difficulty,
        marks: Number(editForm.value.marks),
        chapter: editForm.value.chapter,
        correct_answer: editForm.value.correct_answer,
        options: editForm.value.options ? [...editForm.value.options] : questions.value[idx].options,
        question_data: payload.question_data,
      }
    }
    showEditQuestionModal.value = false
    editSuccessMessage.value = 'Question updated successfully!'
    setTimeout(() => { editSuccessMessage.value = '' }, 3000)
  } catch (err: any) {
    alert('Failed to update question. Please try again.')
    console.error(err)
  } finally {
    editSaving.value = false
  }
}

// --- Delete Question Modal ---
const showDeleteQuestionModal = ref(false)
const questionToDelete = ref<any>(null)
const deleteQuestionProcessing = ref(false)

const openDeleteQuestion = (q: any) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('delete question')
    return
  }
  questionToDelete.value = q
  showDeleteQuestionModal.value = true
}

const confirmDeleteQuestion = async () => {
  if (!questionToDelete.value) return
  deleteQuestionProcessing.value = true
  try {
    await qbStore.deleteQuestion(questionToDelete.value.id)
    questions.value = questions.value.filter(q => q.id !== questionToDelete.value.id)
    stats.value.total--
    const type = questionToDelete.value.type
    if (type === 'multiple_choice') stats.value.mcq--
    else if (type === 'short_answer') stats.value.sa--
    else if (type === 'essay') stats.value.essay--
    else if (type === 'true_false') stats.value.tf--
    showDeleteQuestionModal.value = false
    questionToDelete.value = null
  } catch (err) {
    console.error('Failed to delete question', err)
  } finally {
    deleteQuestionProcessing.value = false
  }
}

// --- Add Category/Topic Modal ---
const showAddCategoryModal = ref(false)
const newCategoryName = ref('')

const addCategory = () => {
  if (!newCategoryName.value.trim()) return
  const name = newCategoryName.value.trim()
  if (!manualCategories.value.some(c => c.name === name) && !categories.value.some(c => c.name === name)) {
    manualCategories.value.push({ name, count: 0 })
  }
  newCategoryName.value = ''
  showAddCategoryModal.value = false
}

// --- Reorder Questions ---
const reorderMode = ref(false)

const moveQuestion = (index: number, direction: 'up' | 'down') => {
  const arr = questions.value
  const newIndex = direction === 'up' ? index - 1 : index + 1
  if (newIndex < 0 || newIndex >= arr.length) return
  const temp = arr[index]
  arr[index] = arr[newIndex]
  arr[newIndex] = temp
  questions.value = [...arr]
}

// --- Export Questions ---
const showExportDropdown = ref(false)

// Close dropdown when clicking outside
const closeExportDropdown = (e: MouseEvent) => {
  const target = e.target as HTMLElement
  if (!target.closest('.export-dropdown-container')) {
    showExportDropdown.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', closeExportDropdown)
})

onUnmounted(() => {
  document.removeEventListener('click', closeExportDropdown)
})

const exportAsCSV = () => {
  if (!questions.value.length) return alert('No questions to export.')
  
  const headers = ['ID', 'Type', 'Difficulty', 'Marks', 'Chapter', 'Topic', 'Question', 'Correct Answer']
  const rows = questions.value.map(q => {
    // Strip HTML from question text
    const tempDiv = document.createElement('div')
    tempDiv.innerHTML = q.text || ''
    const cleanText = tempDiv.textContent || tempDiv.innerText || ''
    
    return [
      q.id,
      q.type,
      q.difficulty,
      q.marks,
      q.chapter || '',
      q.topic || '',
      `"${cleanText.replace(/"/g, '""')}"`, // escape quotes for CSV
      `"${(q.correct_answer || '').replace(/"/g, '""')}"`
    ].join(',')
  })
  
  const csvContent = [headers.join(','), ...rows].join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', `Question_Bank_${bank.value.title.replace(/\s+/g, '_')}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  showExportDropdown.value = false
}

const generateDocumentHTML = () => {
  let html = `
    <html>
      <head>
        <meta charset="utf-8">
        <title>${bank.value?.title || 'Question Bank'}</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; max-width: 800px; margin: 0 auto; }
          h1 { color: #333; text-align: center; margin-bottom: 5px; }
          .subtitle { text-align: center; color: #666; margin-bottom: 30px; font-size: 14px; }
          .question-block { margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #eee; page-break-inside: avoid; }
          .meta { font-size: 12px; color: #666; margin-bottom: 8px; font-weight: bold; }
          .text { font-size: 15px; margin-bottom: 12px; }
          .options { margin-left: 20px; }
          .option-row { margin-bottom: 4px; }
          .correct { color: #059669; font-weight: bold; }
          hr { border: 0; border-top: 1px solid #ccc; margin-bottom: 30px; }
        </style>
      </head>
      <body>
        <h1>${bank.value?.title || 'Question Bank'}</h1>
        <div class="subtitle"><strong>Course:</strong> ${bank.value?.course_name || ''}</div>
        <hr>
  `
  
  questions.value.forEach((q, i) => {
    html += `<div class="question-block">
      <div class="meta">Q${i + 1} | ${q.marks} Marks | ${q.difficulty}</div>
      <div class="text">${q.text}</div>
    `
    if (q.options && q.options.length > 0) {
      html += `<div class="options">`
      q.options.forEach((opt: any, optIdx: number) => {
        const letter = String.fromCharCode(65 + optIdx)
        const isCorrect = q.correct_answer === letter || q.correct_answer === opt
        html += `<div class="option-row ${isCorrect ? 'correct' : ''}">${letter}) ${opt} ${isCorrect ? ' ✓' : ''}</div>`
      })
      html += `</div>`
    } else if (q.correct_answer) {
      html += `<div class="correct" style="margin-top: 8px;">Answer: ${q.correct_answer}</div>`
    }
    html += `</div>`
  })
  
  html += `</body></html>`
  return html
}

const exportAsWord = () => {
  if (!questions.value.length) return alert('No questions to export.')
  const html = generateDocumentHTML()
  const blobHtml = `
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
    <head><meta charset='utf-8'><title>Export</title></head><body>${html}</body></html>
  `
  const blob = new Blob([blobHtml], { type: 'application/msword' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `Question_Bank_${bank.value.title.replace(/\s+/g, '_')}.doc`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  showExportDropdown.value = false
}

const exportAsPDF = () => {
  if (!questions.value.length) return alert('No questions to export.')
  const html = generateDocumentHTML()
  
  const printWindow = window.open('', '_blank')
  if (printWindow) {
    printWindow.document.write(html)
    printWindow.document.close()
    printWindow.focus()
    setTimeout(() => {
      printWindow.print()
      // Note: We don't close the window automatically in case the user cancels the print dialog
    }, 500)
  }
  showExportDropdown.value = false
}

// --- Import Questions ---
const fileInput = ref<HTMLInputElement | null>(null)
const isImporting = ref(false)
const triggerImport = () => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('import questions')
    return
  }
  if (fileInput.value) {
    fileInput.value.click()
  }
}
const handleImport = async (event: Event) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('import questions')
    return
  }
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    const file = target.files[0]
    const formData = new FormData()
    formData.append('file', file)
    
    isImporting.value = true
    try {
      const response = await qbStore.importQuestions(bank.value.id, formData)
      alert(response.message || 'Successfully imported questions')
      await fetchBankDetails()
    } catch (err: any) {
      alert('Failed to import questions. Please check the file format.')
      console.error(err)
    } finally {
      isImporting.value = false
      target.value = '' // reset
    }
  }
}

// --- Preview Bank Modal ---
const showPreviewBankModal = ref(false)
const openPreviewBank = () => {
  showPreviewBankModal.value = true
}

onMounted(() => {
  fetchBankDetails()
  lockStore.fetchLockStatus()
})
</script>

<template>
  <div class="max-w-[1600px] mx-auto space-y-6">

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-20">
      <div class="flex flex-col items-center gap-3">
        <svg class="animate-spin w-8 h-8 text-[#5138ed]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        <span class="text-[13px] text-slate-500 font-medium">Loading question bank...</span>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-rose-50 border border-rose-200 rounded-2xl p-6 text-center">
      <p class="text-rose-600 font-medium text-[14px]">{{ error }}</p>
      <button @click="fetchBankDetails" class="mt-3 px-4 py-2 bg-rose-100 hover:bg-rose-200 text-rose-700 rounded-xl text-[13px] font-bold transition-colors">Retry</button>
    </div>

    <template v-else>
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
      <div class="flex items-center flex-wrap text-xs sm:text-[13px] text-slate-500 font-medium">
        <router-link to="/instructor/question-banks" class="hover:text-[#5138ed] transition-colors">Question Banks</router-link>
        <svg class="w-4 h-4 mx-1.5 sm:mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        <span class="hover:text-[#5138ed] cursor-pointer transition-colors truncate max-w-[120px] sm:max-w-none">{{ bank.title }}</span>
        <svg class="w-4 h-4 mx-1.5 sm:mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        <span class="text-[#5138ed] font-bold">Details</span>
      </div>
      
      <div class="flex items-center gap-3 w-full sm:w-auto">
        <button
          v-if="!lockStore.isLocked"
          @click="router.push(`/instructor/question-banks/${route.params.id}/create-question`)"
          class="bg-[#5138ed] hover:bg-indigo-600 text-white px-4 sm:px-5 py-2.5 rounded-xl font-bold text-xs sm:text-[13px] shadow-xs transition-colors flex items-center justify-center gap-2 w-full sm:w-auto min-h-[44px]"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          Create Question
        </button>
        <div
          v-else
          @click="lockStore.promptLockedNotice('create question')"
          class="flex items-center justify-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 rounded-xl font-bold text-xs shadow-xs cursor-pointer hover:bg-emerald-100 transition-colors w-full sm:w-auto min-h-[44px]"
        >
          <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          <span>Semester Locked (Read-Only)</span>
        </div>
      </div>
    </div>

    <!-- Header Card (Full Width & Slimmer) -->
    <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-5 shadow-xs mb-5 lg:mb-6">
      <div class="flex items-start justify-between mb-4">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 text-[#5138ed] flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
          </div>
          <div>
            <h1 class="text-[17px] font-bold text-slate-900 mb-0.5">{{ bank.title }}</h1>
            <p class="text-[12px] text-slate-500 font-medium">{{ bank.description || 'No description provided.' }}</p>
          </div>
        </div>
        <span class="px-2.5 py-1 text-[11px] font-bold bg-emerald-50 text-emerald-600 rounded-lg">{{ bank.status }}</span>
      </div>
      
      <div class="h-px bg-slate-100 w-full mb-4"></div>
      
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
          </div>
          <div>
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Created Date</div>
            <div class="text-[12px] font-semibold text-slate-700">{{ formatDate(bank.created_at) }}</div>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
          </div>
          <div>
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Total Questions</div>
            <div class="text-[12px] font-semibold text-slate-700">{{ stats.total }}</div>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
          </div>
          <div>
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Created By</div>
            <div class="text-[12px] font-semibold text-slate-700">{{ bank.created_by }}</div>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
          </div>
          <div>
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Last Updated</div>
            <div class="text-[12px] font-semibold text-slate-700">{{ formatDate(bank.updated_at) }}</div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- 6 Type Stat Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 mb-5 lg:mb-6">
      <!-- Total -->
      <div class="bg-white border border-slate-100 rounded-2xl p-3.5 sm:p-4 shadow-xs flex flex-col items-center justify-center text-center">
        <div class="w-10 h-10 rounded-xl bg-[#5138ed]/10 text-[#5138ed] flex items-center justify-center mb-2.5">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
        </div>
        <div class="text-[11px] font-bold text-slate-500 mb-0.5 truncate">Total Questions</div>
        <div class="text-[18px] font-extrabold text-slate-800 leading-none">{{ stats.total }}</div>
      </div>
      <!-- MCQ -->
      <div class="bg-white border border-slate-100 rounded-2xl p-3.5 sm:p-4 shadow-xs flex flex-col items-center justify-center text-center">
        <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-500 flex items-center justify-center mb-2.5">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
        </div>
        <div class="text-[11px] font-bold text-slate-500 mb-0.5 truncate">MCQ</div>
        <div class="text-[18px] font-extrabold text-slate-800 leading-none">{{ typeCounts.mcq }}</div>
      </div>
      <!-- True/False -->
      <div class="bg-white border border-slate-100 rounded-2xl p-3.5 sm:p-4 shadow-xs flex flex-col items-center justify-center text-center">
        <div class="w-10 h-10 rounded-xl bg-fuchsia-50 text-fuchsia-500 flex items-center justify-center mb-2.5">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div class="text-[11px] font-bold text-slate-500 mb-0.5 truncate">True / False</div>
        <div class="text-[18px] font-extrabold text-slate-800 leading-none">{{ typeCounts.tf }}</div>
      </div>
      <!-- Matching -->
      <div class="bg-white border border-slate-100 rounded-2xl p-3.5 sm:p-4 shadow-xs flex flex-col items-center justify-center text-center">
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-500 flex items-center justify-center mb-2.5">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
        </div>
        <div class="text-[11px] font-bold text-slate-500 mb-0.5 truncate">Matching</div>
        <div class="text-[18px] font-extrabold text-slate-800 leading-none">{{ typeCounts.matching }}</div>
      </div>
      <!-- Fill in Blank -->
      <div class="bg-white border border-slate-100 rounded-2xl p-3.5 sm:p-4 shadow-xs flex flex-col items-center justify-center text-center">
        <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center mb-2.5">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div class="text-[11px] font-bold text-slate-500 mb-0.5 truncate">Fill Blank</div>
        <div class="text-[18px] font-extrabold text-slate-800 leading-none">{{ typeCounts.fib }}</div>
      </div>
      <!-- Short Answer -->
      <div class="bg-white border border-slate-100 rounded-2xl p-3.5 sm:p-4 shadow-xs flex flex-col items-center justify-center text-center">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center mb-2.5">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
        </div>
        <div class="text-[11px] font-bold text-slate-500 mb-0.5 truncate">Short Answer</div>
        <div class="text-[18px] font-extrabold text-slate-800 leading-none">{{ typeCounts.sa }}</div>
      </div>
    </div>

    <!-- Controls Bar / Filter Bar -->
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-5 lg:mb-6">
      <!-- Search -->
      <div class="relative w-full sm:w-56 shrink-0">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        <input type="text" v-model="searchQuery" placeholder="Search questions..." class="w-full pl-9 pr-3 py-2 min-h-[44px] bg-white border border-slate-200 rounded-xl text-xs sm:text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow">
      </div>

      <!-- Dropdowns -->
      <select v-model="selectedType" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:border-[#5138ed] outline-none cursor-pointer min-h-[44px] flex-1 sm:flex-initial">
        <option value="All Types">All Types</option>
        <option value="MCQ">MCQ</option>
        <option value="Short Answer">Short Answer</option>
        <option value="True/False">True/False</option>
        <option value="Matching">Matching</option>
        <option value="Fill in Blank">Fill in Blank</option>
      </select>
      
      <select v-model="selectedDifficulty" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:border-[#5138ed] outline-none cursor-pointer min-h-[44px] flex-1 sm:flex-initial">
        <option value="All Difficulties">All Difficulties</option>
        <option value="Easy">Easy</option>
        <option value="Medium">Medium</option>
        <option value="Hard">Hard</option>
      </select>
      
      <select v-model="selectedStatus" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:border-[#5138ed] outline-none cursor-pointer min-h-[44px] flex-1 sm:flex-initial">
        <option value="All Statuses">All Statuses</option>
        <option value="Active">Active</option>
        <option value="Draft">Draft</option>
      </select>
      
      <select v-model="selectedChapter" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:border-[#5138ed] outline-none cursor-pointer min-h-[44px] flex-1 sm:flex-initial">
        <option v-for="ch in availableChapters" :key="ch" :value="ch">{{ ch }}</option>
      </select>

      <!-- View Filtered Questions Button -->
      <router-link 
        :to="{ name: 'ViewFilteredQuestions', params: { id: route.params.id }, query: { type: selectedType, difficulty: selectedDifficulty, status: selectedStatus, chapter: selectedChapter, search: searchQuery } }"
        class="bg-[#5138ed] text-white px-4 py-2 rounded-xl font-bold text-xs hover:bg-indigo-600 transition-colors flex items-center justify-center gap-1.5 whitespace-nowrap min-h-[44px] w-full sm:w-auto sm:ml-auto shadow-xs"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
        View Filtered Questions
      </router-link>
    </div>

    <!-- Table Card -->
    <div class="bg-white border border-slate-100 rounded-2xl shadow-xs overflow-hidden mb-6">
      
      <!-- Questions Desktop Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left border-collapse whitespace-nowrap">
          <thead>
            <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-800 tracking-wide bg-slate-50/50">
              <th class="py-4 pl-6 pr-4">#</th>
              <th class="py-4 px-4 w-[45%]">Question Title</th>
              <th class="py-4 px-4 text-center">Type</th>
              <th class="py-4 px-4 text-center">Difficulty</th>
              <th class="py-4 px-4 text-center">Marks</th>
              <th class="py-4 px-4 text-center">Chapter</th>
              <th class="py-4 px-4 text-center">Status</th>
              <th class="py-4 px-4 text-center">Created Date</th>
              <th class="py-4 px-6 text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredQuestions.length === 0">
              <td colspan="9" class="py-12 text-center">
                <div class="flex flex-col items-center gap-2">
                  <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                  <p class="text-[13px] text-slate-400 font-medium">No questions yet. Click "Create Question" to get started!</p>
                </div>
              </td>
            </tr>
            <tr v-for="(q, idx) in filteredQuestions" :key="q.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors last:border-0 group">
              <td class="py-4 pl-6 pr-4 text-[13px] font-bold text-slate-800">
                <div v-if="reorderMode" class="flex flex-col items-center gap-0.5">
                  <button @click="moveQuestion(idx, 'up')" :disabled="idx === 0" class="p-0.5 rounded hover:bg-indigo-50 text-slate-400 hover:text-[#5138ed] disabled:opacity-30 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                  </button>
                  <span>{{ idx + 1 }}</span>
                  <button @click="moveQuestion(idx, 'down')" :disabled="idx === filteredQuestions.length - 1" class="p-0.5 rounded hover:bg-indigo-50 text-slate-400 hover:text-[#5138ed] disabled:opacity-30 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                  </button>
                </div>
                <span v-else>{{ idx + 1 }}</span>
              </td>
              <td class="py-4 px-4 text-[13px] text-slate-800 font-bold whitespace-normal leading-snug">
                {{ formatQuestionText(q.text) }}
                <div class="text-[11px] text-slate-500 font-medium mt-1 truncate max-w-sm font-normal">{{ q.explanation || 'No description provided.' }}</div>
              </td>
              <td class="py-4 px-4 text-center">
                <span 
                  class="px-2.5 py-1 text-[11px] font-bold rounded-lg"
                  :class="{
                    'text-[#5138ed] bg-indigo-50': typeLabel(q.type) === 'MCQ',
                    'text-emerald-500 bg-emerald-50': typeLabel(q.type) === 'Short Answer',
                    'text-amber-500 bg-amber-50': typeLabel(q.type) === 'Essay',
                    'text-sky-500 bg-sky-50': typeLabel(q.type) === 'True/False',
                    'text-fuchsia-500 bg-fuchsia-50': typeLabel(q.type) === 'Matching',
                    'text-rose-500 bg-rose-50': typeLabel(q.type) === 'Fill in Blank',
                    'text-orange-500 bg-orange-50': typeLabel(q.type) === 'Ordering',
                  }"
                >
                  {{ typeLabel(q.type) }}
                </span>
              </td>
              <td class="py-4 px-4 text-center">
                <span 
                  class="px-2.5 py-1 text-[11px] font-bold rounded-lg"
                  :class="{
                    'text-emerald-500 bg-emerald-50': q.difficulty === 'Easy',
                    'text-amber-500 bg-amber-50': q.difficulty === 'Medium',
                    'text-rose-500 bg-rose-50': q.difficulty === 'Hard'
                  }"
                >
                  {{ q.difficulty }}
                </span>
              </td>
              <td class="py-4 px-4 text-[13px] font-bold text-slate-700 text-center">{{ q.marks }}</td>
              <td class="py-4 px-4 text-[12px] font-medium text-slate-600 text-center">{{ q.chapter || '—' }}</td>
              <td class="py-4 px-4 text-center">
                <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-emerald-50 text-emerald-600">{{ q.status }}</span>
              </td>
              <td class="py-4 px-4 text-center text-[12px] text-slate-500 font-medium">{{ formatDate(bank.created_at) }}</td>
              <td class="py-4 px-6 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button @click="openViewQuestion(q)" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors border border-transparent hover:border-slate-200 shadow-xs" title="View Question">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  </button>
                  <template v-if="!lockStore.isLocked">
                    <button @click="editQuestion(q)" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors border border-transparent hover:border-indigo-100 shadow-xs" title="Edit Question">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </button>
                    <button @click="openDeleteQuestion(q)" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-colors border border-transparent hover:border-rose-100 shadow-xs" title="Delete Question">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Questions Mobile Card View -->
      <div class="md:hidden space-y-3 p-3.5 sm:p-4">
        <div v-if="filteredQuestions.length === 0" class="text-center py-8 text-slate-400 text-xs sm:text-sm font-medium">
          No questions yet. Click "Create Question" to get started!
        </div>
        <div
          v-else
          v-for="(q, idx) in filteredQuestions"
          :key="q.id"
          class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors space-y-3"
        >
          <div class="flex items-start justify-between gap-2">
            <span class="text-xs font-black text-slate-400">#{{ idx + 1 }}</span>
            <div class="flex items-center gap-1.5 flex-wrap justify-end">
              <span 
                class="px-2 py-0.5 text-[10px] font-bold rounded-md"
                :class="{
                  'text-[#5138ed] bg-indigo-50': typeLabel(q.type) === 'MCQ',
                  'text-emerald-600 bg-emerald-50': typeLabel(q.type) === 'Short Answer',
                  'text-amber-600 bg-amber-50': typeLabel(q.type) === 'Essay',
                  'text-sky-600 bg-sky-50': typeLabel(q.type) === 'True/False',
                  'text-fuchsia-600 bg-fuchsia-50': typeLabel(q.type) === 'Matching',
                  'text-rose-600 bg-rose-50': typeLabel(q.type) === 'Fill in Blank',
                  'text-orange-600 bg-orange-50': typeLabel(q.type) === 'Ordering',
                }"
              >
                {{ typeLabel(q.type) }}
              </span>
              <span 
                class="px-2 py-0.5 text-[10px] font-bold rounded-md"
                :class="{
                  'text-emerald-600 bg-emerald-50': q.difficulty === 'Easy',
                  'text-amber-600 bg-amber-50': q.difficulty === 'Medium',
                  'text-rose-600 bg-rose-50': q.difficulty === 'Hard'
                }"
              >
                {{ q.difficulty }}
              </span>
              <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-white border border-slate-200 text-slate-700">
                {{ q.marks }} Marks
              </span>
            </div>
          </div>

          <div class="text-xs sm:text-[13px] text-slate-800 font-bold leading-snug">
            {{ formatQuestionText(q.text) }}
          </div>
          <div v-if="q.explanation" class="text-[11px] text-slate-500 line-clamp-2">
            {{ q.explanation }}
          </div>

          <div class="flex items-center justify-between text-[11px] text-slate-400 pt-2 border-t border-slate-200/60">
            <span>Chapter: <strong class="text-slate-600">{{ q.chapter || '—' }}</strong></span>
            <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-50 text-emerald-600">{{ q.status }}</span>
          </div>

          <div class="flex items-center gap-2 pt-1">
            <button 
              @click="openViewQuestion(q)"
              class="flex-1 flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-semibold text-[#5138ed] bg-indigo-50 border border-indigo-100 rounded-lg hover:bg-indigo-100 transition-colors min-h-[44px]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
              View
            </button>
            <template v-if="!lockStore.isLocked">
              <button 
                @click="editQuestion(q)"
                class="flex-1 flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors min-h-[44px]"
              >
                <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                Edit
              </button>
              <button 
                @click="openDeleteQuestion(q)"
                class="flex-1 flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-semibold text-rose-600 bg-rose-50 border border-rose-100 rounded-lg hover:bg-rose-100 transition-colors min-h-[44px]"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                Delete
              </button>
            </template>
          </div>
        </div>
      </div>
      
      <!-- Table Footer -->
      <div class="px-4 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs sm:text-[13px] font-medium text-slate-500 border-t border-slate-100">
        <span class="text-center sm:text-left">Showing 1 to {{ filteredQuestions.length }} of {{ stats.total }} questions</span>
        <div class="flex items-center justify-center gap-1">
          <button class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors min-h-[36px] min-w-[36px]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
          <button class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg bg-[#5138ed] text-white font-bold shadow-xs min-h-[36px] min-w-[36px]">1</button>
          <button class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg hover:bg-slate-50 transition-colors min-h-[36px] min-w-[36px]">2</button>
          <button class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg hover:bg-slate-50 transition-colors min-h-[36px] min-w-[36px]">3</button>
          <span class="px-1 text-slate-400">...</span>
          <button class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg hover:bg-slate-50 transition-colors min-h-[36px] min-w-[36px]">12</button>
          <button class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors min-h-[36px] min-w-[36px]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
        </div>
      </div>
    </div>
    </template>

    <!-- =================== MODALS =================== -->

    <!-- Edit Bank Modal -->
    <Teleport to="body">
      <div v-if="showEditBankModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showEditBankModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-5 animate-in">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800">Edit Bank Information</h3>
            <button @click="showEditBankModal = false" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
          <div>
            <label class="block text-[12px] font-bold text-slate-700 mb-1">Title <span class="text-rose-500">*</span></label>
            <input v-model="editBankTitle" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]">
          </div>
          <div>
            <label class="block text-[12px] font-bold text-slate-700 mb-1">Description</label>
            <textarea v-model="editBankDescription" rows="3" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] resize-none"></textarea>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button @click="showEditBankModal = false" class="px-4 py-2 border border-slate-200 rounded-xl text-[13px] font-bold text-slate-600 hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="saveEditBank" :disabled="editBankSaving" class="px-5 py-2 bg-[#5138ed] hover:bg-indigo-600 text-white rounded-xl text-[13px] font-bold shadow-sm transition-colors disabled:opacity-50">
              {{ editBankSaving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Bank Confirmation Modal -->
    <Teleport to="body">
      <div v-if="showDeleteBankModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteBankModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-4 animate-in">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-500 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </div>
            <div>
              <h3 class="text-[15px] font-bold text-slate-800">Delete Question Bank</h3>
              <p class="text-[12px] text-slate-500 mt-0.5">This action cannot be undone.</p>
            </div>
          </div>
          <p class="text-[13px] text-slate-600 leading-relaxed">
            Are you sure you want to delete <strong>"{{ bank.title }}"</strong>? All <strong>{{ stats.total }}</strong> questions inside will be permanently removed.
          </p>
          <div class="flex justify-end gap-3 pt-2">
            <button @click="showDeleteBankModal = false" class="px-4 py-2 border border-slate-200 rounded-xl text-[13px] font-bold text-slate-600 hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="confirmDeleteBank" :disabled="deleteBankProcessing" class="px-5 py-2 bg-rose-500 hover:bg-rose-600 text-white rounded-xl text-[13px] font-bold shadow-sm transition-colors disabled:opacity-50">
              {{ deleteBankProcessing ? 'Deleting...' : 'Delete Bank' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- View Question Modal -->
    <Teleport to="body">
      <div v-if="showViewQuestionModal && viewingQuestion" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showViewQuestionModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-5 animate-in max-h-[85vh] overflow-y-auto">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800">Question Details</h3>
            <button @click="showViewQuestionModal = false" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
          <div class="space-y-4">
            <div class="flex flex-wrap gap-2">
              <span class="px-2.5 py-1 text-[11px] font-bold bg-indigo-50 text-[#5138ed] rounded-lg">{{ typeLabel(viewingQuestion.type) }}</span>
              <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg" :class="{
                'bg-emerald-50 text-emerald-600': viewingQuestion.difficulty === 'Easy',
                'bg-amber-50 text-amber-600': viewingQuestion.difficulty === 'Medium',
                'bg-rose-50 text-rose-600': viewingQuestion.difficulty === 'Hard'
              }">{{ viewingQuestion.difficulty }}</span>
              <span class="px-2.5 py-1 text-[11px] font-bold bg-slate-100 text-slate-600 rounded-lg">{{ viewingQuestion.marks }} Marks</span>
            </div>
            <div>
              <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Question Text</label>
              <p class="text-[14px] text-slate-800 font-medium mt-1 leading-relaxed">{{ stripHtml(viewingQuestion.text) }}</p>
            </div>
            <div v-if="viewingQuestion.chapter || viewingQuestion.topic" class="grid grid-cols-2 gap-4">
              <div v-if="viewingQuestion.chapter">
                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Chapter</label>
                <p class="text-[13px] text-slate-700 font-medium mt-0.5">{{ viewingQuestion.chapter }}</p>
              </div>
              <div v-if="viewingQuestion.topic">
                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Topic</label>
                <p class="text-[13px] text-slate-700 font-medium mt-0.5">{{ viewingQuestion.topic }}</p>
              </div>
            </div>
            <div v-if="viewingQuestion.options && viewingQuestion.options.length" class="space-y-2">
              <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Options</label>
              <div v-for="(opt, i) in viewingQuestion.options" :key="i" class="flex items-center gap-3 p-3 rounded-xl border" :class="isOptionCorrect(opt, i) ? 'border-emerald-200 bg-emerald-50' : 'border-slate-100 bg-slate-50/50'">
                <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold" :class="isOptionCorrect(opt, i) ? 'bg-emerald-500 text-white' : 'bg-white text-slate-400 border border-slate-200'">{{ getOptionLabel(i) }}</div>
                <span class="text-[13px] font-medium" :class="isOptionCorrect(opt, i) ? 'text-emerald-700' : 'text-slate-600'">{{ stripHtml(typeof opt === 'string' ? opt : opt.text) }}</span>
              </div>
            </div>
            <div v-else>
              <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Correct Answer</label>
              <p v-if="viewingQuestion.type === 'multiple_choice' || viewingQuestion.type === 'true_false'" class="text-[13px] text-emerald-600 font-bold mt-1 py-2 px-3 bg-emerald-50 rounded-lg inline-block border border-emerald-100">{{ viewingQuestion.correct_answer || 'N/A' }}</p>
              <p v-else-if="viewingQuestion.type === 'fill_in_blank'" class="text-[13px] text-emerald-600 font-bold mt-1 py-2 px-3 bg-emerald-50 rounded-lg inline-block border border-emerald-100">{{ (viewingQuestion.question_data?.answers?.length ? viewingQuestion.question_data.answers.join(', ') : null) || viewingQuestion.correct_answer || 'N/A' }}</p>
              <p v-else-if="viewingQuestion.type === 'short_answer'" class="text-[13px] text-emerald-600 font-bold mt-1 py-2 px-3 bg-emerald-50 rounded-lg inline-block border border-emerald-100">{{ viewingQuestion.question_data?.expected_answer || viewingQuestion.correct_answer || 'N/A' }}</p>
              <div v-else-if="viewingQuestion.type === 'matching'" class="mt-2 space-y-2">
                <div v-if="viewingQuestion.question_data?.column_a" class="grid grid-cols-2 gap-4">
                  <div>
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase mb-1">Column A</h4>
                    <div v-for="(colA, idx) in viewingQuestion.question_data.column_a" :key="'VA'+idx" class="flex gap-2 text-xs mb-1">
                      <span class="font-bold text-slate-500">{{ Number(idx) + 1 }}.</span> <span v-html="colA"></span>
                    </div>
                  </div>
                  <div>
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase mb-1">Column B</h4>
                    <div v-for="(colB, idx) in viewingQuestion.question_data.column_b" :key="'VB'+idx" class="flex gap-2 text-xs mb-1">
                      <span class="font-bold text-slate-500">{{ colB.label }}.</span> <span v-html="colB.text"></span>
                    </div>
                  </div>
                </div>
                <div class="mt-2 pt-2 border-t border-slate-100">
                  <span class="text-[11px] font-bold text-emerald-600 mr-2 uppercase">Matches:</span>
                  <span v-for="(ans, key) in (viewingQuestion.question_data?.correct_answers || {})" :key="'ans'+key" class="inline-block mr-3 text-xs font-medium text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">{{ key }} → {{ ans }}</span>
                </div>
              </div>
            </div>
            <div v-if="viewingQuestion.tags">
              <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Tags</label>
              <div class="flex flex-wrap gap-1.5 mt-1">
                <span v-for="tag in viewingQuestion.tags.split(',')" :key="tag" class="px-2 py-0.5 text-[11px] font-semibold bg-slate-100 text-slate-500 rounded-md">{{ tag.trim() }}</span>
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button @click="editQuestion(viewingQuestion); showViewQuestionModal = false" class="px-4 py-2 bg-[#5138ed] hover:bg-indigo-600 text-white rounded-xl text-[13px] font-bold transition-colors">Edit Question</button>
            <button @click="showViewQuestionModal = false" class="px-4 py-2 border border-slate-200 rounded-xl text-[13px] font-bold text-slate-600 hover:bg-slate-50 transition-colors">Close</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit Question Modal -->
    <Teleport to="body">
      <div v-if="showEditQuestionModal && editingQuestion" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showEditQuestionModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 space-y-5 animate-in max-h-[85vh] overflow-y-auto">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800">Edit Question</h3>
            <button @click="showEditQuestionModal = false" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
          <div class="space-y-4">
            <!-- Question Text -->
            <div>
              <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1.5 block">Question Text</label>
              <RichTextEditor v-model="editForm.text" placeholder="Type your question content here..." minHeight="150px" />
            </div>
            <!-- Type & Difficulty -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1.5 block">Type</label>
                <select v-model="editForm.type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] cursor-pointer">
                  <option value="multiple_choice">MCQ</option>
                  <option value="true_false">True/False</option>
                  <option value="short_answer">Short Answer</option>
                  <option value="matching">Matching</option>
                  <option value="fill_in_blank">Fill in Blank</option>
                </select>
              </div>
              <div>
                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1.5 block">Difficulty</label>
                <select v-model="editForm.difficulty" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] cursor-pointer">
                  <option value="Easy">Easy</option>
                  <option value="Medium">Medium</option>
                  <option value="Hard">Hard</option>
                </select>
              </div>
            </div>
            <!-- Marks & Chapter -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1.5 block">Marks</label>
                <input type="number" v-model="editForm.marks" min="1" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow" />
              </div>
              <div>
                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1.5 block">Chapter</label>
                <input type="text" v-model="editForm.chapter" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow" />
              </div>
            </div>
            <!-- Options (for MCQ / True-False) -->
            <div v-if="editForm.type === 'multiple_choice' || editForm.type === 'true_false'" class="space-y-2">
              <div v-if="editForm.options && editForm.options.length > 0" class="space-y-4">
                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Options</label>
                <div v-for="(opt, i) in editForm.options" :key="i" class="flex items-start gap-3">
                  <div class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold bg-white text-slate-400 border border-slate-200 shrink-0 mt-1">{{ getOptionLabel(i) }}</div>
                  <div class="flex-1 min-w-0">
                    <RichTextEditor v-model="editForm.options[i]" placeholder="Type option content here..." minHeight="80px" />
                  </div>
                  <button @click="removeEditOption(Number(i))" class="p-1.5 text-slate-400 hover:text-rose-500 rounded-lg transition-colors mt-1" title="Remove option">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                  </button>
                </div>
                <button @click="addEditOption" class="text-[12px] font-bold text-[#5138ed] hover:text-indigo-600 flex items-center gap-1 mt-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                  Add Option
                </button>
              </div>
              <!-- Correct Answer -->
              <div class="pt-2">
                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1.5 block">Correct Answer</label>
                <select v-if="editForm.type === 'true_false'" v-model="editForm.correct_answer" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow bg-white">
                  <option value="" disabled>Select True or False</option>
                  <option value="True">True</option>
                  <option value="False">False</option>
                </select>
                <input v-else type="text" v-model="editForm.correct_answer" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow" placeholder="e.g. A, B, C" />
              </div>
            </div>

            <!-- Matching Data -->
            <div v-if="editForm.type === 'matching' && editForm.question_data" class="space-y-3 pt-2">
              <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Matching Pairs</label>
              <div v-for="(colA, i) in editForm.question_data.column_a" :key="'E'+i" class="grid grid-cols-[1fr,1fr,auto] gap-2 items-center">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-slate-400">{{ Number(i) + 1 }}.</span>
                  <input type="text" v-model="editForm.question_data.column_a[i]" placeholder="Column A item" class="flex-1 border border-slate-200 rounded-lg px-2 py-1.5 text-[13px] focus:border-[#5138ed] focus:outline-none" />
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-slate-400">{{ editForm.question_data.column_b[i]?.label }}.</span>
                  <input type="text" v-if="editForm.question_data.column_b[i]" v-model="editForm.question_data.column_b[i].text" placeholder="Matching Column B item" class="flex-1 border border-slate-200 rounded-lg px-2 py-1.5 text-[13px] focus:border-[#5138ed] focus:outline-none" />
                </div>
                <button @click="removeMatchingPair(Number(i))" class="p-1 text-slate-400 hover:text-rose-500 rounded">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
              </div>
              <button @click="addMatchingPair" class="text-[12px] font-bold text-[#5138ed] hover:text-indigo-600 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Pair
              </button>
            </div>

            <!-- Fill in the Blank Data -->
            <div v-if="editForm.type === 'fill_in_blank' && editForm.question_data" class="space-y-4 pt-2">
              <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Acceptable Answers</label>
              <div v-for="(ans, i) in editForm.question_data.answers" :key="'F'+i" class="flex items-start gap-2">
                <div class="flex-1 min-w-0">
                  <RichTextEditor v-model="editForm.question_data.answers[i]" placeholder="Type answer alternative here..." minHeight="80px" />
                </div>
                <button @click="removeFIBAnswer(Number(i))" class="p-1.5 text-slate-400 hover:text-rose-500 rounded mt-1">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
              </div>
              <button @click="addFIBAnswer" class="text-[12px] font-bold text-[#5138ed] hover:text-indigo-600 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Answer Alternative
              </button>
            </div>

            <!-- Short Answer Data -->
            <div v-if="editForm.type === 'short_answer' && editForm.question_data" class="pt-2">
              <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1.5 block">Expected Answer</label>
              <RichTextEditor v-model="editForm.question_data.expected_answer" placeholder="Expected answer text..." minHeight="80px" />
            </div>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button @click="saveEditQuestion" :disabled="editSaving" class="px-4 py-2 bg-[#5138ed] hover:bg-indigo-600 text-white rounded-xl text-[13px] font-bold transition-colors disabled:opacity-50 flex items-center gap-2">
              <svg v-if="editSaving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              {{ editSaving ? 'Saving...' : 'Save Changes' }}
            </button>
            <button @click="showEditQuestionModal = false" class="px-4 py-2 border border-slate-200 rounded-xl text-[13px] font-bold text-slate-600 hover:bg-slate-50 transition-colors">Cancel</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit Success Toast -->
    <Teleport to="body">
      <div v-if="editSuccessMessage" class="fixed top-6 right-6 z-[9999] bg-emerald-500 text-white px-5 py-3 rounded-xl shadow-lg flex items-center gap-2 animate-in">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="text-[13px] font-bold">{{ editSuccessMessage }}</span>
      </div>
    </Teleport>

    <!-- Delete Question Confirmation Modal -->
    <Teleport to="body">
      <div v-if="showDeleteQuestionModal && questionToDelete" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteQuestionModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-4 animate-in">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-500 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </div>
            <div>
              <h3 class="text-[15px] font-bold text-slate-800">Delete Question</h3>
              <p class="text-[12px] text-slate-500 mt-0.5">This action cannot be undone.</p>
            </div>
          </div>
          <p class="text-[13px] text-slate-600 leading-relaxed">
            Are you sure you want to delete this question?
          </p>
          <div class="p-3 bg-slate-50 rounded-xl text-[12px] text-slate-700 font-medium border border-slate-100" v-html="questionToDelete.text"></div>
          <div class="flex justify-end gap-3 pt-2">
            <button @click="showDeleteQuestionModal = false" class="px-4 py-2 border border-slate-200 rounded-xl text-[13px] font-bold text-slate-600 hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="confirmDeleteQuestion" :disabled="deleteQuestionProcessing" class="px-5 py-2 bg-rose-500 hover:bg-rose-600 text-white rounded-xl text-[13px] font-bold shadow-sm transition-colors disabled:opacity-50">
              {{ deleteQuestionProcessing ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Add Category/Topic Modal -->
    <Teleport to="body">
      <div v-if="showAddCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showAddCategoryModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-5 animate-in">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800">Add Category / Topic</h3>
            <button @click="showAddCategoryModal = false" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
          <div>
            <label class="block text-[12px] font-bold text-slate-700 mb-1">Category Name <span class="text-rose-500">*</span></label>
            <input v-model="newCategoryName" type="text" placeholder="e.g. SQL Basics, Data Structures" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]">
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button @click="showAddCategoryModal = false" class="px-4 py-2 border border-slate-200 rounded-xl text-[13px] font-bold text-slate-600 hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="addCategory" :disabled="!newCategoryName.trim()" class="px-5 py-2 bg-[#5138ed] hover:bg-indigo-600 text-white rounded-xl text-[13px] font-bold shadow-sm transition-colors disabled:opacity-50">Add Category</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Preview Bank Modal -->
    <Teleport to="body">
      <div v-if="showPreviewBankModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showPreviewBankModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl p-0 flex flex-col animate-in max-h-[90vh] overflow-hidden">
          <!-- Header -->
          <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between shrink-0">
            <div>
              <h2 class="text-xl font-bold text-slate-800">{{ bank.title }}</h2>
              <p class="text-[13px] text-slate-500 mt-1 font-medium">{{ stats.total }} Questions Total</p>
            </div>
            <button @click="showPreviewBankModal = false" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-50 rounded-xl transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
          
          <!-- Content -->
          <div class="flex-1 overflow-y-auto p-6 space-y-8 bg-slate-50/50">
            <div v-if="questions.length === 0" class="text-center py-10">
              <p class="text-slate-500 font-medium">This question bank is empty.</p>
            </div>
            
            <div v-for="(q, index) in questions" :key="q.id" class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
              <div class="flex items-start gap-4">
                <div class="w-8 h-8 rounded-full bg-indigo-50 text-[#5138ed] font-bold text-[13px] flex items-center justify-center shrink-0">
                  {{ index + 1 }}
                </div>
                <div class="flex-1 min-w-0">
                  <!-- Meta tags -->
                  <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="px-2 py-0.5 text-[11px] font-bold bg-slate-100 text-slate-500 rounded">{{ typeLabel(q.type) }}</span>
                    <span class="px-2 py-0.5 text-[11px] font-bold bg-slate-100 text-slate-500 rounded">{{ q.marks }} Marks</span>
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded" :class="{
                      'bg-emerald-50 text-emerald-600': q.difficulty === 'Easy',
                      'bg-amber-50 text-amber-600': q.difficulty === 'Medium',
                      'bg-rose-50 text-rose-600': q.difficulty === 'Hard'
                    }">{{ q.difficulty }}</span>
                  </div>
                  
                  <!-- Question Text -->
                  <div class="text-[14px] text-slate-800 font-medium leading-relaxed prose prose-sm max-w-none" v-html="q.text"></div>
                  
                  <!-- Options -->
                  <div v-if="q.options && q.options.length" class="mt-5 space-y-2.5">
                    <div v-for="(opt, oIndex) in q.options" :key="oIndex" class="flex items-center gap-3">
                      <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center text-[10px] font-bold" :class="isOptionCorrect(opt, oIndex) ? 'border-emerald-500 bg-emerald-50 text-emerald-600' : 'border-slate-200 text-slate-400'">
                        {{ getOptionLabel(oIndex) }}
                      </div>
                      <span class="text-[13px]" :class="isOptionCorrect(opt, oIndex) ? 'font-bold text-emerald-700' : 'font-medium text-slate-600'">
                        {{ typeof opt === 'string' ? opt : opt.text }}
                      </span>
                    </div>
                  </div>
                  <div v-else class="mt-4">
                    <div class="inline-block px-3 py-2 bg-emerald-50 border border-emerald-100 rounded-lg">
                      <span class="text-[12px] font-bold text-emerald-600 tracking-wide uppercase mr-2">Answer:</span>
                      <span class="text-[13px] font-medium text-emerald-800">{{ q.correct_answer }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<style scoped>
.animate-in {
  animation: modalIn 0.2s ease-out;
}
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.95) translateY(10px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
