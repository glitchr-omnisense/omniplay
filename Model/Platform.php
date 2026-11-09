<?php

namespace Omniplay\Model;

/** The families of platforms a game runs on; a store's finer names are folded into them. */
enum Platform: string
{
    case Windows = 'windows';
    case Mac = 'mac';
    case Linux = 'linux';
    case Android = 'android';
    case Ios = 'ios';
    case Web = 'web';
    case Switch = 'switch';
    case PlayStation = 'playstation';
    case Xbox = 'xbox';

    /** "PC (Microsoft Windows)", "osx", "PlayStation 5", "Nintendo Switch 2", "HTML5"... null when none of these. */
    public static function fromLabel(string $label): ?self
    {
        $label = strtolower($label);

        return match (true) {
            str_contains($label, 'windows') || 'pc' === $label || 'win' === $label => self::Windows,
            str_contains($label, 'mac') || str_contains($label, 'osx') || 'os x' === $label => self::Mac,
            str_contains($label, 'linux') || str_contains($label, 'steamos') => self::Linux,
            str_contains($label, 'android') => self::Android,
            'ios' === $label || str_contains($label, 'iphone') || str_contains($label, 'ipad') => self::Ios,
            str_contains($label, 'web') || str_contains($label, 'html') || str_contains($label, 'browser') => self::Web,
            str_contains($label, 'switch') => self::Switch,
            str_contains($label, 'playstation') || preg_match('~^ps\d~', $label) > 0 => self::PlayStation,
            str_contains($label, 'xbox') => self::Xbox,
            default => null,
        };
    }

    /**
     * @param iterable<string> $labels
     *
     * @return list<self> each family once, in the order first met
     */
    public static function fromLabels(iterable $labels): array
    {
        $platforms = [];
        foreach ($labels as $label) {
            $platform = self::fromLabel($label);
            if (null !== $platform && !\in_array($platform, $platforms, true)) {
                $platforms[] = $platform;
            }
        }

        return $platforms;
    }
}
