<?php

use App\Http\Controllers\Api\InsurerCallbackController;
use App\Http\Controllers\Api\PolicyRequestController;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()]));

// Policy Requests
Route::post('/policy-requests', [PolicyRequestController::class, 'store']);
Route::get('/policy-requests/{policyRequest}', [PolicyRequestController::class, 'show']);

// Mock Insurer Callback (reliability scenario)
Route::post('/insurer/callback', [InsurerCallbackController::class, 'handle']);
