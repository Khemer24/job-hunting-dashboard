<?php

use App\Http\Controllers\JobDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [JobDashboardController::class, 'index'])->name('jobs.index');
Route::post('/refresh', [JobDashboardController::class, 'refresh'])->name('jobs.refresh');
Route::post('/jobs', [JobDashboardController::class, 'store'])->name('jobs.store');
Route::post('/sources/{source}/checked', [JobDashboardController::class, 'markChecked'])
    ->where('source', 'onlinejobs_ph|jobstreet|linkedin|indeed')
    ->name('sources.checked');
