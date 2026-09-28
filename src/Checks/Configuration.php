<?php

namespace Tualo\Office\Zugferd\Checks;

use Tualo\Office\Basic\PostCheck;
use Tualo\Office\Basic\TualoApplication as App;

class Configuration extends PostCheck
{
    public static function test(array $config)
    {
        $qpdf = trim((string) shell_exec('command -v qpdf 2>/dev/null || true'));
        if ($qpdf === '') {
            PostCheck::formatPrintLn(['red'], "\tqpdf not found");
            PostCheck::formatPrintLn(['blue'], "\tinstall qpdf with `brew install qpdf` or `sudo apt install -y qpdf`");
        } else {
            exec(escapeshellarg($qpdf) . ' --version 2>/dev/null', $output, $return_var);
            if ($return_var != 0) {
                PostCheck::formatPrintLn(['red'], "\tqpdf *$qpdf* is not callable ($return_var)");
            } else {
                PostCheck::formatPrintLn(['green'], "\tqpdf version: " . implode(' ', $output));
            }
        }

        $browser = App::configuration('browsershot', 'chrome_path', false);
        $candidates = [];
        if ($browser !== false && $browser !== '') {
            $candidates[] = $browser;
        }
        $candidates = array_values(array_unique(array_merge($candidates, [
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',
            'google-chrome',
            'chromium',
            'chromium-browser',
        ])));

        $browserFound = false;
        foreach ($candidates as $candidate) {
            if ($candidate === '') continue;

            if (str_contains($candidate, '/')) {
                if (file_exists($candidate) && is_executable($candidate)) {
                    $browserFound = true;
                    PostCheck::formatPrintLn(['green'], "\tbrowser found: $candidate");
                    break;
                }
                continue;
            }

            $resolved = trim((string) shell_exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null || true'));
            if ($resolved !== '') {
                $browserFound = true;
                PostCheck::formatPrintLn(['green'], "\tbrowser found: $resolved");
                break;
            }
        }

        if (!$browserFound) {
            PostCheck::formatPrintLn(['red'], "\tbrowser executable for Browsershot not found");
            PostCheck::formatPrintLn(['blue'], "\tinstall Chrome/Chromium or set `browsershot.chrome_path` in the app configuration");
        }
    }
}
