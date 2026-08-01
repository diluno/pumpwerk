<?php

use App\Http\Controllers\AiFeedbackController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CardioController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\ProgressionController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SetController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [SessionController::class, 'index'])->name('sessions.index');
    Route::post('/sessions', [SessionController::class, 'store'])->name('sessions.store');
    Route::get('/sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
    Route::patch('/sessions/{session}', [SessionController::class, 'update'])->name('sessions.update');
    Route::post('/sessions/{session}/finish', [SessionController::class, 'finish'])->name('sessions.finish');
    Route::post('/sessions/{session}/reopen', [SessionController::class, 'reopen'])->name('sessions.reopen');
    Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');

    Route::post('/sessions/{session}/exercises', [ExerciseController::class, 'store'])->name('exercises.store');
    Route::patch('/exercises/{exercise}', [ExerciseController::class, 'update'])->name('exercises.update');
    Route::delete('/exercises/{exercise}', [ExerciseController::class, 'destroy'])->name('exercises.destroy');

    Route::post('/exercises/{exercise}/sets', [SetController::class, 'store'])->name('sets.store');
    Route::patch('/sets/{set}', [SetController::class, 'update'])->name('sets.update');
    Route::delete('/sets/{set}', [SetController::class, 'destroy'])->name('sets.destroy');

    Route::post('/sessions/{session}/cardio', [CardioController::class, 'store'])->name('cardio.store');
    Route::patch('/cardio/{cardio}', [CardioController::class, 'update'])->name('cardio.update');
    Route::delete('/cardio/{cardio}', [CardioController::class, 'destroy'])->name('cardio.destroy');

    Route::get('/machines/picker', [MachineController::class, 'picker'])->name('machines.picker');
    Route::get('/machines/{machine}', [MachineController::class, 'history'])->name('machines.history');

    Route::get('/progression', [ProgressionController::class, 'index'])->name('progression');

    Route::post('/sessions/{session}/ai-feedback', [AiFeedbackController::class, 'store'])->name('ai-feedback.store');
    Route::post('/sessions/{session}/plan', [\App\Http\Controllers\PlanController::class, 'store'])->name('plan.store');
});
