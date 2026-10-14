<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class Mailer
{
    public static function send(string $to, string $subject, string $html): bool
    {
        try {
            $response = Http::withHeaders([
                'api-key' => env('BREVO_API_KEY'),
                'accept' => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => [
                    'name' => env('MAIL_FROM_NAME', 'Omie Store'),
                    'email' => env('MAIL_FROM_ADDRESS'),
                ],
                'to' => [['email' => $to]],
                'subject' => $subject,
                'htmlContent' => $html,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}