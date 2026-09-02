<?php

namespace Tests\Feature;

use App\Models\SmsLog;
use App\Services\OrangeSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrangeSmsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.orange.client_id' => 'test-client-id',
            'services.orange.client_secret' => 'test-client-secret',
            'services.orange.sender_address' => 'tel:+2370000',
            'services.orange.sender_name' => 'SMS 064221',
        ]);
    }

    private function reponseToken(string $token = 'access-token-1'): array
    {
        return ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => 3600];
    }

    private function reponseEnvoiReussi(string $id = 'abc123'): array
    {
        return [
            'outboundSMSMessageRequest' => [
                'address' => ['tel:+237600000000'],
                'resourceURL' => "https://api.orange.com/smsmessaging/v1/outbound/tel:+2370000/requests/{$id}",
            ],
        ];
    }

    public function test_recupere_et_met_en_cache_le_token(): void
    {
        Http::fake([
            'api.orange.com/oauth/v3/token' => Http::response($this->reponseToken('mon-token'), 200),
        ]);

        $service = app(OrangeSmsService::class);

        $this->assertSame('mon-token', $service->getAccessToken());
        $this->assertSame('mon-token', $service->getAccessToken());

        // Un seul appel HTTP : le deuxième getAccessToken() vient du cache.
        Http::assertSentCount(1);
        $this->assertSame('mon-token', Cache::get('orange_sms_access_token'));
    }

    public function test_envoi_reussi_journalise_le_message_id_et_le_statut_sent(): void
    {
        Http::fake([
            'api.orange.com/oauth/v3/token' => Http::response($this->reponseToken(), 200),
            'api.orange.com/smsmessaging/*' => Http::response($this->reponseEnvoiReussi('msg-001'), 201),
        ]);

        $resultat = app(OrangeSmsService::class)->sendSms('612345678', 'Bonjour');

        $this->assertTrue($resultat['success']);
        $this->assertSame('msg-001', $resultat['message_id']);
        $this->assertNull($resultat['error']);

        $this->assertDatabaseHas('sms_logs', [
            'message_id' => 'msg-001',
            'recipient' => 'tel:+237612345678',
            'status' => 'sent',
        ]);
    }

    public function test_401_invalide_le_cache_et_reessaie_une_fois_avec_succes(): void
    {
        Cache::put('orange_sms_access_token', 'token-perime', 3500);

        Http::fake([
            'api.orange.com/oauth/v3/token' => Http::response($this->reponseToken('token-frais'), 200),
            'api.orange.com/smsmessaging/*' => Http::sequence()
                ->push(['error' => 'invalid token'], 401)
                ->push($this->reponseEnvoiReussi('msg-002'), 201),
        ]);

        $resultat = app(OrangeSmsService::class)->sendSms('612345678', 'Bonjour');

        $this->assertTrue($resultat['success']);
        $this->assertSame('msg-002', $resultat['message_id']);

        Http::assertSentCount(3); // token initial ignoré (déjà en cache) + 401 + refetch token + retry envoi
        $this->assertSame('token-frais', Cache::get('orange_sms_access_token'));

        $this->assertDatabaseHas('sms_logs', [
            'message_id' => 'msg-002',
            'status' => 'sent',
        ]);
    }

    public function test_echec_definitif_est_journalise_en_failed_et_ne_leve_pas_d_exception(): void
    {
        Http::fake([
            'api.orange.com/oauth/v3/token' => Http::response($this->reponseToken(), 200),
            'api.orange.com/smsmessaging/*' => Http::response(['error' => 'destinataire invalide'], 400),
        ]);

        $resultat = app(OrangeSmsService::class)->sendSms('612345678', 'Bonjour');

        $this->assertFalse($resultat['success']);
        $this->assertNotNull($resultat['error']);

        $this->assertDatabaseHas('sms_logs', [
            'recipient' => 'tel:+237612345678',
            'status' => 'failed',
        ]);
    }

    public function test_echec_recuperation_token_ne_leve_pas_d_exception(): void
    {
        Http::fake([
            'api.orange.com/oauth/v3/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $resultat = app(OrangeSmsService::class)->sendSms('612345678', 'Bonjour');

        $this->assertFalse($resultat['success']);
        $this->assertNull($resultat['message_id']);

        $this->assertDatabaseHas('sms_logs', ['status' => 'failed']);
        $this->assertSame(1, SmsLog::count());
    }
}
