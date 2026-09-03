<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class LogUserLogin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $ip;
    protected $userAgent;
    protected $is_login_success;
    protected $device_browser;
    protected array $location = [];

    public function __construct($user, $ip, $userAgent, $is_login_success, $device_browser, $location = [])
    {
        $this->location = $location;
        $this->user = $user;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
        $this->is_login_success  = $is_login_success;
        $this->device_browser = $device_browser;
    }

    public function handle()
    {
        \App\Models\UserLoginLog::create([
            'user_id'       => $this->user->id,
            'org_id'        => $this->user?->organization?->id,
            'login_at'      => now(),
            'ip_address'    => $this->ip,
            'client_data'   => $this->location,
            'status'        => $this->is_login_success,
            'device_browser' => $this->device_browser
        ]);
    }
}
