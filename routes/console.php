<?php

\Illuminate\Support\Facades\Schedule::command('app:sabre-access-token')->weekly();
\Illuminate\Support\Facades\Schedule::command('app:cancel-sabre-booking')->everyTwoMinutes();
\Illuminate\Support\Facades\Schedule::command('app:get-pnr-expiry-from-confirmed-booking')->everyTenMinutes();
\Illuminate\Support\Facades\Schedule::command('app:not-flown-ticket')->dailyAt('23:59');
\Illuminate\Support\Facades\Schedule::command('app:automatic-tkt-issue')->everyFiveMinutes();
\Illuminate\Support\Facades\Schedule::command('app:cancel-all-bookings')->dailyAt('00:01');
\Illuminate\Support\Facades\Schedule::command('app:jinnah-access-token')->dailyAt('00:01');
\Illuminate\Support\Facades\Schedule::command('app:revoke-sanctum-tokens')->dailyAt('00:01');