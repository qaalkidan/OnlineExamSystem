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
            'Student Name',
            'Student ID',
            'Student Email',
            'Course Code',
            'Course Title',
            'Exam Code',
            'Exam Title',
            'Score Earned',
            'Total Marks',
            'Percentage (%)',
            'Grade',
            'Status',
            'Semester',
            'Academic Year',
            'Submission Date',
        ];
    }

    public function map($row): array
    {
        $this->index++;

        $studentName = $row['student_name'] ?? 'N/A';
        $studentRegNo = $row['student_reg_no'] ?? ($row['student_id_str'] ?? 'N/A');
        $studentEmail = $row['student_email'] ?? 'N/A';
        $courseCode = $row['course_code'] ?? 'N/A';
        $courseName = $row['course_name'] ?? 'N/A';
        $examCode = $row['exam_code'] ?? 'N/A';
        $examTitle = $row['exam_title'] ?? 'N/A';
        $score = $row['score'] !== null ? $row['score'] : 'N/A';
        $totalMarks = $row['total_marks'] ?? 100;
        $percentage = $row['percentage'] !== null ? ($row['percentage'] . '%') : 'N/A';
        $grade = $row['grade'] ?? 'N/A';
        $status = ucfirst($row['status'] ?? 'Pending');
        $semester = $row['semester'] ?? 'Semester 1';
        $academicYear = $row['academic_year'] ?? '2025/2026';
        $submittedAt = $row['submitted_at'] ?? 'N/A';

        return [
            $this->index,
            $studentName,
            $studentRegNo,
            $studentEmail,
            $courseCode,
            $courseName,
            $examCode,
            $examTitle,
            $score,
            $totalMarks,
            $percentage,
            $grade,
            $status,
            $semester,
            $academicYear,
            $submittedAt,
        ];
    }
}
