<?php declare(strict_types=1);

namespace Frosh\Jetpack\Configuration;

enum ConfigurationScope: string
{
    case Global = 'global';
    case SalesChannel = 'sales-channel';
    case Language = 'language';
    case SalesChannelLanguage = 'sales-channel-language';

    public function usesSalesChannel(): bool
    {
        return $this === self::SalesChannel || $this === self::SalesChannelLanguage;
    }

    public function usesLanguage(): bool
    {
        return $this === self::Language || $this === self::SalesChannelLanguage;
    }
}
