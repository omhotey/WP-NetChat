<?php
/** Public CSS delivery. External styles remain the fail-safe and cacheable alternative. */
if (!defined('ABSPATH')) exit;

add_filter('style_loader_tag', 'netchat_style_delivery', 10, 4);
function netchat_style_delivery($html, $handle, $href, $media) {
    if ((defined('NETCHAT_INLINE_STYLES') && NETCHAT_INLINE_STYLES !== true)
        || apply_filters('netchat_inline_styles', true) !== true
        || is_admin() || is_feed()
        || (function_exists('netchat_ncf_workspace_active') && netchat_ncf_workspace_active())
        || ($media !== '' && $media !== 'all')) return $html;

    $files = array('netchat-front'=>'style.css', 'netchat-leads'=>'leads.css',
        'netchat-contact-alternatives'=>'contact-alternatives.css',
        'netchat-services'=>'service-forms.css', 'netchat-popup'=>'popup.css');
    if (!is_string($handle) || !isset($files[$handle]) || !is_string($href) || !is_string($html)) return $html;
    $expected = wp_parse_url(NETCHAT_URL . 'assets/css/' . $files[$handle]);
    $actual = wp_parse_url(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (!is_array($expected) || !is_array($actual) || isset($actual['user']) || isset($actual['pass']) || isset($actual['fragment'])) return $html;
    foreach (array('host','path','port') as $part) {
        if (($actual[$part] ?? null) !== ($expected[$part] ?? null)) return $html;
    }
    // A protocol-relative URL inherits the same scheme. Never replace CDN/rewritten assets.
    if (isset($actual['scheme']) && ($actual['scheme'] !== ($expected['scheme'] ?? null))) return $html;
    if (isset($actual['query']) && $actual['query'] !== 'ver=' . NETCHAT_VER . '.' . @filemtime(NETCHAT_DIR . 'assets/css/' . $files[$handle])) return $html;

    // Replace only an ordinary WordPress link. Preserve optimization/CSP/SRI/alternate tags.
    if (!preg_match('~^\s*<link\b(.*?)\s*/?>\s*$~sD', $html, $tag)) return $html;
    $attributes = array(); $offset = 0; $raw = $tag[1];
    while ($offset < strlen($raw)) {
        if (trim(substr($raw, $offset)) === '') break;
        if (!preg_match('~\G\s+([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*([\'"])(.*?)\2~s', $raw, $attribute, 0, $offset)) return $html;
        $name = strtolower($attribute[1]);
        if (isset($attributes[$name]) || !in_array($name, array('rel','id','href','type','media'), true)) return $html;
        $attributes[$name] = html_entity_decode($attribute[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $offset += strlen($attribute[0]);
    }
    if (($attributes['rel'] ?? '') !== 'stylesheet' || ($attributes['id'] ?? '') !== $handle . '-css'
        || ($attributes['href'] ?? '') !== html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        || (isset($attributes['type']) && $attributes['type'] !== 'text/css')
        || (isset($attributes['media']) && $attributes['media'] !== $media)) return $html;

    // Fixed local paths only; never follow a symlink outside this plugin's CSS directory.
    $root = realpath(NETCHAT_DIR);
    $base = realpath(NETCHAT_DIR . 'assets/css');
    $path = realpath(NETCHAT_DIR . 'assets/css/' . $files[$handle]);
    if ($root === false || $base === false || strpos($base . DIRECTORY_SEPARATOR, rtrim($root, '/\\') . DIRECTORY_SEPARATOR) !== 0 || $path === false || dirname($path) !== $base || !is_file($path) || !is_readable($path)) return $html;
    $size = @filesize($path);
    if ($size === false || $size < 1 || $size > 32768) return $html;
    $css = @file_get_contents($path);
    if ($css === false || strlen($css) !== $size || strpos($css, '<') !== false || strpos($css, '\\') !== false) return $html;
    $tokens = preg_replace('~/\*.*?\*/~s', '', $css);
    if ($tokens === null || preg_match('~url\s*\(|@(?:import|charset)\b~i', $tokens)) return $html;
    return '<style id="' . esc_attr($handle . '-css') . '" media="' . esc_attr($media === '' ? 'all' : $media) . '">' . $css . '</style>' . "\n";
}
