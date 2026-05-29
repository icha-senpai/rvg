<?php

namespace App\Domain\Squadrons;

use App\Models\Squadron;
use DOMDocument;
use DOMXPath;

/**
 * Handles squadron settings updates and sanitizes rich-text fields before they
 * are stored.
 *
 * This keeps HTML cleanup rules in one place so controllers and higher-level
 * services do not need to understand DOM sanitization details.
 */
class SquadronSettingsService
{
    /**
     * Update a squadron after normalizing any rich-text fields in the payload.
     */
    public function update(Squadron $squadron, array $data): Squadron
    {
        $data = $this->sanitizeSquadronRichText($data);
        $squadron->update($data);

        return $squadron->fresh();
    }

    /**
     * Dedicated wrapper used by settings-focused callers for readability.
     */
    public function updateSettings(Squadron $squadron, array $data): Squadron
    {
        return $this->update($squadron, $data);
    }

    /**
     * Sanitize only the squadron fields that intentionally allow rich text.
     */
    protected function sanitizeSquadronRichText(array $data): array
    {
        foreach (['description', 'recruitment_propaganda'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $data[$field] = $this->sanitizeRichTextHtml($data[$field]);
        }

        return $data;
    }

    /**
     * Convert user-provided HTML into a safe, normalized subset that the frontend
     * can render without executing unsafe content.
     */
    protected function sanitizeRichTextHtml($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $html = trim((string) $value);
        if ($html === '') {
            return null;
        }

        // Plain text is wrapped into paragraph HTML so the frontend receives one
        // consistent rich-text shape regardless of how the user entered content.
        if (! str_contains($html, '<')) {
            $escaped = e($html);
            $escaped = nl2br($escaped, false);
            $html = '<p>' . $escaped . '</p>';
        }

        $allowedTags = [
            'p', 'br',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'b', 'strong',
            'i', 'em',
            'u',
            's', 'strike',
            'ul', 'ol', 'li',
            'blockquote',
            'hr',
            'code', 'pre',
            'div',
            'mark', 'span',
            'a',
            'img',
            'table', 'thead', 'tbody', 'tfoot',
            'tr', 'th', 'td',
            'colgroup', 'col',
        ];

        libxml_use_internal_errors(true);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $xpath = new DOMXPath($doc);

        // Script and style tags are removed up front before attribute-level cleanup
        // runs on the remaining nodes.
        foreach ($xpath->query('//script|//style') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $nodes = [];
        foreach ($doc->getElementsByTagName('*') as $node) {
            $nodes[] = $node;
        }

        $nodes = array_reverse($nodes);

        foreach ($nodes as $node) {
            $tag = strtolower($node->nodeName);

            if (! in_array($tag, $allowedTags, true)) {
                // Unknown wrapper tags are unwrapped so safe child content can
                // survive even when the outer element is not allowed.
                $this->unwrapNode($node);
                continue;
            }

            if ($tag === 'div') {
                if (! $this->isAllowedCalloutNode($node)) {
                    $this->unwrapNode($node);
                    continue;
                }

                $this->sanitizeCalloutNode($node);
                continue;
            }

            if ($tag === 'a') {
                $href = $node->getAttribute('href');

                if (! $this->isSafeHref($href)) {
                    // Unsafe links are removed rather than rewritten so the stored
                    // content never points at dangerous schemes.
                    $this->unwrapNode($node);
                    continue;
                }

                foreach (iterator_to_array($node->attributes ?? []) as $attr) {
                    if (! in_array(strtolower($attr->name), ['href', 'target', 'rel'], true)) {
                        $node->removeAttribute($attr->name);
                    }
                }

                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer');
            } elseif ($tag === 'img') {
                $src = $node->getAttribute('src');
                $widthPercent = $this->normalizeImageWidthPercent(
                    $node->getAttribute('data-width') !== ''
                        ? $node->getAttribute('data-width')
                        : $this->extractImageWidthFromStyle($node->getAttribute('style'))
                );

                if (! $this->isSafeSrc($src)) {
                    // Images are removed entirely when the source is unsafe because
                    // there is no useful text content to preserve.
                    $node->parentNode?->removeChild($node);
                    continue;
                }

                foreach (iterator_to_array($node->attributes ?? []) as $attr) {
                    if (! in_array(strtolower($attr->name), ['src', 'alt', 'title', 'class', 'data-align', 'data-width'], true)) {
                        $node->removeAttribute($attr->name);
                    }
                }

                $className = $this->sanitizeRichImageClassList($node->getAttribute('class'));

                if ($className === '') {
                    $node->removeAttribute('class');
                } else {
                    $node->setAttribute('class', $className);
                }

                $align = strtolower(trim($node->getAttribute('data-align')));

                if (! in_array($align, ['left', 'center', 'right'], true)) {
                    $node->removeAttribute('data-align');
                }

                if ($widthPercent !== null) {
                    $node->setAttribute('data-width', (string) $widthPercent);
                    $node->setAttribute('style', $this->buildImageStyle($widthPercent));
                } else {
                    $node->removeAttribute('data-width');
                    $node->removeAttribute('style');
                }

                $node->setAttribute('loading', 'lazy');
            } else {
                // Non-link, non-image tags get a narrow attribute allowlist so the
                // saved HTML can keep formatting without carrying risky metadata.
                $allowedAttrs = [];

                if (in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'mark', 'blockquote', 'th', 'td', 'col'], true)) {
                    $allowedAttrs[] = 'style';
                }

                // Allow class attribute on span for rich text colors (hz-rte-color-*)
                if ($tag === 'span') {
                    $allowedAttrs[] = 'class';
                }

                if (in_array($tag, ['th', 'td'], true)) {
                    $allowedAttrs[] = 'colspan';
                    $allowedAttrs[] = 'rowspan';
                }

                foreach (iterator_to_array($node->attributes ?? []) as $attr) {
                    if (! in_array(strtolower($attr->name), $allowedAttrs, true)) {
                        $node->removeAttribute($attr->name);
                    }
                }

                if ($tag === 'span' && $node->hasAttribute('class')) {
                    $className = $this->sanitizeRichTextSpanClassList($node->getAttribute('class'));

                    if ($className === '') {
                        $node->removeAttribute('class');
                    } else {
                        $node->setAttribute('class', $className);
                    }
                }

                if ($node->hasAttribute('style')) {
                    $style = $this->sanitizeInlineStyle($node->getAttribute('style'));

                    if ($style === '') {
                        $node->removeAttribute('style');
                    } else {
                        $node->setAttribute('style', $style);
                    }
                }

                if (in_array($tag, ['th', 'td'], true)) {
                    foreach (['colspan', 'rowspan'] as $spanAttr) {
                        if (! $node->hasAttribute($spanAttr)) {
                            continue;
                        }

                        $raw = trim($node->getAttribute($spanAttr));

                        if (preg_match('/^[1-9]\d?$/', $raw) !== 1) {
                            $node->removeAttribute($spanAttr);
                        }
                    }
                }
            }
        }

        $clean = trim((string) $doc->saveHTML());

        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if ($clean === '') {
            return null;
        }

        $plain = trim(strip_tags($clean));
        $hasNonText = preg_match('/<(img|hr|table)\b/i', $clean) === 1;

        // Empty formatting wrappers should not be persisted unless the content is
        // intentionally non-textual, like an image or table.
        if ($plain === '' && ! $hasNonText) {
            return null;
        }

        return $clean;
    }

