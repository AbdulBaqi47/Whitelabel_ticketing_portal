<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\ResponseFactory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Interfaces\Auth\UserRepositoryInterface::class, \App\Repositories\User\UserRepository::class);   
        $this->app->bind(\App\Interfaces\BankAccounts\BankAccountsRepositoryInterface::class, \App\Repositories\BankAccounts\BankAccountsRepository::class);  
        $this->app->bind(\App\Interfaces\SupplierRepositoryInterface::class, \App\Repositories\SupplierRepository::class);
        $this->app->bind(\App\Interfaces\AirlineRepositoryInterface::class, \App\Repositories\AirlineRepository::class);
        $this->app->bind(\App\Interfaces\AirportRepositoryInterface::class, \App\Repositories\AirportRepository::class);
        $this->app->bind(\App\Interfaces\CountryRepositoryInterface::class, \App\Repositories\CountryRepository::class);
        $this->app->bind(\App\Interfaces\CityRepositoryInterface::class, \App\Repositories\CityRepository::class);
        $this->app->bind(\App\Interfaces\AirlineMarginRepositoryInterface::class, \App\Repositories\AirlineMarginRepository::class);
        $this->app->bind(\App\Interfaces\NewAndAlertRepositoryInterface::class, \App\Repositories\NewAndAlertRepository::class);
        $this->app->bind(\App\Interfaces\ConnectorRepositoryInterface::class, \App\Repositories\ConnectorRepository::class);
        $this->app->bind(\App\Interfaces\Organization\BranchRepositoryInterface::class, \App\Repositories\Organization\BranchRepository::class);
        $this->app->bind(\App\Interfaces\Organization\AgencyRepositoryInterface::class, \App\Repositories\Organization\AgencyRepository::class);
        $this->app->bind(\App\Interfaces\Organization\EmployeeRepositoryInterface::class, \App\Repositories\Organization\EmployeeRepository::class);
        $this->app->bind(\App\Interfaces\BookingRepositoryInterface::class, \App\Repositories\BookingRepository::class);
        $this->app->bind(\App\Interfaces\Organization\AgentManagementRepositoryInterface::class, \App\Repositories\Organization\AgentManagementRepository::class);
        $this->app->bind(\App\Interfaces\RefundTktRepositoryInterface::class, \App\Repositories\RefundTktRepository::class);
        $this->app->bind(\App\Interfaces\AccountHeadRepositoryInterface::class, \App\Repositories\AccountHeadRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResponseFactory::macro('successResponse', function ($code = 200, $message = 'success', $data = []) {
            return Response()->json([
                'status' => true,
                'code' => $code,
                'message' => $message,
                'data' => $data,
            ], $code);
        });
        ResponseFactory::macro('errorResponse', function ($code = 500, $message = 'error') {
            if ($code == 404) {
                return Response()->json([
                    'status' => false,
                    'code' => $code,
                    'message' => $message,
                    'errors' => ['message' => [$message]],
                    'data' => []
                ], $code);
            } else {
                return Response()->json([
                    'status' => false,
                    'code' => $code,
                    'message' => $message,
                    "errors" => ['message' => [$message]],
                ], $code);
            }
        });
    }
}