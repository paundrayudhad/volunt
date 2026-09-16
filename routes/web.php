<?php

use App\Http\Controllers\OrganizationRequestController;
use App\Http\Controllers\Organizer\InvitationController;
use App\Http\Controllers\Organizer\MemberController;
use App\Http\Controllers\Organizer\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('organizations/requests', [OrganizationRequestController::class, 'index'])
        ->name('organizations.requests.index');
    Route::get('organizations/requests/create', [OrganizationRequestController::class, 'create'])
        ->name('organizations.requests.create');
    Route::post('organizations/requests', [OrganizationRequestController::class, 'store'])
        ->middleware('throttle:3,1')
        ->name('organizations.requests.store');

    Route::get('invitations', [InvitationController::class, 'index'])
        ->name('invitations.index');
    Route::post('invitations/{invitation}/accept', [InvitationController::class, 'accept'])
        ->name('invitations.accept');
    Route::post('invitations/{invitation}/decline', [InvitationController::class, 'decline'])
        ->name('invitations.decline');

    Route::prefix('organizer/{organization}')->name('organizer.')->group(function () {
        Route::get('/', [OrganizationController::class, 'show'])->name('show');
        Route::get('edit', [OrganizationController::class, 'edit'])->name('edit');
        Route::patch('/', [OrganizationController::class, 'update'])->name('update');
        Route::post('transfer', [OrganizationController::class, 'transfer'])
            ->middleware('password.confirm')
            ->name('transfer');

        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::get('members/create', [MemberController::class, 'create'])->name('members.create');
        Route::post('members', [MemberController::class, 'store'])->name('members.store');
        Route::patch('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
    });
});

require __DIR__.'/auth.php';
