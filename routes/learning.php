<?php

use App\Http\Controllers\Branch\Learning\AssignmentController;
use App\Http\Controllers\Branch\Learning\MaterialController;
use App\Http\Controllers\Branch\Learning\OnlineClassController;
use App\Http\Controllers\Branch\Learning\ProgressController;
use App\Http\Controllers\Branch\Learning\QuizController;
use Illuminate\Support\Facades\Route;

/*
| Learning management screens shared by the back office ("branch.learning.*") and the teacher portal
| ("portal.teacher.learning.*"). Included from routes/branch.php and routes/portal.php inside a prefix + name group.
| Teachers only ever see their own items — enforced in LearningAccess, not here.
*/

Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
Route::get('assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
Route::post('assignments', [AssignmentController::class, 'store'])->name('assignments.store');
Route::get('assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
Route::get('assignments/{assignment}/edit', [AssignmentController::class, 'edit'])->name('assignments.edit');
Route::put('assignments/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
Route::delete('assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
Route::post('assignments/{assignment}/publish', [AssignmentController::class, 'publish'])->name('assignments.publish');
Route::post('assignments/{assignment}/close', [AssignmentController::class, 'close'])->name('assignments.close');
Route::post('assignments/{assignment}/reopen', [AssignmentController::class, 'reopen'])->name('assignments.reopen');
Route::post('assignments/{assignment}/evaluate', [AssignmentController::class, 'evaluate'])->name('assignments.evaluate');
Route::get('assignments/{assignment}/attachment', [AssignmentController::class, 'attachment'])->name('assignments.attachment');
Route::get('assignments/{assignment}/submissions/{submission}/file', [AssignmentController::class, 'submissionFile'])->name('assignments.submission-file');

Route::get('materials', [MaterialController::class, 'index'])->name('materials.index');
Route::get('materials/create', [MaterialController::class, 'create'])->name('materials.create');
Route::post('materials', [MaterialController::class, 'store'])->name('materials.store');
Route::get('materials/{material}/edit', [MaterialController::class, 'edit'])->name('materials.edit');
Route::put('materials/{material}', [MaterialController::class, 'update'])->name('materials.update');
Route::delete('materials/{material}', [MaterialController::class, 'destroy'])->name('materials.destroy');
Route::get('materials/{material}/file', [MaterialController::class, 'file'])->name('materials.file');

Route::get('progress', [ProgressController::class, 'index'])->name('progress.index');
Route::get('progress/students/{student}', [ProgressController::class, 'student'])->name('progress.student');

Route::get('online-classes', [OnlineClassController::class, 'index'])->name('online-classes.index');
Route::get('online-classes/create', [OnlineClassController::class, 'create'])->name('online-classes.create');
Route::post('online-classes', [OnlineClassController::class, 'store'])->name('online-classes.store');
Route::get('online-classes/{onlineClass}', [OnlineClassController::class, 'show'])->name('online-classes.show');
Route::get('online-classes/{onlineClass}/edit', [OnlineClassController::class, 'edit'])->name('online-classes.edit');
Route::put('online-classes/{onlineClass}', [OnlineClassController::class, 'update'])->name('online-classes.update');
Route::post('online-classes/{onlineClass}/cancel', [OnlineClassController::class, 'cancel'])->name('online-classes.cancel');
Route::post('online-classes/{onlineClass}/complete', [OnlineClassController::class, 'complete'])->name('online-classes.complete');
Route::post('online-classes/{onlineClass}/sync', [OnlineClassController::class, 'sync'])->middleware('throttle:20,1')->name('online-classes.sync');
Route::post('online-classes/{onlineClass}/recording', [OnlineClassController::class, 'recording'])->middleware('throttle:20,1')->name('online-classes.recording');
Route::post('online-classes/{onlineClass}/attendance', [OnlineClassController::class, 'attendance'])->name('online-classes.attendance');

Route::get('quizzes', [QuizController::class, 'index'])->name('quizzes.index');
Route::get('quizzes/create', [QuizController::class, 'create'])->name('quizzes.create');
Route::post('quizzes', [QuizController::class, 'store'])->name('quizzes.store');
Route::get('quizzes/{quiz}', [QuizController::class, 'show'])->name('quizzes.show');
Route::get('quizzes/{quiz}/edit', [QuizController::class, 'edit'])->name('quizzes.edit');
Route::put('quizzes/{quiz}', [QuizController::class, 'update'])->name('quizzes.update');
Route::delete('quizzes/{quiz}', [QuizController::class, 'destroy'])->name('quizzes.destroy');
Route::post('quizzes/{quiz}/publish', [QuizController::class, 'publish'])->name('quizzes.publish');
Route::post('quizzes/{quiz}/close', [QuizController::class, 'close'])->name('quizzes.close');
Route::post('quizzes/{quiz}/reopen', [QuizController::class, 'reopen'])->name('quizzes.reopen');
