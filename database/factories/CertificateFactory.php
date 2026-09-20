<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        $registration = Registration::factory()->create();

        return [
            'event_id' => $registration->event_id,
            'user_id' => $registration->user_id,
            'registration_id' => $registration->id,
            'certificate_no' => 'WV-'.now()->format('Y').'-'.strtoupper(Str::random(6)),
            'issued_at' => now(),
            'qr_token_hash' => hash('sha256', Str::random(64)),
        ];
    }
}
