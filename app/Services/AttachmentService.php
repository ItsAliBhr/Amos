<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\FundSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores uploads on the private disk (storage/app/private), never under public/.
 * Files are served only through an authorised controller.
 */
class AttachmentService
{
    public const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public static function rules(): array
    {
        $kb = (int) (FundSetting::current()->max_upload_kb ?: 5120);

        return ['nullable', 'file', 'max:'.$kb, 'mimes:jpg,jpeg,png,webp,pdf', 'mimetypes:'.implode(',', array_keys(self::ALLOWED_MIMES))];
    }

    public static function store(?UploadedFile $file, User $user, string $folder): ?Attachment
    {
        if (! $file) {
            return null;
        }
        $mime = $file->getMimeType();
        if (! isset(self::ALLOWED_MIMES[$mime])) {
            throw ValidationException::withMessages(['file' => 'نوع فایل مجاز نیست.']);
        }
        $ext = self::ALLOWED_MIMES[$mime];
        $stored = Str::random(40).'.'.$ext;
        $dir = $folder.'/'.now()->format('Y/m');
        $path = $file->storeAs($dir, $stored, 'local');

        return Attachment::create([
            'uploaded_by' => $user->id,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
            'stored_name' => $stored,
            'storage_path' => $path,
            'mime_type' => $mime,
            'file_size' => $file->getSize(),
        ]);
    }

    public static function attachTo(?Attachment $attachment, $model): void
    {
        if ($attachment) {
            $attachment->related()->associate($model);
            $attachment->save();
        }
    }

    public static function path(Attachment $a): string
    {
        return Storage::disk('local')->path($a->storage_path);
    }
}
