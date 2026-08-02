<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Template;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles where document files live: {root}/{area_slug}/{template_slug}/{name}.
 * Uses the configured disk so Hostinger (local storage) and future cloud disks
 * both work without touching callers.
 */
class DocumentStorageService
{
    private string $disk;
    private string $root;

    public function __construct()
    {
        $this->disk = config('documents.disk');
        $this->root = trim(config('documents.root'), '/');
    }

    /** Directory (relative to the disk) for a template's files. */
    public function directoryFor(Template $template): string
    {
        return "{$this->root}/{$template->area->slug}/{$template->slug}";
    }

    /**
     * Store an uploaded file for a template, returning the stored relative path.
     * Keeps the original base name (sanitized) so PDF+XML pairs stay matched.
     */
    public function store(Template $template, UploadedFile $file, ?string $baseName = null): string
    {
        $dir = $this->directoryFor($template);
        $ext = $file->getClientOriginalExtension();
        $name = $baseName
            ? Str::slug($baseName) . '.' . $ext
            : Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $ext;

        // Avoid clobbering an existing file with the same name.
        $path = "{$dir}/{$name}";
        $i = 2;
        while (Storage::disk($this->disk)->exists($path)) {
            $path = "{$dir}/" . pathinfo($name, PATHINFO_FILENAME) . "-{$i}." . $ext;
            $i++;
        }

        Storage::disk($this->disk)->putFileAs($dir, $file, basename($path));

        return $path;
    }

    /** Delete a document's files from disk. */
    public function deleteFiles(Document $document): void
    {
        foreach ([$document->pdf_path, $document->xml_path] as $path) {
            if ($path && Storage::disk($this->disk)->exists($path)) {
                Storage::disk($this->disk)->delete($path);
            }
        }
    }

    public function disk(): string
    {
        return $this->disk;
    }
}