    protected function isAllowedCalloutNode($node): bool
    {
        return strtolower($node->getAttribute('data-type')) === 'hz-rte-callout';
    }

    protected function sanitizeCalloutNode($node): void
    {
        foreach (iterator_to_array($node->attributes ?? []) as $attr) {
            if (! in_array(strtolower($attr->name), ['data-type', 'data-tone', 'data-accent-color', 'class', 'style'], true)) {
                $node->removeAttribute($attr->name);
            }
        }

        $customColor = $this->normalizeHexColor($node->getAttribute('data-accent-color'));
        $tone = $customColor !== null
            ? 'custom'
            : $this->sanitizeCalloutTone($node->getAttribute('data-tone'));

        $node->setAttribute('data-type', 'hz-rte-callout');
        $node->setAttribute('data-tone', $tone);
        $node->setAttribute('class', 'hz-rte-callout hz-rte-callout-' . $tone);

        if ($customColor !== null) {
            $node->setAttribute('data-accent-color', $customColor);
            $node->setAttribute('style', $this->buildCustomCalloutStyle($customColor));

            return;
        }

        $node->removeAttribute('data-accent-color');
        $node->removeAttribute('style');
    }

    protected function sanitizeCalloutTone(?string $tone): string
    {
        $tone = strtolower(trim((string) $tone));

        return in_array($tone, ['blue', 'cyan', 'magenta', 'orange', 'green', 'red'], true)
            ? $tone
            : 'blue';
    }

    protected function normalizeHexColor(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if (preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/', $value) !== 1) {
            return null;
        }

        if (strlen($value) === 4) {
            return sprintf(
                '#%s%s%s%s%s%s',
                $value[1],
                $value[1],
                $value[2],
                $value[2],
                $value[3],
                $value[3]
            );
        }

        return $value;
    }

    protected function buildCustomCalloutStyle(string $hexColor): string
    {
        return sprintf(
            'border-color: color-mix(in srgb, %1$s 45%%, transparent); background: color-mix(in srgb, %1$s 14%%, rgb(27 32 53 / 1));',
            $hexColor
        );
    }

