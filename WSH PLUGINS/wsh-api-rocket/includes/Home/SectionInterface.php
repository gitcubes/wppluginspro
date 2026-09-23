<?php
namespace WSH\APIROCKET\Home\Sections;

if ( ! defined('ABSPATH') ) exit;

interface SectionInterface {
    /**
     * @return array{items:array, excluded_ids?:array, meta?:array}
     */
    public function build(array $cfg, array $ctx): array;
}
