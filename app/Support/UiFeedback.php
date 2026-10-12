<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Identifies confirmed feedback without changing operational persistence. */
final class UiFeedback
{
    public static function success(string $message): void
    {
        self::flash('status', $message);
    }

    public static function error(string $message): void
    {
        self::flash('error', $message);
    }

    private static function flash(string $key, string $message): void
    {
        session()->flash($key, $message);
        session()->flash('ui_feedback_'.$key.'_id', (string) Str::uuid());
    }
}
