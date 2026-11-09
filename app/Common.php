<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (! function_exists('asset_url')) {
    /**
     * URL of a file under public/ with its modification time appended
     * (`assets/css/app.css?v=1762800000`).
     *
     * The host serves static files with a week-long cache (CDN and browser),
     * so without a version a deploy's CSS and JS reach returning users only
     * days later. The query changes whenever the file does.
     */
    function asset_url(string $path): string
    {
        $file = FCPATH . ltrim($path, '/');

        return base_url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
    }
}
