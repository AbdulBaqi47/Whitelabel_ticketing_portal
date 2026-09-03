<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SabreAccessToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sabre-access-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get sabre access token and save in database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connector = \App\Models\Connector::whereType('SABRE')->first()->getConnectorCreds();
        $ERP = 'V1:' . $connector['api_key'] . ':' . $connector['pcc'] . ':' . $connector['domain'] ?? 'AA';
        $basicEncode = 'Basic ' . base64_encode(base64_encode($ERP) . ':' . base64_encode($connector['api_secret']));
        $response = (new \App\Services\SabreService\API)->getSabreAccessToken($basicEncode);
        \App\Models\AccessToken::updateOrCreate(['type' => $response['token_type']], [
            'type' => $response['token_type'],
            'token' => $response['access_token'],
            'expiry_date' => date('Y-m-d H:i:s', time() + $response['expires_in'])
        ]);
    }
}
