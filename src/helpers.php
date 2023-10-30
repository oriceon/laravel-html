<?php

use Illuminate\Support\HtmlString;

if ( ! function_exists('link_to')) {
    /**
     * Generate a HTML link.
     */
    function link_to(string $url, ?string $title = null, array $attributes = [], ?bool $secure = null, ?bool $escape = true): HtmlString
    {
        return app('html')->link($url, $title, $attributes, $secure, $escape);
    }
}

if ( ! function_exists('link_to_asset')) {
    /**
     * Generate a HTML link to an asset.
     */
    function link_to_asset(string $url, ?string $title = null, array $attributes = [], ?bool $secure = null): HtmlString
    {
        return app('html')->linkAsset($url, $title, $attributes, $secure);
    }
}

if ( ! function_exists('link_to_route')) {
    /**
     * Generate a HTML link to a named route.
     */
    function link_to_route(string $name, ?string $title = null, array $parameters = [], array $attributes = []): HtmlString
    {
        return app('html')->linkRoute($name, $title, $parameters, $attributes);
    }
}

if ( ! function_exists('link_to_action')) {
    /**
     * Generate a HTML link to a controller action.
     */
    function link_to_action(string $action, ?string $title = null, array $parameters = [], array $attributes = []): HtmlString
    {
        return app('html')->linkAction($action, $title, $parameters, $attributes);
    }
}
