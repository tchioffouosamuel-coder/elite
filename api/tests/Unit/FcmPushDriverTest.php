<?php

namespace Tests\Unit;

use App\Services\Push\Drivers\FcmPushDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmPushDriverTest extends TestCase
{
    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privateKey));

        $credentialsPath = tempnam(sys_get_temp_dir(), 'fcm-test-');
        $this->assertNotFalse($credentialsPath);
        $this->credentialsPath = $credentialsPath;
        $this->assertNotFalse(file_put_contents(
            $this->credentialsPath,
            json_encode([
                'client_email' => 'test@example.invalid',
                'private_key' => $privateKey,
            ], JSON_THROW_ON_ERROR),
        ));

        config([
            'services.fcm.credentials' => $this->credentialsPath,
            'services.fcm.projet' => 'test-project',
        ]);
        Cache::forget('fcm.jeton_acces');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
            ]),
            'fcm.googleapis.com/v1/projects/test-project/messages:send' => Http::response([
                'name' => 'projects/test-project/messages/test-message',
            ]),
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->credentialsPath) && is_file($this->credentialsPath)) {
            unlink($this->credentialsPath);
        }

        Cache::forget('fcm.jeton_acces');
        parent::tearDown();
    }

    public function test_sync_wake_is_data_only_and_uses_background_apns_headers(): void
    {
        $envoyes = (new FcmPushDriver)->envoyer(
            ['device-token'],
            '',
            '',
            ['type' => 'sync'],
        );

        $this->assertSame(1, $envoyes);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'messages:send')
            && $request->data()['message']['data'] === ['type' => 'sync']
            && ! isset($request->data()['message']['notification'])
            && $request->data()['message']['android']['priority'] === 'high'
            && $request->data()['message']['apns']['headers'] === [
                'apns-push-type' => 'background',
                'apns-priority' => '5',
            ]
            && $request->data()['message']['apns']['payload']['aps']['content-available'] === 1);
    }

    public function test_regular_push_keeps_its_visible_notification_payload(): void
    {
        $envoyes = (new FcmPushDriver)->envoyer(
            ['device-token'],
            'Nouvelle annonce',
            'Une annonce est disponible.',
            ['type' => 'annonce'],
        );

        $this->assertSame(1, $envoyes);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'messages:send')
            && $request->data()['message']['notification'] === [
                'title' => 'Nouvelle annonce',
                'body' => 'Une annonce est disponible.',
            ]
            && $request->data()['message']['data'] === ['type' => 'annonce']
            && ! isset($request->data()['message']['apns']));
    }
}
