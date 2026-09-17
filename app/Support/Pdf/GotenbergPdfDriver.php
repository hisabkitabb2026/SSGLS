<?php

namespace App\Support\Pdf;

use App\Support\Net\BlockedUrlException;
use App\Support\Net\PrivateNetworkGuard;
use Gotenberg\Gotenberg;
use Gotenberg\Stream;

class GotenbergPdfDriver
{
    public function loadView(string $viewname): GotenbergPdfResponse
    {
        $papersize = explode(' ', config('pdf.connections.gotenberg.papersize'));
        if (count($papersize) != 2) {
            throw new \InvalidArgumentException('Invalid Gotenberg Papersize specified');
        }

        $host = config('pdf.connections.gotenberg.host');

        // SSRF guard: gotenberg_host is an admin-supplied URL the server POSTs
        // the rendered HTML to. Block private/reserved/link-local targets even
        // if set via env/seed/stale config or reachable through DNS rebinding.
        try {
            PrivateNetworkGuard::assertAllowed((string) $host);
        } catch (BlockedUrlException $e) {
            throw new \InvalidArgumentException('Invalid Gotenberg host: '.$e->getMessage());
        }

        $html = view($viewname)->render();
        $assets = [];

        // Scan and extract local absolute font files, convert to relative assets
        if (preg_match_all('/url\((["\'])([^"\']+?\.(?:ttf|otf|woff|woff2))\1\)/i', $html, $matches)) {
            foreach ($matches[2] as $index => $filePath) {
                $normalizedPath = str_replace('\\', '/', $filePath);
                if (file_exists($filePath)) {
                    $filename = basename($normalizedPath);
                    $assets[] = Stream::path($filePath);

                    $quote = $matches[1][$index];
                    $html = str_replace(
                        "url({$quote}{$filePath}{$quote})",
                        "url({$quote}{$filename}{$quote})",
                        $html
                    );
                }
            }
        }

        $request = Gotenberg::chromium($host)
            ->pdf()
            ->margins(0, 0, 0, 0)
            ->paperSize($papersize[0], $papersize[1])
            ->html(
                Stream::string(
                    'document.html',
                    $html,
                )
            );

        if (! empty($assets)) {
            $request->assets(...$assets);
        }

        $result = Gotenberg::send($request);

        return new GotenbergPdfResponse($result);
    }
}
