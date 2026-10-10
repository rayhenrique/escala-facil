<?php

namespace App\Http\Controllers;

use App\Support\ErrorReporting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClientErrorController extends Controller
{
    public function __invoke(Request $request, ErrorReporting $reporting): Response
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:255'],
            'line' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'column' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'page' => ['nullable', 'string', 'max:500'],
        ]);

        $reporting->logClientError([
            'message' => $validated['message'],
            'source' => $this->fileName($validated['source'] ?? ''),
            'line' => $validated['line'] ?? null,
            'column' => $validated['column'] ?? null,
            'page' => $this->pathOnly($validated['page'] ?? '/'),
        ]);

        return response()->noContent();
    }

    private function fileName(string $source): string
    {
        $path = $this->pathOnly($source);
        $base = basename($path);

        return $base === '' ? '' : mb_substr($base, 0, 255);
    }

    private function pathOnly(string $url): string
    {
        $withoutQuery = strtok($url, '?') ?: '/';
        $path = parse_url($withoutQuery, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        return mb_substr($path, 0, 500);
    }
}
