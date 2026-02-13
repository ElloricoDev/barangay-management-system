<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::query()->pluck('id')->all();
        if (empty($users)) {
            return;
        }

        $residentIds = Resident::query()->pluck('id')->all();
        $certificateIds = Certificate::query()->pluck('id')->all();
        $blotterIds = Blotter::query()->pluck('id')->all();
        $paymentIds = Payment::query()->pluck('id')->all();
        $documentIds = Document::query()->pluck('id')->all();

        $targets = collect([
            ['type' => Resident::class, 'ids' => $residentIds, 'actions' => ['resident.create', 'resident.update']],
            ['type' => Certificate::class, 'ids' => $certificateIds, 'actions' => ['certificate.submit_for_approval', 'certificate.approve', 'certificate.reject']],
            ['type' => Blotter::class, 'ids' => $blotterIds, 'actions' => ['blotter.create', 'blotter.update', 'blotter.approve']],
            ['type' => Payment::class, 'ids' => $paymentIds, 'actions' => ['finance.payment.create', 'finance.payment.update', 'finance.payment.delete']],
            ['type' => Document::class, 'ids' => $documentIds, 'actions' => ['document.upload', 'document.download', 'document.delete']],
        ])->filter(fn ($row) => ! empty($row['ids']))->values();

        if ($targets->isEmpty()) {
            return;
        }

        for ($i = 1; $i <= 120; $i++) {
            $target = $targets->random();
            $action = fake()->randomElement($target['actions']);
            $statusFrom = fake()->randomElement(['submitted', 'ready_for_approval', 'ongoing', 'planned']);
            $statusTo = fake()->randomElement(['approved', 'rejected', 'settled', 'completed']);

            AuditLog::query()->create([
                'user_id' => fake()->randomElement($users),
                'action' => $action,
                'auditable_type' => $target['type'],
                'auditable_id' => fake()->randomElement($target['ids']),
                'before' => [
                    'status' => $statusFrom,
                    'updated_at' => now()->subDays(fake()->numberBetween(2, 20))->toIso8601String(),
                ],
                'after' => [
                    'status' => $statusTo,
                    'updated_at' => now()->subDays(fake()->numberBetween(0, 1))->toIso8601String(),
                ],
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
                'updated_at' => now(),
            ]);
        }
    }
}
