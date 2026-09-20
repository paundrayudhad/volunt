<?php

use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\LogController as AdminLogController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\OrganizationRequestController as AdminOrganizationRequestController;
use App\Http\Controllers\Admin\RegistrationController as AdminRegistrationController;
use App\Http\Controllers\OrganizationRequestController;
use App\Http\Controllers\Organizer\AnnouncementController as OrganizerAnnouncementController;
use App\Http\Controllers\Organizer\AssignmentController as OrganizerAssignmentController;
use App\Http\Controllers\Organizer\AttendanceController as OrganizerAttendanceController;
use App\Http\Controllers\Organizer\CertificateController as OrganizerCertificateController;
use App\Http\Controllers\Organizer\CustomFieldController;
use App\Http\Controllers\Organizer\DivisionController;
use App\Http\Controllers\Organizer\EventController;
use App\Http\Controllers\Organizer\InvitationController;
use App\Http\Controllers\Organizer\MemberController;
use App\Http\Controllers\Organizer\OrganizationController;
use App\Http\Controllers\Organizer\RegistrationController as OrganizerRegistrationController;
use App\Http\Controllers\Organizer\RoleController;
use App\Http\Controllers\Organizer\ShiftController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\Volunteer\AnnouncementController as VolunteerAnnouncementController;
use App\Http\Controllers\Volunteer\AttendanceController as VolunteerAttendanceController;
use App\Http\Controllers\Volunteer\NotificationController as VolunteerNotificationController;
use App\Http\Controllers\Volunteer\ScheduleController as VolunteerScheduleController;
use App\Http\Controllers\VolunteerProfileController;
use App\Http\Controllers\VolunteerRegistrationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('events', [PublicEventController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('events.index');
Route::get('events/{eventPublic}', [PublicEventController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('events.show');

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

    Route::get('registrations', [VolunteerRegistrationController::class, 'index'])
        ->name('registrations.index');
    Route::get('registrations/{registrationVol}', [VolunteerRegistrationController::class, 'show'])
        ->name('registrations.show');
    Route::get('events/{eventPublic}/register', [VolunteerRegistrationController::class, 'create'])
        ->name('registrations.create');
    Route::post('events/{eventPublic}/register', [VolunteerRegistrationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('registrations.store');
    Route::post('registrations/{registrationVol}/withdraw', [VolunteerRegistrationController::class, 'withdraw'])
        ->name('registrations.withdraw');

    Route::get('my/assignments/{assignmentVol}/qr', [VolunteerAttendanceController::class, 'show'])
        ->name('my.qr.show');
    Route::post('my/assignments/{assignmentVol}/qr/rotate', [VolunteerAttendanceController::class, 'rotate'])
        ->middleware('throttle:10,1')
        ->name('my.qr.rotate');
    Route::get('my/schedule', [VolunteerScheduleController::class, 'index'])
        ->name('my.schedule');

    Route::get('profile/volunteer', [VolunteerProfileController::class, 'edit'])
        ->name('profile.volunteer.edit');
    Route::patch('profile/volunteer', [VolunteerProfileController::class, 'update'])
        ->name('profile.volunteer.update');

    Route::get('announcements', [VolunteerAnnouncementController::class, 'index'])
        ->name('announcements.index');
    Route::get('notifications', [VolunteerNotificationController::class, 'index'])
        ->name('notifications.index');
    Route::post('notifications/{id}/read', [VolunteerNotificationController::class, 'read'])
        ->name('notifications.read');

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

                Route::prefix('fields')->name('fields.')->group(function () {
                    Route::get('/', [CustomFieldController::class, 'index'])->name('index');
                    Route::get('create', [CustomFieldController::class, 'create'])->name('create');
                    Route::post('/', [CustomFieldController::class, 'store'])->name('store');
                    Route::prefix('{field}')->group(function () {
                        Route::get('/', [CustomFieldController::class, 'show'])->name('show');
                        Route::get('edit', [CustomFieldController::class, 'edit'])->name('edit');
                        Route::patch('/', [CustomFieldController::class, 'update'])->name('update');
                        Route::delete('/', [CustomFieldController::class, 'destroy'])->name('destroy');
                    });
                });

                Route::prefix('registrations')->name('registrations.')->group(function () {
                    Route::get('/', [OrganizerRegistrationController::class, 'index'])->name('index');
                    Route::post('bulk', [OrganizerRegistrationController::class, 'bulkReview'])
                        ->middleware(['password.confirm', 'throttle:10,1'])
                        ->name('bulk');
                    Route::prefix('{registration}')->group(function () {
                        Route::get('/', [OrganizerRegistrationController::class, 'show'])->name('show');
                        Route::post('review', [OrganizerRegistrationController::class, 'review'])
                            ->middleware('password.confirm')
                            ->name('review');
                    });
                });

                Route::prefix('assignments')->name('assignments.')->group(function () {
                    Route::get('/', [OrganizerAssignmentController::class, 'index'])->name('index');
                    Route::post('assign', [OrganizerAssignmentController::class, 'assign'])
                        ->middleware(['password.confirm', 'throttle:10,1'])
                        ->name('assign');
                    Route::post('bulk', [OrganizerAssignmentController::class, 'bulkAssign'])
                        ->middleware(['password.confirm', 'throttle:10,1'])
                        ->name('bulk');
                    Route::prefix('{assignment}')->group(function () {
                        Route::get('/', [OrganizerAssignmentController::class, 'show'])->name('show');
                        Route::post('reassign', [OrganizerAssignmentController::class, 'reassign'])->name('reassign');
                        Route::post('confirm', [OrganizerAssignmentController::class, 'confirm'])->name('confirm');
                        Route::post('cancel', [OrganizerAssignmentController::class, 'cancel'])->name('cancel');
                    });
                });

                Route::prefix('attendances')->name('attendances.')->group(function () {
                    Route::get('/', [OrganizerAttendanceController::class, 'index'])->name('index');
                    Route::get('scan', [OrganizerAttendanceController::class, 'scan'])->name('scan');
                    Route::post('scan', [OrganizerAttendanceController::class, 'process'])
                        ->middleware('throttle:30,1')
                        ->name('process');
                    Route::post('manual', [OrganizerAttendanceController::class, 'manual'])
                        ->middleware('throttle:30,1')
                        ->name('manual');
                });

                Route::prefix('announcements')->name('announcements.')->group(function () {
                    Route::get('/', [OrganizerAnnouncementController::class, 'index'])->name('index');
                    Route::get('create', [OrganizerAnnouncementController::class, 'create'])->name('create');
                    Route::post('/', [OrganizerAnnouncementController::class, 'store'])
                        ->middleware('throttle:10,1')
                        ->name('store');
                    Route::prefix('{announcement}')->group(function () {
                        Route::get('/', [OrganizerAnnouncementController::class, 'show'])->name('show');
                        Route::post('publish', [OrganizerAnnouncementController::class, 'publish'])
                            ->middleware('throttle:10,1')
                            ->name('publish');
                    });
                });

                Route::prefix('certificates')->name('certificates.')->group(function () {
                    Route::get('/', [OrganizerCertificateController::class, 'index'])->name('index');
                    Route::post('issue', [OrganizerCertificateController::class, 'issue'])
                        ->middleware(['password.confirm', 'throttle:10,1'])
                        ->name('issue');
                    Route::prefix('{certificate}')->group(function () {
                        Route::get('/', [OrganizerCertificateController::class, 'show'])->name('show');
                        Route::post('revoke', [OrganizerCertificateController::class, 'revoke'])
                            ->middleware('password.confirm')
                            ->name('revoke');
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

    Route::get('events', [AdminEventController::class, 'index'])->name('events.index');
    Route::post('events/{eventAdmin}/cancel', [AdminEventController::class, 'cancel'])
        ->middleware('password.confirm')
        ->name('events.cancel');

    Route::get('registrations', [AdminRegistrationController::class, 'index'])->name('registrations.index');
    Route::get('registrations/{registrationAdmin}', [AdminRegistrationController::class, 'show'])->name('registrations.show');

    Route::get('logs', [AdminLogController::class, 'index'])->name('logs.index');
});

require __DIR__.'/auth.php';
