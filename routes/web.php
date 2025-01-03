<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('auth.register');
});
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/chatui', function () {
        return view('chatui');
    });
    Route::get('/conversations', [App\Http\Controllers\ChatController::class, 'getConversation'])->name('conversations');
    Route::post('/chat', [App\Http\Controllers\ChatController::class, 'handleChat'])->name('handle.chat');
    Route::get('/stream-chat', [App\Http\Controllers\ChatController::class, 'streamChat'])->name('stream.chat');
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
