<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

it('mendaftarkan user dengan password minimal 12 karakter', function () {
    $pendek = Volt::test('pages.auth.register')
        ->set('name', 'Calon Owner')
        ->set('email', 'calon@example.com')
        ->set('password', 'pendek-7')
        ->set('password_confirmation', 'pendek-7');

    $pendek->call('register');
    $pendek->assertHasErrors('password');

    $panjang = Volt::test('pages.auth.register')
        ->set('name', 'Calon Owner')
        ->set('email', 'calon@example.com')
        ->set('password', 'kata-sandi-12-plus')
        ->set('password_confirmation', 'kata-sandi-12-plus');

    $panjang->call('register');
    $panjang->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'calon@example.com')->exists())->toBeTrue();
});

it('menolak login salah tanpa membocorkan email terdaftar', function () {
    User::factory()->create(['email' => 'nyata@example.com', 'password' => bcrypt('kata-sandi-benar-12')]);

    $terdaftar = Volt::test('pages.auth.login')
        ->set('form.email', 'nyata@example.com')
        ->set('form.password', 'salah-salah-salah');
    $terdaftar->call('login');

    $takTerdaftar = Volt::test('pages.auth.login')
        ->set('form.email', 'tak-ada@example.com')
        ->set('form.password', 'salah-salah-salah');
    $takTerdaftar->call('login');

    $terdaftar->assertHasErrors('form.email');
    $takTerdaftar->assertHasErrors('form.email');

    $errorTerdaftar = $terdaftar->errors()->get('form.email');
    $errorTakTerdaftar = $takTerdaftar->errors()->get('form.email');
    expect($errorTerdaftar[0])->toBe($errorTakTerdaftar[0]);
});

it('mengunci setelah 5x login gagal', function () {
    User::factory()->create(['email' => 'korban@example.com', 'password' => bcrypt('kata-sandi-benar-12')]);

    for ($i = 0; $i < 6; $i++) {
        Volt::test('pages.auth.login')
            ->set('form.email', 'korban@example.com')
            ->set('form.password', 'salah-salah-salah')
            ->call('login');
    }

    $terkunci = Volt::test('pages.auth.login')
        ->set('form.email', 'korban@example.com')
        ->set('form.password', 'kata-sandi-benar-12');
    $terkunci->call('login');
    $terkunci->assertHasErrors('form.email'); // masih terkunci

    // assertion security_logs ditambah di Task 2
});

it('logout menginvalidasi session', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    Volt::test('layout.navigation')->call('logout')->assertRedirect('/');

    $this->assertGuest();
    $this->get('/dashboard')->assertRedirect('/login');
});

it('tautan reset password bisa diminta', function () {
    Notification::fake();

    $user = User::factory()->create();

    Volt::test('pages.auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('password bisa direset dengan token valid', function () {
    Notification::fake();

    $user = User::factory()->create();

    Volt::test('pages.auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $component = Volt::test('pages.auth.reset-password', ['token' => $notification->token])
            ->set('email', $user->email)
            ->set('password', 'kata-sandi-baru-12')
            ->set('password_confirmation', 'kata-sandi-baru-12');

        $component->call('resetPassword');
        $component->assertHasNoErrors()->assertRedirect('/login');

        return true;
    });
});

it('email bisa diverifikasi', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});
