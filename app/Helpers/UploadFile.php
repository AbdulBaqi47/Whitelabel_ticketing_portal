<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function STR_RANDOM($length): string
{
    return Str::random($length);
}

function UPLOAD_FILE(object $file, string $path)
{
    $name =  STR_RANDOM(10) . '-' . time() . '.' . $file->getClientOriginalExtension();
    if (MAKE_DIR($path)) {
        $full_image_name = Storage::url(Storage::disk('public')->putFileAs($path, $file, $name));
        return $full_image_name;
    }
    return false;
}

function MAKE_DIR(string $name): bool
{
    if (!Storage::disk('public')->exists($name)) {
        Storage::disk('public')->makeDirectory($name);
    }

    return true;
}

function REMOVE_FILE(string $filepath): bool
{
    return @unlink($filepath ?? '');
}

function UPLOAD_PDF_FILE(object $file, string $path, string $download_type, $pnr)
{
    $name = $pnr . '-E-' . ($download_type == 'booking_download' ? 'booking' : 'ticket') . '.' . $file->getClientOriginalExtension();
    if (MAKE_DIR($path)) {
        Storage::disk('public')->putFileAs($path, $file, $name);
        return Storage::url($path . '/' . $name);
    }
    
    return false;
}
