<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CatalogImage
{
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Preserve previously stored absolute image URLs.
        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        return url(Storage::disk('public')->url($path));
    }

    public static function rules(): array
    {
        return ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
    }

    public static function save(Model $model, Request $request, array $data, string $directory, string $column = 'image', array $aliases = []): Model
    {
        $keys = array_merge(['image'], $aliases);
        $files = array_values(array_filter($keys, fn ($key) => $request->hasFile($key)));
        if (count($files) > 1 || ($files && $request->boolean('remove_image'))) {
            throw ValidationException::withMessages([
                'image' => ['Send one image field only; do not combine an upload with remove_image.'],
            ]);
        }

        foreach (array_merge($keys, ['remove_image']) as $key) {
            unset($data[$key]);
        }
        $oldPath = $model->getAttribute($column);
        $newPath = null;
        if ($files) {
            $newPath = $request->file($files[0])->store($directory, 'public');
            if (! $newPath) {
                throw new RuntimeException('Unable to store catalog image.');
            }
            $data[$column] = $newPath;
        } elseif ($request->boolean('remove_image')) {
            $data[$column] = null;
        }

        try {
            DB::transaction(function () use ($model, $data) {
                $model->fill($data)->save();
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                self::delete($newPath, $directory);
            }
            throw $exception;
        }

        // Keep the previous file until the database update has succeeded.
        if (array_key_exists($column, $data) && $oldPath !== $data[$column]) {
            self::delete($oldPath, $directory);
        }

        return $model->refresh();
    }

    public static function delete(?string $path, string $directory): void
    {
        // Only delete files owned by this catalog; never external URLs or legacy paths.
        if ($path && str_starts_with($path, $directory.'/') && ! str_contains($path, '..')) {
            Storage::disk('public')->delete($path);
        }
    }
}
