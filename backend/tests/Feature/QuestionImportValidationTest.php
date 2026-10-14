<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use App\Services\QuestionValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class QuestionImportValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;
    protected QuestionValidationService $validatorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validatorService = new QuestionValidationService();

        $this->instructor = User::factory()->create([
            'role'        => 'instructor',
            'course_code' => 'CS101',
            'course_name' => 'Computer Science',
            'semester'    => 'First Semester',
            'academic_year' => '2025/2026',
        ]);
    }

    /**
     * Helper to create a temporary Excel file with custom rows.
     */
    protected function createExcelFile(array $rows, string $fileName = 'test_questions.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rIdx => $row) {
            foreach ($row as $cIdx => $val) {
                $sheet->setCellValueByColumnAndRow($cIdx + 1, $rIdx + 1, $val);
            }
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'test_xlsx_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return new UploadedFile($tempPath, $fileName, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * Helper to create a temporary CSV file with custom rows.
     */
    protected function createCsvFile(array $rows, string $fileName = 'test_questions.csv'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_csv_');
        $handle = fopen($tempPath, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return new UploadedFile($tempPath, $fileName, 'text/csv', null, true);
    }

    /**
     * 1. Valid supported Excel file passes validation.
     */
    public function test_valid_excel_file_import_passes_validation(): void
    {
        $rows = [
            ['Type', 'Question Text', 'Marks', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Answer', 'Difficulty'],
            ['multiple_choice', 'What is HTTP?', 5, 'Hypertext Transfer Protocol', 'High Text Program', 'Hyperlink Test', 'Home Text', 'A', 'Easy'],
            ['true_false', 'RAM is volatile memory.', 3, 'True', 'False', '', '', 'True', 'Easy'],
            ['fill_blank', 'HTTPS uses port _____.', 3, '', '', '', '', '443', 'Medium'],
            ['short_answer', 'Define operating system.', 5, '', '', '', '', 'An OS manages hardware and software resources.', 'Hard'],
        ];

        $file = $this->createExcelFile($rows);

        $response = $this->actingAs($this->instructor)
            ->postJson('/api/v1/instructor/exams/validate-import', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.valid_count', 4)
            ->assertJsonPath('data.invalid_count', 0);
    }

    /**
     * 2. Valid supported CSV file passes validation.
     */
    public function test_valid_csv_file_import_passes_validation(): void
    {
        $rows = [
            ['Type', 'Question Text', 'Marks', 'Option A', 'Option B', 'Correct Answer'],
            ['multiple_choice', 'Is binary base 2?', 5, 'Yes', 'No', 'A'],
            ['true_false', 'Python is an interpreted language.', 2, 'True', 'False', 'True'],
        ];

        $file = $this->createCsvFile($rows);

        $response = $this->actingAs($this->instructor)
            ->postJson('/api/v1/instructor/exams/validate-import', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.valid_count', 2)
            ->assertJsonPath('data.invalid_count', 0);
    }

    /**
     * 3. Valid JSON file passes validation.
     */
    public function test_valid_json_file_import_passes_validation(): void
    {
        $jsonContent = json_encode([
            'questions' => [
                [
                    'type'           => 'multiple_choice',
                    'text'           => 'What is the capital of Ethiopia?',
                    'marks'          => 5,
                    'options'        => ['Addis Ababa', 'Gondar', 'Hawassa', 'Dire Dawa'],
                    'correct_answer' => 'A',
                ],
                [
                    'type'           => 'true_false',
                    'text'           => 'CPU stands for Central Processing Unit.',
                    'marks'          => 2,
                    'correct_answer' => 'True',
                ],
            ]
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_json_');
        file_put_contents($tempPath, $jsonContent);
        $file = new UploadedFile($tempPath, 'questions.json', 'application/json', null, true);

        $response = $this->actingAs($this->instructor)
            ->postJson('/api/v1/instructor/exams/validate-import', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.valid_count', 2)
            ->assertJsonPath('data.invalid_count', 0);
    }

    /**
     * 4. A PDF renamed with an .xlsx extension is rejected.
     */
    public function test_renamed_pdf_file_is_rejected(): void
    {
        $pdfContent = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF";
        $tempPath = tempnam(sys_get_temp_dir(), 'fake_pdf_');
        file_put_contents($tempPath, $pdfContent);
        $file = new UploadedFile($tempPath, 'renamed_pdf.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->instructor)
            ->postJson('/api/v1/instructor/exams/validate-import', [
                'file' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Invalid file format: A PDF document cannot be uploaded as an Excel spreadsheet.'
            ]);
    }

    /**
     * 5. A corrupted Excel file is rejected.
     */
    public function test_corrupted_excel_file_is_rejected(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'corrupt_');
        file_put_contents($tempPath, 'NOT_AN_EXCEL_FILE_RANDOM_CORRUPT_BYTES_12345');
        $file = new UploadedFile($tempPath, 'corrupted.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->instructor)
            ->postJson('/api/v1/instructor/exams/validate-import', [
                'file' => $file,
            ]);

        $response->assertStatus(422);
    }

    /**
     * 6. An empty file is rejected.
     */
    public function test_empty_file_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('empty.xlsx', 0);

        $response = $this->actingAs($this->instructor)
            ->postJson('/api/v1/instructor/exams/validate-import', [
                'file' => $file,
            ]);

        $response->assertStatus(422);
    }

    /**
     * 7. A file with missing required headers (such as an instructor export) is rejected.
     */
    public function test_file_with_missing_required_headers_is_rejected(): void
    {
        // Simulating the user's uploaded instructor spreadsheet: computer_scince_Instructors_2026-10-02.xlsx
        $rows = [
            ['Name', 'Email', 'Department', 'Course Code', 'Role', 'Status'],
            ['Abebe Bikila', 'abebe@wollo.edu.et', 'Computer Science', 'CS101', 'Instructor', 'Active'],
            ['Almaz Ayana', 'almaz@wollo.edu.et', 'Computer Science', 'CS102', 'Instructor', 'Active'],
        ];

        $file = $this->createExcelFile($rows, 'computer_scince_Instructors_2026-10-02.xlsx');

        $response = $this->actingAs($this->instructor)
            ->postJson('/api/v1/instructor/exams/validate-import', [
                'file' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.file.0', fn($v) => str_contains($v, 'Missing required question headers'));
    }

    /**
     * 8. A question with empty question text is marked as invalid.
     */
    public function test_question_with_empty_text_is_invalid(): void
    {
        $res = $this->validatorService->validateQuestion([
            'type'           => 'multiple_choice',
            'text'           => '',
            'marks'          => 5,
            'options'        => ['Option 1', 'Option 2'],
            'correct_answer' => 'A'
        ]);

        $this->assertFalse($res['valid']);
        $this->assertContains('Question text is required.', $res['errors']);
    }

    /**
     * 9. An unsupported question type is rejected.
     */
    public function test_unsupported_question_type_is_invalid(): void
    {
        $res = $this->validatorService->validateQuestion([
            'type'           => 'quantum_physics_drag_drop',
            'text'           => 'What is quantum entanglement?',
            'marks'          => 5,
            'correct_answer' => 'Unknown'
        ]);

        $this->assertFalse($res['valid']);
        $this->assertTrue(collect($res['errors'])->some(fn($e) => str_contains($e, 'Unsupported question type')));
    }

    /**
     * 10. Multiple choice question with missing options is marked as invalid.
     */
    public function test_multiple_choice_with_missing_options_is_invalid(): void
    {
        $res = $this->validatorService->validateQuestion([
            'type'           => 'multiple_choice',
            'text'           => 'Select the true statement',
            'marks'          => 5,
            'options'        => ['Only One Option Here'],
            'correct_answer' => 'A'
        ]);

        $this->assertFalse($res['valid']);
        $this->assertTrue(collect($res['errors'])->some(fn($e) => str_contains($e, 'at least 2 non-empty options')));
    }

    /**
     * 11. Multiple choice question with correct answer not matching any option is marked as invalid.
     */
    public function test_multiple_choice_with_mismatched_correct_answer_is_invalid(): void
    {
        $res = $this->validatorService->validateQuestion([
            'type'           => 'multiple_choice',
            'text'           => 'Which of the following is correct?',
            'marks'          => 5,
            'options'        => ['First Choice', 'Second Choice'],
            'correct_answer' => 'Z' // Only A and B exist
        ]);

        $this->assertFalse($res['valid']);
        $this->assertTrue(collect($res['errors'])->some(fn($e) => str_contains($e, 'does not match any available option')));
    }

    /**
     * 12. Question with missing or invalid marks is marked as invalid.
     */
    public function test_missing_or_invalid_marks_is_invalid(): void
    {
        $res = $this->validatorService->validateQuestion([
            'type'           => 'true_false',
            'text'           => 'A byte is 8 bits.',
            'marks'          => -10, // negative marks
            'correct_answer' => 'True'
        ]);

        $this->assertFalse($res['valid']);
        $this->assertContains('Marks must be a valid positive number greater than 0.', $res['errors']);
    }

    /**
     * 13. File containing both valid and invalid questions properly reports valid vs invalid counts.
     */
    public function test_file_containing_mixed_valid_and_invalid_questions(): void
    {
        $rows = [
            ['Type', 'Question Text', 'Marks', 'Option A', 'Option B', 'Correct Answer'],
            ['multiple_choice', 'Valid question 1', 5, 'Yes', 'No', 'A'], // Valid
            ['multiple_choice', '', 5, 'Yes', 'No', 'A'], // Invalid: missing text
            ['true_false', 'Valid question 2', 2, 'True', 'False', 'True'], // Valid
            ['multiple_choice', 'Invalid MCQ', 5, 'Only One', '', 'A'], // Invalid: < 2 options
        ];

        $file = $this->createExcelFile($rows);

        $response = $this->actingAs($this->instructor)
            ->postJson('/api/v1/instructor/exams/validate-import', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.total_rows', 4)
            ->assertJsonPath('data.valid_count', 2)
            ->assertJsonPath('data.invalid_count', 2);
    }

    /**
     * 14. Server-side validation on POST /exams rejects invalid question payloads and rolls back.
     */
    public function test_backend_store_exam_rejects_invalid_questions_payload(): void
    {
        $payload = [
            'title'            => 'Test Mid Exam',
            'course_code'      => 'CS101',
            'course_name'      => 'Computer Science',
            'duration_minutes' => 60,
            'total_marks'      => 10,
            'status'           => 'draft',
            'questions'        => [
                [
                    'type'           => 'multiple_choice',
                    'text'           => 'Valid question',
                    'marks'          => 5,
                    'options'        => ['Option A', 'Option B'],
                    'correct_answer' => 'A'
                ],
                [
                    'type'           => 'invalid_type_xyz', // Invalid type
                    'text'           => 'Second question',
                    'marks'          => 5,
                    'correct_answer' => 'A'
                ]
            ]
        ];

        $response = $this->actingAs($this->instructor)
            ->postJson('/api/v1/instructor/exams', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'One or more questions failed validation against the question format schema.'
            ]);

        // Assert database has NOT saved the exam or questions (Transaction rollback verification)
        $this->assertDatabaseMissing('exams', ['title' => 'Test Mid Exam']);
        $this->assertDatabaseMissing('questions', ['text' => 'Valid question']);
    }

    /**
     * 15. Unauthenticated user cannot access import validation endpoint.
     */
    public function test_unauthenticated_user_cannot_access_import_validation(): void
    {
        $file = $this->createCsvFile([['Type', 'Question Text', 'Marks', 'Correct Answer']]);

        $response = $this->postJson('/api/v1/instructor/exams/validate-import', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    /**
     * 16. Successful exam creation saves valid questions correctly.
     */
    public function test_successful_exam_creation_saves_validated_questions(): void
    {
        $payload = [
            'title'            => 'CS101 Final Exam',
            'course_code'      => 'CS101',
            'course_name'      => 'Computer Science',
            'duration_minutes' => 120,
            'total_marks'      => 8,
            'status'           => 'draft',
            'questions'        => [
                [
                    'type'           => 'multiple_choice',
                    'text'           => 'What is the base of hexadecimal numbers?',
                    'marks'          => 5,
                    'options'        => ['8', '10', '16', '2'],
                    'correct_answer' => 'C',
                    'difficulty'     => 'Medium',
                ],
                [
                    'type'           => 'true_false',
                    'text'           => 'A bit can be 0 or 1.',
                    'marks'          => 3,
                    'correct_answer' => 'True',
                    'difficulty'     => 'Easy',
                ]
            ]
        ];

        $response = $this->actingAs($this->instructor)
            ->postJson('/api/v1/instructor/exams', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('exams', ['title' => 'CS101 Final Exam']);
        $this->assertDatabaseHas('questions', ['text' => 'What is the base of hexadecimal numbers?', 'correct_answer' => 'C']);
        $this->assertDatabaseHas('questions', ['text' => 'A bit can be 0 or 1.', 'correct_answer' => 'A']);
    }

    /**
     * 17. Matching questions with matching_pairs and correct_answer pass validation.
     */
    public function test_matching_questions_in_csv_pass_validation(): void
    {
        $rows = [
            ['question_type', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'matching_pairs', 'marks', 'instructor_instruction', 'difficulty'],
            [
                'matching',
                'Match each database term in Column A with its correct definition in Column B.',
                '', '', '', '',
                '1-B; 2-D; 3-A; 4-C',
                '1:Primary Key->A:An attribute that may contain duplicate values; B:A field that uniquely identifies a record; 2:Foreign Key->C:A command used to sort query results; D:A field that references a key in another table; 3:Query->A:A request to retrieve or manipulate data; 4:Sort->C:Arrange records in a specified order',
                4,
                'Match every term in Column A with one definition in Column B.',
                'Medium'
            ]
        ];

        $file = $this->createCsvFile($rows, 'matching_exam.csv');

        $response = $this->actingAs($this->instructor)
            ->postJson('/api/v1/instructor/exams/validate-import', [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.valid_count', 1)
            ->assertJsonPath('data.invalid_count', 0);

        $questionData = $response->json('data.questions.0.question');
        $this->assertEquals('matching', $questionData['type']);
        $this->assertCount(4, $questionData['column_a']);
        $this->assertCount(4, $questionData['column_b']);
        $this->assertEquals('Primary Key', $questionData['column_a'][0]);
        $this->assertEquals('Foreign Key', $questionData['column_a'][1]);
        $this->assertEquals('Query', $questionData['column_a'][2]);
        $this->assertEquals('Sort', $questionData['column_a'][3]);
    }
}
