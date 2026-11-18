<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment)
    {
        Gate::authorize('view', $attachment);
        $path = AttachmentService::path($attachment);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="attachment-'.$attachment->id.'.'.pathinfo($attachment->stored_name, PATHINFO_EXTENSION).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
