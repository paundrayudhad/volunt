<?php

use App\Http\Controllers\Admin\LogController as AdminLogController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\OrganizationRequestController as AdminOrganizationRequestController;
use App\Http\Controllers\OrganizationRequestController;
use App\Http\Controllers\Organizer\DivisionController;
use App\Http\Controllers\Organizer\EventController;
use App\Http\Controllers\Organizer\InvitationController;
use App\Http\Controllers\Organizer\MemberController;
use App\Http\Controllers\Organizer\OrganizationController;
use App\Http\Controllers\Organizer\RoleController;
use App\Http\Controllers\Organizer\ShiftController;
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

        Route::prefix('events')->name('events.')->group(function () {
            Route::get('/', [EventController::class, 'index'])->name('index');
            Route::get('create', [EventController::class, 'create'])->name('create');
            Route::post('/', [EventController::class, 'store'])->name('store');
            Route::prefix('{event}')->group(function () {
                Route::get('/', [EventController::class, 'show'])->name('show');
                Route::get('edit', [EventController::class, 'edit'])->name('edit');
                Route::patch('/', [EventController::class, 'update'])->name('update');
                Route::delete('/', [EventController::class, 'destroy'])->name('destroy');
                Route::post('transition', [EventController::class, 'transition'])
                    ->middleware('password.confirm')
                    ->name('transition');

                Route::prefix('divisions')->name('divisions.')->group(function () {
                    Route::get('/', [DivisionController::class, 'index'])->name('index');
                    Route::get('create', [DivisionController::class, 'create'])->name('create');
                    Route::post('/', [DivisionController::class, 'store'])->name('store');
                    Route::prefix('{division}')->group(function () {
                        Route::get('/', [DivisionController::class, 'show'])->name('show');
                        Route::get('edit', [DivisionController::class, 'edit'])->name('edit');
                        Route::patch('/', [DivisionController::class, 'update'])->name('update');
                        Route::delete('/', [DivisionController::class, 'destroy'])->name('destroy');
                    });
                });

                Route::prefix('roles')->name('roles.')->group(function () {
                    Route::get('/', [RoleController::class, 'index'])->name('index');
                    Route::get('create', [RoleController::class, 'create'])->name('create');
                    Route::post('/', [RoleController::class, 'store'])->name('store');
                    Route::prefix('{role}')->group(function () {
                        Route::get('/', [RoleController::class, 'show'])->name('show');
                        Route::get('edit', [RoleController::class, 'edit'])->name('edit');
                        Route::patch('/', [RoleController::class, 'update'])->name('update');
                        Route::delete('/', [RoleController::class, 'destroy'])->name('destroy');
                    });
                });

                Route::prefix('shifts')->name('shifts.')->group(function () {
                    Route::get('/', [ShiftController::class, 'index'])->name('index');
                    Route::get('create', [ShiftController::class, 'create'])->name('create');
                    Route::post('/', [ShiftController::class, 'store'])->name('store');
                    Route::prefix('{shift}')->group(function () {
                        Route::get('/', [ShiftController::class, 'show'])->name('show');
                        Route::get('edit', [ShiftController::class, 'edit'])->name('edit');
                        Route::patch('/', [ShiftController::class, 'update'])->name('update');
                        Route::delete('/', [ShiftController::class, 'destroy'])->name('destroy');
                    });
                });
            });
        });
    });
});

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('requests', [AdminOrganizationRequestController::class, 'index'])->name('requests.index');
    Route::post('requests/{organizationRequest}/approve', [AdminOrganizationRequestController::class, 'approve'])
        ->middleware('password.confirm')
        ->name('requests.approve');
    Route::post('requests/{organizationRequest}/reject', [AdminOrganizationRequestController::class, 'reject'])
        ->name('requests.reject');

    Route::get('organizations', [AdminOrganizationController::class, 'index'])->name('organizations.index');
    Route::post('organizations/{org}/suspend', [AdminOrganizationController::class, 'suspend'])
        ->middleware('password.confirm')
        ->name('organizations.suspend');
    Route::post('organizations/{org}/archive', [AdminOrganizationController::class, 'archive'])
        ->middleware('password.confirm')
        ->name('organizations.archive');
    Route::post('organizations/{org}/activate', [AdminOrganizationController::class, 'activate'])
        ->name('organizations.activate');

    Route::get('logs', [AdminLogController::class, 'index'])->name('logs.index');
});

require __DIR__.'/auth.php';
