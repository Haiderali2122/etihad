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
}