    protected function sanitizeRichImageClassList(?string $className): string
    {
        $tokens = preg_split('/\s+/', trim((string) $className), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $allowed = [];

        if (in_array('hz-rich-image', $tokens, true)) {
            $allowed[] = 'hz-rich-image';
        }

        foreach (['hz-rich-image-left', 'hz-rich-image-center', 'hz-rich-image-right'] as $alignClass) {
            if (in_array($alignClass, $tokens, true)) {
                $allowed[] = $alignClass;
                break;
            }
        }

        return implode(' ', $allowed);
    }

    protected function normalizeImageWidthPercent($value): ?int
    {
        $raw = trim((string) $value);
        $raw = rtrim($raw, '%');

        if ($raw === '') {
            return null;
        }

        if (! is_numeric($raw)) {
            return null;
        }

        $width = (int) round((float) $raw);

        return max(15, min(100, $width));
    }

    protected function extractImageWidthFromStyle(?string $style): ?int
    {
        $style = (string) $style;

        if (preg_match('/(?:^|;)\s*width\s*:\s*(\d{1,3}(?:\.\d+)?)%/i', $style, $matches) !== 1) {
            return null;
        }

        return $this->normalizeImageWidthPercent($matches[1]);
    }

    protected function buildImageStyle(int $widthPercent): string
    {
        return 'width: ' . $widthPercent . '%; height: auto';
    }

    protected function sanitizeRichTextSpanClassList(?string $className): string
    {
        $tokens = preg_split('/\s+/', trim((string) $className), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $allowed = array_values(array_filter($tokens, function (string $token): bool {
            return preg_match('/^hz-rte-(size|font|color)-[a-z0-9-]+$/', $token) === 1;
        }));

        return implode(' ', array_unique($allowed));
    }

    /**
     * Remove an unsupported node while preserving its child content.
     */
    protected function unwrapNode($node): void
    {
        if (! $node || ! $node->parentNode) {
            return;
        }

        while ($node->firstChild) {
            $node->parentNode->insertBefore($node->firstChild, $node);
        }

        $node->parentNode->removeChild($node);
    }

    /**
     * Allow only local anchors, local paths, and safe outbound link schemes.
     */
    protected function isSafeHref(?string $href): bool
    {
        $href = trim((string) $href);

        if ($href === '') {
            return false;
        }

        if (str_starts_with($href, '/')) {
            return true;
        }

        if (str_starts_with($href, '#')) {
            return true;
        }

        if (str_starts_with($href, 'http://')) {
            return true;
        }

        if (str_starts_with($href, 'https://')) {
            return true;
        }

        if (str_starts_with($href, 'mailto:')) {
            return true;
        }

        return false;
    }

    /**
     * Reduce inline styles to a very small allowlist of presentational values.
     */
    protected function sanitizeInlineStyle(string $style): string
    {
        $style = trim($style);
        if ($style === '') {
            return '';
        }

        $allowed = [];
        $pairs = preg_split('/\s*;\s*/', $style, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($pairs as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $prop = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if ($value === '') {
                continue;
            }

            if ($prop === 'text-align') {
                $v = strtolower($value);
                if (in_array($v, ['left', 'center', 'right', 'justify'], true)) {
                    $allowed['text-align'] = $v;
                }
                continue;
            }

            if (in_array($prop, ['color', 'background-color'], true)) {
                $v = strtolower($value);

                if (preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $v) === 1) {
                    $allowed[$prop] = $v;
                    continue;
                }

                if (preg_match('/^rgba?\((\s*\d{1,3}\s*,){2}\s*\d{1,3}(\s*,\s*(0|0?\.\d+|1(\.0+)?)\s*)?\)$/i', $v) === 1) {
                    $allowed[$prop] = $v;
                    continue;
                }

                continue;
            }

            if ($prop === 'font-family') {
                $v = strtolower(trim($value, " \t\n\r\0\x0B\"'"));
                if (in_array($v, [
                    'system-ui',
                    'sans-serif',
                    'arial',
                    'verdana',
                    'tahoma',
                    'trebuchet ms',
                    'serif',
                    'georgia',
                    'times new roman',
                    'monospace',
                    'courier new',
                ], true)) {
                    $allowed['font-family'] = $v;
                }
                continue;
            }

            if ($prop === 'font-size') {
                $v = strtolower(trim($value));

                if (preg_match('/^(\d{1,3})px$/', $v, $m) === 1) {
                    $px = (int) $m[1];
                    if ($px >= 10 && $px <= 64) {
                        $allowed['font-size'] = $px . 'px';
                    }
                }
            }
        }

        $out = [];
        foreach ($allowed as $k => $v) {
            $out[] = $k . ': ' . $v;
        }

        return implode('; ', $out);
    }

    /**
     * Allow only local or http(s) image sources.
     */
    protected function isSafeSrc(?string $src): bool
    {
        $src = trim((string) $src);

        if ($src === '') {
            return false;
        }

        if (str_starts_with($src, '/')) {
            return true;
        }

        if (str_starts_with($src, 'http://')) {
            return true;
        }

        if (str_starts_with($src, 'https://')) {
            return true;
        }

        return false;
    }
}
