<?php

use App\Http\Controllers\Branch\Academic\TimetableController;
use App\Http\Controllers\Branch\Attendance\AttendanceCorrectionController;
use App\Http\Controllers\Branch\Exams\MarkController;
use App\Http\Controllers\Branch\Exams\MarkCorrectionController;
use App\Http\Controllers\Branch\Attendance\StudentAttendanceController;
use App\Http\Controllers\Branch\HR\LeaveApplicationController;
use App\Http\Controllers\Portal\LearningPortalController;
use App\Http\Controllers\Portal\LibraryPortalController;
use App\Http\Controllers\Portal\QuizPortalController;
use App\Http\Controllers\Portal\ParentPortalController;
use App\Http\Controllers\Portal\StudentPortalController;
use App\Http\Controllers\Portal\TeacherPortalController;
use Illuminate\Support\Facades\Route;

/*
| Teacher / Student / Parent portals on branch hosts.
| Teacher pages reuse the back-office controllers (SharedWithTeacherPortal) with teacher-only scoping.
*/

Route::middleware(['auth', 'tenant.access'])->group(function () {
    Route::get('/teacher', [TeacherPortalController::class, 'index'])->middleware('portal:teacher')->name('portal.teacher');
    Route::get('/student', [StudentPortalController::class, 'index'])->middleware('portal:student')->name('portal.student');
    Route::get('/parent', [ParentPortalController::class, 'index'])->middleware('portal:parent')->name('portal.parent');
});

Route::middleware(['auth', 'tenant.access', 'portal:teacher'])->prefix('teacher')->name('portal.teacher.')->group(function () {
    Route::get('attendance', [StudentAttendanceController::class, 'index'])->name('attendance.students.index');
    Route::get('attendance/sections/{section}', [StudentAttendanceController::class, 'register'])->name('attendance.students.register');
    Route::post('attendance', [StudentAttendanceController::class, 'store'])->name('attendance.students.store');
    Route::get('attendance/corrections', [AttendanceCorrectionController::class, 'index'])->name('attendance.corrections.index');
    Route::post('attendance/corrections', [AttendanceCorrectionController::class, 'store'])->name('attendance.corrections.store');

    Route::get('timetable', [TimetableController::class, 'teacher'])->name('academics.timetable.teacher');

    Route::get('marks', [MarkController::class, 'index'])->name('exams.marks.index');
    Route::get('marks/corrections', [MarkCorrectionController::class, 'index'])->name('exams.corrections.index');
    Route::post('marks/corrections', [MarkCorrectionController::class, 'store'])->name('exams.corrections.store');
    Route::get('marks/{exam}/{paper}/{section}', [MarkController::class, 'sheet'])->name('exams.marks.sheet');
    Route::post('marks/{exam}/{paper}/{section}', [MarkController::class, 'store'])->name('exams.marks.store');

    Route::prefix('learning')->name('learning.')->group(base_path('routes/learning.php'));

    Route::get('leaves', [LeaveApplicationController::class, 'index'])->name('leaves.index');
    Route::get('leaves/create', [LeaveApplicationController::class, 'create'])->name('leaves.create');
    Route::post('leaves', [LeaveApplicationController::class, 'store'])->name('leaves.store');
    Route::post('leaves/{leave}/cancel', [LeaveApplicationController::class, 'cancel'])->name('leaves.cancel');
});

