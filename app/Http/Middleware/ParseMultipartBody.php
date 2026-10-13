<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * PHP hanya mengisi $_POST/$_FILES untuk metode POST. API-KE01-11 memakai
 * PATCH multipart/form-data, jadi badan permintaan diurai manual dengan
 * request_parse_body() (PHP 8.4+) bila belum terurai.
 */
class ParseMultipartBody
{
    public function handle(Request $request, Closure $next): Response
    {
        $method = strtoupper($request->getMethod());
        $contentType = (string) $request->headers->get('Content-Type', '');

        $needsParsing = in_array($method, ['PUT', 'PATCH', 'DELETE'], true)
            && stripos($contentType, 'multipart/form-data') === 0
            && $request->request->count() === 0
            && $request->files->count() === 0
            && function_exists('request_parse_body');

        if ($needsParsing) {
            try {
                [$post, $files] = request_parse_body();
                $request->request->add($post);
                $request->files->add($files);
            } catch (Throwable) {
                // badan tidak dapat diurai: validasi berikutnya akan menolak (400)
            }
        }

        return $next($request);
    }
}
