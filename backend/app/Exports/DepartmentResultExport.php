<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Collection;

class DepartmentResultExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    protected Collection $results;
    protected int $index = 0;

    public function __construct(Collection $results)
    {
        $this->results = $results;
    }

    public function collection()
    {
        return $this->results;
    }

    public function headings(): array
    {
        return [
            '#',
            'Student ID',
            'Student Name',
            'Course Code',
            'Course Title',
            'Section',
            'Year Level',
            'Semester',
            'Exam Title',
            'Exam Code',
            'Score Earned',
            'Total Marks',
            'Percentage (%)',
            'Grade',
            'Result Status',
            'Submission Date',
        ];
    }

    public function map($row): array
    {
        $this->index++;

        $studentName = $row['student_name'] ?? 'N/A';
        $studentRegNo = $row['student_id'] ?? ($row['student_reg_no'] ?? 'N/A');
        $courseCode = $row['course_code'] ?? 'N/A';
        $courseName = $row['course_name'] ?? 'N/A';
        $section = $row['section'] ?? 'Section A';
        $yearLevel = $row['year_level'] ?? ($row['academic_year'] ?? '1st Year');
        $semester = $row['semester'] ?? 'Semester 1';
        $examTitle = $row['exam_title'] ?? 'N/A';
        $examCode = $row['exam_code'] ?? 'N/A';
        $score = $row['score'] !== null ? $row['score'] : 'N/A';
        $totalMarks = $row['total_marks'] ?? 100;
        $percentage = $row['percentage'] !== null ? ($row['percentage'] . '%') : 'N/A';
        $grade = $row['grade'] ?? 'Pending';
        $status = ucfirst($row['status'] ?? 'Pending');
        $submittedAt = $row['submitted_at'] ?? 'N/A';

        return [
            $this->index,
            $studentRegNo,
            $studentName,
            $courseCode,
            $courseName,
            $section,
            $yearLevel,
            $semester,
            $examTitle,
            $examCode,
            $score,
            $totalMarks,
            $percentage,
            $grade,
            $status,
            $submittedAt,
        ];
    }
}
