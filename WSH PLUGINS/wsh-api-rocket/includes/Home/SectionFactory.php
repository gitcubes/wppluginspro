<?php
namespace WSH\APIROCKET\Home;

use WSH\APIROCKET\Home\Sections\LatestSection;
use WSH\APIROCKET\Home\Sections\CategoryLatestSection;
use WSH\APIROCKET\Home\Sections\ManualPostsSection;
use WSH\APIROCKET\Home\Sections\MoreNewsSection;

if ( ! defined('ABSPATH') ) exit;

final class SectionFactory {

    public static function make(array $cfg) {
        $type = (string)($cfg['type'] ?? '');

        return match ($type) {
            'latest'          => new LatestSection(),
            'category_latest' => new CategoryLatestSection(),
            'manual_posts'    => new ManualPostsSection(),
            'more_news'       => new MoreNewsSection(),
            default           => null,
        };
    }
}
