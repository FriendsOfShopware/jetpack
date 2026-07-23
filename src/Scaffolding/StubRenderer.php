<?php declare(strict_types=1);

namespace Frosh\Jetpack\Scaffolding;

/**
 * @internal
 */
final class StubRenderer
{
    /**
     * @param array<string, string> $values
     */
    public function render(string $template, array $values): string
    {
        preg_match_all('/%jetpack\.([a-z][a-z0-9_]*)%/', $template, $matches);
        $placeholders = array_values(array_unique($matches[1]));

        foreach ($placeholders as $placeholder) {
            if (!\array_key_exists($placeholder, $values)) {
                throw new \InvalidArgumentException(\sprintf('Missing scaffold value for placeholder "%s".', $placeholder));
            }
        }
        foreach ($values as $name => $value) {
            if (!\in_array($name, $placeholders, true)) {
                throw new \InvalidArgumentException(\sprintf('Scaffold value "%s" is not used by the template.', $name));
            }
        }

        $replacements = [];
        foreach ($values as $name => $value) {
            $replacements['%jetpack.' . $name . '%'] = $value;
        }

        return strtr($template, $replacements);
    }
}