Route::middleware(['auth', 'tenant.access', 'portal:student'])->prefix('student')->name('portal.student.')->group(function () {
    Route::get('results', [StudentPortalController::class, 'results'])->name('results');
    Route::get('results/{result}', [StudentPortalController::class, 'marksheet'])->name('results.show');
    Route::get('results/{result}/pdf', [StudentPortalController::class, 'marksheetPdf'])->name('results.pdf');
    Route::get('exams/{exam}/admit-card', [StudentPortalController::class, 'admitCard'])->name('admit-card');
    Route::get('fees', [StudentPortalController::class, 'fees'])->name('fees');
    Route::get('fees/invoices/{invoice}', [StudentPortalController::class, 'invoice'])->name('fees.invoice');
    Route::get('fees/receipts/{payment}', [StudentPortalController::class, 'receipt'])->name('fees.receipt');

    Route::get('learning', [LearningPortalController::class, 'studentProgress'])->name('learning');
    Route::get('assignments/{assignment}', [LearningPortalController::class, 'studentAssignment'])->name('assignments.show');
    Route::post('assignments/{assignment}/submit', [LearningPortalController::class, 'studentSubmit'])->middleware('throttle:30,1')->name('assignments.submit');
    Route::get('assignments/{assignment}/attachment', [LearningPortalController::class, 'studentAttachment'])->name('assignments.attachment');
    Route::get('assignments/{assignment}/submission-file', [LearningPortalController::class, 'studentSubmissionFile'])->name('assignments.submission-file');
    Route::get('materials', [LearningPortalController::class, 'studentMaterials'])->name('materials');
    Route::get('online-classes', [LearningPortalController::class, 'studentOnlineClasses'])->name('online-classes');
    Route::get('quizzes', [QuizPortalController::class, 'index'])->name('quizzes');
    Route::get('quizzes/{quiz}', [QuizPortalController::class, 'show'])->name('quizzes.show');
    Route::post('quizzes/{quiz}/start', [QuizPortalController::class, 'start'])->middleware('throttle:20,1')->name('quizzes.start');
    Route::get('quiz-attempts/{attempt}', [QuizPortalController::class, 'attempt'])->name('quizzes.attempt');
    Route::post('quiz-attempts/{attempt}/save', [QuizPortalController::class, 'save'])->middleware('throttle:120,1')->name('quizzes.save');
    Route::post('quiz-attempts/{attempt}/submit', [QuizPortalController::class, 'submit'])->middleware('throttle:20,1')->name('quizzes.submit');
    Route::get('quiz-attempts/{attempt}/result', [QuizPortalController::class, 'result'])->name('quizzes.result');
    Route::get('materials/{material}/file', [LearningPortalController::class, 'studentMaterialFile'])->name('materials.file');

    Route::get('library', [LibraryPortalController::class, 'student'])->name('library');
    Route::post('library/books/{book}/reserve', [LibraryPortalController::class, 'reserve'])->middleware('throttle:20,1')->name('library.reserve');
    Route::post('library/reservations/{reservation}/cancel', [LibraryPortalController::class, 'cancel'])->name('library.cancel');
    Route::get('facilities', [\App\Http\Controllers\Portal\FacilitiesPortalController::class, 'student'])->name('facilities');
});

Route::middleware(['auth', 'tenant.access', 'portal:parent'])->prefix('parent')->name('portal.parent.')->group(function () {
    Route::get('children/{student}/results', [ParentPortalController::class, 'results'])->name('results');
    Route::get('results/{result}', [ParentPortalController::class, 'marksheet'])->name('results.show');
    Route::get('results/{result}/pdf', [ParentPortalController::class, 'marksheetPdf'])->name('results.pdf');
    Route::get('children/{student}/fees', [ParentPortalController::class, 'fees'])->name('fees');
    Route::get('fees/invoices/{invoice}', [ParentPortalController::class, 'invoice'])->name('fees.invoice');
    Route::get('fees/receipts/{payment}', [ParentPortalController::class, 'receipt'])->name('fees.receipt');

    Route::get('children/{student}/learning', [LearningPortalController::class, 'parentProgress'])->name('learning');
    Route::get('children/{student}/assignments/{assignment}', [LearningPortalController::class, 'parentAssignment'])->name('assignments.show');
    Route::get('children/{student}/assignments/{assignment}/attachment', [LearningPortalController::class, 'parentAttachment'])->name('assignments.attachment');
    Route::get('children/{student}/assignments/{assignment}/submission-file', [LearningPortalController::class, 'parentSubmissionFile'])->name('assignments.submission-file');
    Route::get('children/{student}/materials', [LearningPortalController::class, 'parentMaterials'])->name('materials');
    Route::get('children/{student}/online-classes', [LearningPortalController::class, 'parentOnlineClasses'])->name('online-classes');
    Route::get('children/{student}/quizzes', [QuizPortalController::class, 'parentIndex'])->name('quizzes');
    Route::get('children/{student}/materials/{material}/file', [LearningPortalController::class, 'parentMaterialFile'])->name('materials.file');
    Route::get('children/{student}/library', [LibraryPortalController::class, 'parent'])->name('library');
    Route::get('children/{student}/facilities', [\App\Http\Controllers\Portal\FacilitiesPortalController::class, 'parent'])->name('facilities');
});
