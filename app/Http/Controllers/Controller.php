<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Store an uploaded bill, invoice or slip photo on the public disk.
     */
    protected function storeAttachment(Request $request, string $folder): ?string
    {
        return $request->file('attachment')?->store("attachments/{$folder}", 'public');
    }

    /**
     * A page inside this app to go back to, passed along by the screen that opened a form, or null if it is
     * missing or points elsewhere.
     */
    protected function safeReturnUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $root = url('/');
        if (str_starts_with($url, $root.'/') || $url === $root) {
            return $url;
        }

        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : null;
    }
}
