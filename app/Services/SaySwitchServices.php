<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SaySwitchServices
{
    protected $token;
    protected $baseUrl;

    public function __construct()
    {
        $this->token = env('SAYSWITCH_SECRET');
        $this->baseUrl = env('SAYSWITCH_BASE_URL');
    }

    public function initializeTransaction(array $data)
    {
        try {
            $response = Http::withToken($this->token)
                ->asMultipart()
                ->post("{$this->baseUrl}/transaction/initialize", [
                    'amount'       => $data['amount'],
                    'currency'     => $data['currency'] ?? 'NGN',
                    'email'        => $data['email'],
                    'reference'    => $data['reference'],
                    'callback_url' => $data['callback_url'],
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('SaySwitch Payment Initialization Failed', [
                'status' => $response->status(),
                'body'   => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('SaySwitch Service Error: ' . $e->getMessage());
            return null;
        }
    }

    public function verifyTransaction($reference)
    {
        try {
            $response = Http::withToken($this->token)
                ->get("{$this->baseUrl}/transaction/verify/{$reference}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('SaySwitch Verification Failed', [
                'reference' => $reference,
                'status' => $response->status()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('SaySwitch Verification Service Error: ' . $e->getMessage());
            return null;
        }
    }
}
