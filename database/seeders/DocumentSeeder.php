<?php

namespace Database\Seeders;

use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\Document;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $residentIds = Resident::query()->pluck('id')->all();
        $certificateIds = Certificate::query()->pluck('id')->all();
        $blotterIds = Blotter::query()->pluck('id')->all();
        $uploaderIds = User::query()->pluck('id')->all();

        if (empty($uploaderIds)) {
            return;
        }

        $modules = ['resident', 'certificate', 'blotter', 'other'];
        $disk = Storage::disk('public');
        $runKey = now()->format('YmdHis');

        for ($i = 1; $i <= 40; $i++) {
            $module = fake()->randomElement($modules);
            $ext = fake()->randomElement(['txt', 'pdf']);
            $storedName = "seed-{$runKey}-{$i}-".Str::random(8).".{$ext}";
            $path = "documents/{$storedName}";
            $original = "document_{$i}.{$ext}";
            $status = fake()->randomElement(['submitted', 'approved', 'rejected']);
            $reviewedBy = $status === 'submitted' ? null : fake()->randomElement($uploaderIds);
            $reviewedAt = $status === 'submitted' ? null : fake()->dateTimeBetween('-30 days', 'now');
            $rejectionReason = $status === 'rejected' ? fake()->sentence(8) : null;

            $content = "Seeded document {$i}\nModule: {$module}\nGenerated: ".now()->toDateTimeString()."\n";
            $disk->put($path, $content);

            Document::query()->create([
                'resident_id' => $module === 'resident' && ! empty($residentIds) ? fake()->randomElement($residentIds) : fake()->optional(0.4)->randomElement($residentIds ?: [null]),
                'certificate_id' => $module === 'certificate' && ! empty($certificateIds) ? fake()->randomElement($certificateIds) : null,
                'blotter_id' => $module === 'blotter' && ! empty($blotterIds) ? fake()->randomElement($blotterIds) : null,
                'uploaded_by' => fake()->randomElement($uploaderIds),
                'title' => fake()->sentence(3),
                'module' => $module,
                'original_name' => $original,
                'stored_name' => $storedName,
                'disk' => 'public',
                'path' => $path,
                'mime_type' => $ext === 'pdf' ? 'application/pdf' : 'text/plain',
                'file_size' => $disk->size($path) ?? strlen($content),
                'notes' => fake()->optional()->sentence(6),
                'status' => $status,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => $reviewedAt,
                'rejection_reason' => $rejectionReason,
            ]);
        }
    }
}
