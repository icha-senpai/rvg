<?php

namespace App\Domain\Squadrons;

use App\Models\Squadron;
use App\Models\User;
use App\Models\SquadronMember;
use App\Domain\States\SquadronState;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SquadronService
{
    /**
     * List all squadrons with counts.
     */
    public function listAll()
    {
        return Squadron::with(['leader.roles'])
            ->with('emblem')
            ->withCount('members')
            ->orderBy('name')
            ->get();
    }

    /**
     * Return full squadron graph for web / API.
     */
    public function loadGraph(Squadron $squadron): Squadron
    {
        return $squadron->load([
            'members.user.roles',
            'leader.roles',
            'emblem',
        ]);
    }

    /**
     * Show a single squadron.
     */
    public function show(Squadron $squadron): Squadron
    {
        return $this->loadGraph($squadron);
    }

    /**
     * Create a new squadron with default state.
     */
    public function create(array $data): Squadron
    {
        $defaults = [
            'status'    => SquadronState::ACTIVE,
            'motto'     => null,
            'recruiting'=> true,
        ];

        return Squadron::create(array_merge($defaults, $data));
    }

    /**
     * Update a squadron.
     */
    public function update(Squadron $squadron, array $data): Squadron
    {
        $data = $this->sanitizeSquadronRichText($data);
        $squadron->update($data);
        return $squadron->fresh();
    }

    /**
     * Transition squadron state using the state machine.
     */
    public function transition(Squadron $squadron, string $toStatus): Squadron
    {
        return SquadronState::transition($squadron, $toStatus);
    }

    /**
     * Delete squadron (soft delete if model supports it).
     */
    public function delete(Squadron $squadron): void
    {
        $squadron->delete();
    }

    /**
     * Get members.
     */
    public function members(Squadron $squadron)
    {
        return $squadron->members()->with(['user.roles'])->get();
    }

    /**
     * Update settings.
     */
    public function updateSettings(Squadron $squadron, array $data): Squadron
    {
        $data = $this->sanitizeSquadronRichText($data);
        $squadron->update($data);
        return $squadron->fresh();
    }

    private function sanitizeSquadronRichText(array $data): array
    {
        foreach (['description', 'recruitment_propaganda'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $data[$field] = $this->sanitizeRichTextHtml($data[$field]);
        }

        return $data;
    }

    private function sanitizeRichTextHtml($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $html = trim((string) $value);
        if ($html === '') {
            return null;
        }

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
                $this->unwrapNode($node);
                continue;
            }

            if ($tag === 'a') {
                $href = $node->getAttribute('href');

                if (! $this->isSafeHref($href)) {
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

                if (! $this->isSafeSrc($src)) {
                    $node->parentNode?->removeChild($node);
                    continue;
                }

                foreach (iterator_to_array($node->attributes ?? []) as $attr) {
                    if (! in_array(strtolower($attr->name), ['src', 'alt', 'title'], true)) {
                        $node->removeAttribute($attr->name);
                    }
                }

                $node->setAttribute('loading', 'lazy');
            } else {
                $allowedAttrs = [];

                if (in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'mark', 'blockquote', 'th', 'td', 'col'], true)) {
                    $allowedAttrs[] = 'style';
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

        if ($plain === '' && ! $hasNonText) {
            return null;
        }

        return $clean;
    }

    private function unwrapNode($node): void
    {
        if (! $node || ! $node->parentNode) {
            return;
        }

        while ($node->firstChild) {
            $node->parentNode->insertBefore($node->firstChild, $node);
        }

        $node->parentNode->removeChild($node);
    }

    private function isSafeHref(?string $href): bool
    {
        $href = trim((string) $href);

        if ($href === '') return false;

        if (str_starts_with($href, '/')) return true;
        if (str_starts_with($href, '#')) return true;
        if (str_starts_with($href, 'http://')) return true;
        if (str_starts_with($href, 'https://')) return true;
        if (str_starts_with($href, 'mailto:')) return true;

        return false;
    }

    private function sanitizeInlineStyle(string $style): string
    {
        $style = trim($style);
        if ($style === '') return '';

        $allowed = [];

        $pairs = preg_split('/\s*;\s*/', $style, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($pairs as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) !== 2) continue;

            $prop = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if ($value === '') continue;

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

                continue;
            }
        }

        $out = [];
        foreach ($allowed as $k => $v) {
            $out[] = $k . ': ' . $v;
        }

        return implode('; ', $out);
    }

    private function isSafeSrc(?string $src): bool
    {
        $src = trim((string) $src);

        if ($src === '') return false;

        if (str_starts_with($src, '/')) return true;
        if (str_starts_with($src, 'http://')) return true;
        if (str_starts_with($src, 'https://')) return true;

        return false;
    }

    /**
     * Upload emblem.
     */
    public function uploadEmblem(Squadron $squadron, UploadedFile $file): Squadron
    {
        $path = $file->store('squadrons', 'public');

        $squadron->update([
            'emblem_path' => $path,
        ]);

        return $squadron->fresh();
    }

    /**
     * Assign a leader to a squadron.
     * (MembershipService handles promotion logic)
     */
    public function assignLeader(Squadron $squadron, User $user): Squadron
    {
        // demote all previous leaders
        SquadronMember::where('squadron_id', $squadron->id)
            ->where('role', SquadronMember::ROLE_LEADER)
            ->update(['role' => SquadronMember::ROLE_MEMBER]);

        // ensure membership exists & promote
        SquadronMember::updateOrCreate(
            [
                'user_id'     => $user->id,
                'squadron_id' => $squadron->id,
            ],
            [
                'membership_status' => SquadronMember::STATUS_ACTIVE,
                'role'              => SquadronMember::ROLE_LEADER,
                'joined_at'         => now(),
            ]
        );

        // update squadron model
        $squadron->update(['leader_id' => $user->id]);

        return $squadron->fresh();
    }
}
