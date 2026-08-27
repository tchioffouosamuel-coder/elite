<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Interroge cartescolaire.cm pour retrouver le matricule national d'un élève
 * du secondaire à partir de son nom et du code national de l'établissement
 * — cf. School::estSecondaire() / School::national_school_code.
 */
class MatriculeNationalService
{
    protected string $baseUrl = 'https://cartescolaire.cm';
    protected string $endpoint = 'https://cartescolaire.cm/get-matricule';
    protected string $csrfToken = 'J5vmoibrdizZFFpjuy7oSPc2mVzFcPYOotc0bL2g';

    public function fetchStudents(string $name, string $schoolCode): array
    {
        $cacheKey = 'students_' . Str::slug($name . '_' . $schoolCode);

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($name, $schoolCode) {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Referer' => $this->baseUrl . '/',
            ])->timeout(60)->get($this->endpoint, [
                '_token' => $this->csrfToken,
                'student_name' => $name,
                'school_code' => $schoolCode,
            ]);

            if ($response->failed()) {
                return [];
            }

            return $this->parseHtml($response->body());
        });
    }

    protected function parseHtml(string $html): array
    {
        $crawler = new Crawler($html);
        $students = [];

        $crawler->filter('.result-item')->each(function (Crawler $node) use (&$students) {
            try {
                $students[] = [
                    'etablissement' => $this->safeText($node, '.profile-info .title'),
                    'fullname' => $this->safeText($node, '.profile-info .subtitle'),
                    'classe' => $this->safeText($node, '.student-class'),
                    'date_naissance' => $this->safeText($node, '.student-year'),
                    'sexe' => $this->safeText($node, '.gender p'),
                    'matricule_national' => $this->safeText($node, '.actual-matricule'),
                ];
            } catch (\Exception $e) {
                // Ignore les erreurs de parsing individuelles : un résultat
                // malformé ne doit pas faire échouer toute la recherche.
            }
        });

        return $students;
    }

    private function safeText(Crawler $node, string $selector): string
    {
        $element = $node->filter($selector);

        return $element->count() > 0 ? trim($element->text()) : '';
    }
}
