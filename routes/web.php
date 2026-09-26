<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\QueueController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/display', 'display')->name('display');
Route::view('/antrian', 'queue.index')->name('queue.index');

// --- Publik: pasien tanpa login ---
Route::post('/queue/take-number', [QueueController::class, 'takeNumber'])->name('queue.take');
Route::get('/queue/status', [QueueController::class, 'status'])->name('queue.status');
Route::get('/queue/waiting-count', [QueueController::class, 'waitingCount'])->name('queue.waiting-count');

// --- Petugas loket ---
Route::prefix('petugas/loket/{counterId}')->name('counter.')->group(function () {
    Route::get('/', [QueueController::class, 'board'])->name('board');
    Route::post('panggil', [QueueController::class, 'callNext'])->name('call-next');
    Route::post('recall/{queue}', [QueueController::class, 'recall'])->name('recall');
});

// --- Admin: 1 password dari .env, tanpa tabel user ---
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminController::class, 'login'])->name('login');
    Route::post('/login', [AdminController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');

    Route::middleware('admin')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/counters/reseed', [AdminController::class, 'reseedCounters'])->name('counters.reseed');
        Route::post('/counters/{counter}/toggle', [AdminController::class, 'toggleCounter'])->name('counters.toggle');
        Route::post('/queue/reset', [AdminController::class, 'resetQueue'])->name('queue.reset');
    });
});
