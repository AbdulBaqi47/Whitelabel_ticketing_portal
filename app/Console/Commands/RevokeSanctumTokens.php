<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeSanctumTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:revoke-sanctum-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = PersonalAccessToken::count();

        PersonalAccessToken::query()->delete();

        $this->info("Revoked {$count} tokens successfully.");

        return Command::SUCCESS;
    }
}
