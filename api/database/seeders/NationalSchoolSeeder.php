<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the `national_schools` table from a raw HTML <option> dump.
 *
 * Why parse instead of a hard-coded PHP array: the source list (from the
 * national schools dropdown) has ~5,900 entries and occasionally contains
 * duplicate codes (the same school exported twice under the same code).
 * Parsing directly from the raw markup means you can just re-export the
 * dropdown's HTML whenever the official list changes, drop it in
 * database/seeders/data/national_schools_options.html, and re-run this
 * seeder — no manual re-typing, no risk of transcription typos in codes
 * that other tables will reference.
 */
class NationalSchoolSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/national_schools_options.html');

        if (! is_file($path)) {
            $this->command?->warn("National schools data file not found at: {$path}");
            return;
        }

        $raw = file_get_contents($path);

        // Matches: <option value="CODE">Name</option>
        preg_match_all(
            '/<option\s+value="([^"]*)"\s*>([^<]*)<\/option>/iu',
            $raw,
            $matches,
            PREG_SET_ORDER
        );

        $schools = [];

        foreach ($matches as $match) {
            $code = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5));
            $name = trim(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5));

            if ($code === '' || $name === '') {
                continue;
            }

            // Some codes appear twice in the source export (duplicate
            // <option> entries for the same school) — keep the first
            // occurrence and skip later duplicates.
            if (! isset($schools[$code])) {
                $schools[$code] = [
                    'code' => $code,
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (empty($schools)) {
            $this->command?->warn('No <option> entries found in national schools data file.');
            return;
        }

        foreach (array_chunk(array_values($schools), 500) as $chunk) {
            DB::table('national_schools')->upsert(
                $chunk,
                ['code'],
                ['name', 'updated_at']
            );
        }

        $this->command?->info(count($schools) . ' national schools seeded.');
    }
}