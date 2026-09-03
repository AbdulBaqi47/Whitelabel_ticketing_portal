<?php

\Illuminate\Support\Facades\Broadcast::channel('user.{uuid}', function ($user, $uuid) {
    return $user->uuid === $uuid;
});