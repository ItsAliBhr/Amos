<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

/** Renders the operator documentation shipped in docs/ (installation, backup, decisions). */
class DocsController extends Controller
{
    public const DOCS = [
        'install' => ['INSTALL-cPanel.md', 'راهنمای نصب روی cPanel'],
        'backup' => ['BACKUP.md', 'راهنمای پشتیبان‌گیری و بازیابی'],
        'accounting' => ['ACCOUNTING.md', 'مدل حسابداری، NAV و واحدها'],
        'decisions' => ['DECISIONS.md', 'تصمیم‌ها، فرض‌ها و TODOها'],
        'security' => ['SECURITY.md', 'نکات امنیتی'],
    ];

    public function index()
    {
        return view('admin.docs', ['docs' => self::DOCS, 'html' => null, 'title' => null]);
    }

    public function show(string $slug)
    {
        abort_unless(isset(self::DOCS[$slug]), 404);
        [$file, $title] = self::DOCS[$slug];
        $path = base_path('docs/'.$file);
        abort_unless(is_file($path), 404);
        $html = Str::markdown(file_get_contents($path), ['html_input' => 'escape', 'allow_unsafe_links' => false]);

        return view('admin.docs', ['docs' => self::DOCS, 'html' => $html, 'title' => $title]);
    }
}
