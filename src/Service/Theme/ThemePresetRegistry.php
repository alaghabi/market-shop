<?php

namespace App\Service\Theme;

/**
 * Visual presets for storefront themes. Each preset maps a theme code to a
 * complete color palette and typography so boutiques can switch look & feel
 * without touching code.
 */
final class ThemePresetRegistry
{
    /** @return array<string, array{name: string, description: string, primaryColor: string, secondaryColor: string, fontFamily: string, borderRadius: string, layout: string, colorPalette: array<string, string>}> */
    public function all(): array
    {
        return [
            'hanooti-glass' => [
                'name' => 'Hanooti Glass',
                'description' => 'Violet moderne, surfaces lumineuses et effet glassmorphism.',
                'primaryColor' => '#3525cd',
                'secondaryColor' => '#505f76',
                'fontFamily' => 'Inter, system-ui, sans-serif',
                'borderRadius' => '12px',
                'layout' => 'glass',
                'colorPalette' => [
                    'primary' => '#3525cd',
                    'primaryContainer' => '#4f46e5',
                    'secondary' => '#505f76',
                    'background' => '#fcf8ff',
                    'surface' => '#ffffff',
                    'surfaceContainer' => '#f0ecf9',
                    'surfaceContainerHigh' => '#eae6f4',
                    'text' => '#1b1b24',
                    'textMuted' => '#464555',
                    'outline' => '#c7c4d8',
                    'accent' => '#7c3aed',
                ],
            ],
            'hanooti-marketplace' => [
                'name' => 'Hanooti Marketplace',
                'description' => 'Marketplace premium avec surfaces glass, CTA achat vert et navigation catalogue par slug.',
                'primaryColor' => '#7C3AED',
                'secondaryColor' => '#475569',
                'fontFamily' => '"Nunito Sans", Rubik, system-ui, sans-serif',
                'borderRadius' => '18px',
                'layout' => 'glass',
                'colorPalette' => [
                    'primary' => '#7C3AED',
                    'primaryContainer' => '#A78BFA',
                    'secondary' => '#475569',
                    'background' => '#FAF5FF',
                    'surface' => '#FFFFFF',
                    'surfaceContainer' => '#F3E8FF',
                    'surfaceContainerHigh' => '#E9D5FF',
                    'text' => '#0F172A',
                    'textMuted' => '#475569',
                    'outline' => '#DDD6FE',
                    'accent' => '#22C55E',
                ],
            ],
            'nordic-editorial' => [
                'name' => 'Nordic Editorial',
                'description' => 'Beige chaud, noir profond et mise en page éditoriale.',
                'primaryColor' => '#111111',
                'secondaryColor' => '#6b6560',
                'fontFamily' => '"DM Sans", Inter, sans-serif',
                'borderRadius' => '1.6rem',
                'layout' => 'editorial',
                'colorPalette' => [
                    'primary' => '#111111',
                    'primaryContainer' => '#2b2b2b',
                    'secondary' => '#6b6560',
                    'background' => '#f6f2eb',
                    'surface' => '#ffffff',
                    'surfaceContainer' => '#ece5d9',
                    'surfaceContainerHigh' => '#e7e0d6',
                    'text' => '#171717',
                    'textMuted' => '#6b6560',
                    'outline' => '#d8d0c4',
                    'accent' => '#a44100',
                ],
            ],
            'ocean-minimal' => [
                'name' => 'Ocean Minimal',
                'description' => 'Bleu océan, fond clair et interface épurée.',
                'primaryColor' => '#0e7490',
                'secondaryColor' => '#475569',
                'fontFamily' => 'Inter, system-ui, sans-serif',
                'borderRadius' => '8px',
                'layout' => 'minimal',
                'colorPalette' => [
                    'primary' => '#0e7490',
                    'primaryContainer' => '#0891b2',
                    'secondary' => '#475569',
                    'background' => '#f0f9ff',
                    'surface' => '#ffffff',
                    'surfaceContainer' => '#e0f2fe',
                    'surfaceContainerHigh' => '#bae6fd',
                    'text' => '#0f172a',
                    'textMuted' => '#64748b',
                    'outline' => '#cbd5e1',
                    'accent' => '#0369a1',
                ],
            ],
        ];
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->all());
    }

    public function has(string $code): bool
    {
        return isset($this->all()[$code]);
    }

    /** @return array<string, mixed>|null */
    public function get(string $code): ?array
    {
        return $this->all()[$code] ?? null;
    }

    public function defaultCode(): string
    {
        return 'hanooti-marketplace';
    }
}
