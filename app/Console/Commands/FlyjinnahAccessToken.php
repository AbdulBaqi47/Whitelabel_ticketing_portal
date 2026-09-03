<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FlyjinnahAccessToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:jinnah-access-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get jinnah access token and save in database';

    protected $authURL = "https://aero-suite-stage4-airarabia.isaaviation.net/api/auth/authenticate";
    // protected $authURL = "https://aero-suite-prod-airarabia.accelaero.com/api/auth/authenticate";


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $payload = [
			"login" => 'ABYAMANTRAG9',
			"password" => 'P@ss1234',
		];

        // $payload = [
		// 	"login" => 'FJLYOUSAFTT_API',
		// 	"password" => 'China@124',
		// ];

		$response = \Illuminate\Support\Facades\Http::post($this->authURL, $payload);
		$response = json_decode($response, true);
        
        \App\Models\AccessToken::updateOrCreate(['type' => 'jinnah_token'], [
            'type' => 'jinnah_token',
            'token' => $response['tokenPair']['accessToken'],
            'expiry_date' => now()->addHours(12)
        ]);
    }
}
