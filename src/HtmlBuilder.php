<?php

namespace Oriceon\Html;

use BadMethodCallException;
use Exception;
use Illuminate\Support\HtmlString;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Routing\UrlGenerator;

class HtmlBuilder
{
    use Macroable, Componentable {
        Macroable::__call as macroCall;
        Componentable::__call as componentCall;
    }

    /**
     * The URL generator instance.
     */
    protected UrlGenerator $url;

    /**
     * The View Factory instance.
     */
    protected Factory $view;

    /**
     * Create a new Html builder instance.
     */
    public function __construct(UrlGenerator $url, Factory $view)
    {
        $this->url  = $url;
        $this->view = $view;
    }

    /**
     * Convert Html string to entities.
     */
    public function entities(string $value): string
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * Convert entities to Html characters.
     */
    public function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate a link to a JavaScript file.
     */
    public function script(string $url, array $attributes = [], ?bool $secure = null): HtmlString
    {
        $attributes['src'] = $this->url->asset($url, $secure);

        return $this->toHtmlString('<script' . $this->attributes($attributes) . '></script>');
    }

    /**
     * Generate a link to a CSS file.
     */
    public function style(string $url, array $attributes = [], ?bool $secure = null): HtmlString
    {
        $defaults = ['media' => 'all', 'type' => 'text/css', 'rel' => 'stylesheet'];

        $attributes = array_merge($defaults, $attributes);

        $attributes['href'] = $this->url->asset($url, $secure);

        return $this->toHtmlString('<link' . $this->attributes($attributes) . '>');
    }

    /**
     * Generate Html image element.
     */
    public function image(string $url, string $alt = null, array $attributes = [], ?bool $secure = null): HtmlString
    {
        $attributes['alt'] = $alt;

        return $this->toHtmlString('<img src="' . $this->url->asset($url, $secure) . '"' . $this->attributes($attributes) . '>');
    }

    /**
     * Generate a link to a Favicon file.
     */
    public function favicon(string $url, array $attributes = [], ?bool $secure = null): HtmlString
    {
        $defaults = ['rel' => 'shortcut icon', 'type' => 'image/x-icon'];

        $attributes = array_merge($defaults, $attributes);

        $attributes['href'] = $this->url->asset($url, $secure);

        return $this->toHtmlString('<link' . $this->attributes($attributes) . '>');
    }

    /**
     * Generate Html link.
     */
    public function link(string $url, mixed $title = null, array $attributes = [], ?bool $secure = null, ?bool $escape = true): HtmlString
    {
        $url = $this->url->to($url, [], $secure);

        if (is_null($title) || $title === false) {
            $title = $url;
        }

        if ($escape) {
            $title = $this->entities($title);
        }

        return $this->toHtmlString('<a href="' . $this->entities($url) . '"' . $this->attributes($attributes) . '>' . $title . '</a>');
    }

    /**
     * Generate https Html link.
     */
    public function secureLink(string $url, mixed $title = null, array $attributes = [], bool $escape = true): HtmlString
    {
        return $this->link($url, $title, $attributes, true, $escape);
    }

    /**
     * Generate Html link to an asset.
     */
    public function linkAsset(string $url, mixed $title = null, array $attributes = [], ?bool $secure = null, bool $escape = true): HtmlString
    {
        $url = $this->url->asset($url, $secure);

        return $this->link($url, $title ?: $url, $attributes, $secure, $escape);
    }

    /**
     * Generate https Html link to an asset.
     */
    public function linkSecureAsset(string $url, mixed $title = null, array $attributes = [], bool $escape = true): HtmlString
    {
        return $this->linkAsset($url, $title, $attributes, true, $escape);
    }

    /**
     * Generate Html link to a named route.
     */
    public function linkRoute(string $name, mixed $title = null, array $parameters = [], array $attributes = [], ?bool $secure = null, bool $escape = true): HtmlString
    {
        return $this->link($this->url->route($name, $parameters), $title, $attributes, $secure, $escape);
    }

    /**
     * Generate Html link to a controller action.
     */
    public function linkAction(string $action, mixed $title = null, array $parameters = [], array $attributes = [], ?bool $secure = null, bool $escape = true): HtmlString
    {
        return $this->link($this->url->action($action, $parameters), $title, $attributes, $secure, $escape);
    }

    /**
     * Generate Html link to an email address.
     *
     * @throws Exception
     */
    public function mailto(string $email, mixed $title = null, array $attributes = [], bool $escape = true): HtmlString
    {
        $email = $this->email($email);

        $title = $title ?: $email;

        if ($escape) {
            $title = $this->entities($title);
        }

        $email = $this->obfuscate('mailto:') . $email;

        return $this->toHtmlString('<a href="' . $email . '"' . $this->attributes($attributes) . '>' . $title . '</a>');
    }

    /**
     * Obfuscate an e-mail address to prevent spam-bots from sniffing it.
     *
     * @throws Exception
     */
    public function email(string $email): string
    {
        return str_replace('@', '&#64;', $this->obfuscate($email));
    }

    /**
     * Generates non-breaking space entities based on the number supplied.
     */
    public function nbsp(int $num = 1): string
    {
        return str_repeat('&nbsp;', $num);
    }

    /**
     * Generate an ordered list of items.
     */
    public function ol(array $list, array $attributes = []): HtmlString|string
    {
        return $this->listing('ol', $list, $attributes);
    }

    /**
     * Generate an unordered list of items.
     */
    public function ul(array $list, array $attributes = []): HtmlString|string
    {
        return $this->listing('ul', $list, $attributes);
    }

    /**
     * Generate a description list of items.
     */
    public function dl(array $list, array $attributes = []): HtmlString
    {
        $html = "<dl{$this->attributes($attributes)}>";

        foreach ($list as $key => $value) {
            $value = (array) $value;

            $html .= "<dt>$key</dt>";

            foreach ($value as $v_key => $v_value) {
                $html .= "<dd>$v_value</dd>";
            }
        }

        $html .= '</dl>';

        return $this->toHtmlString($html);
    }

    /**
     * Create a listing Html element.
     */
    protected function listing(string $type, array $list = [], array $attributes = []): HtmlString|string
    {
        $html = '';

        if (count($list) === 0) {
            return $html;
        }

        // Essentially, we will just spin through the list and build the list of the Html elements from the array.
        // We will also handle nested lists in case that is present in the array.
        // Then we will build out the final listing elements.
        foreach ($list as $key => $value) {
            $html .= $this->listingElement($key, $type, $value);
        }

        return $this->toHtmlString("<{$type}{$this->attributes($attributes)}>{$html}</{$type}>");
    }

    /**
     * Create the Html for a listing element.
     */
    protected function listingElement(mixed $key, string $type, mixed $value): HtmlString|string
    {
        if (is_array($value)) {
            return $this->nestedListing($key, $type, $value);
        }

        return '<li>' . e($value, false) . '</li>';
    }

    /**
     * Create the Html for a nested listing attribute.
     */
    protected function nestedListing(mixed $key, string $type, mixed $value): HtmlString|string
    {
        if (is_int($key)) {
            return $this->listing($type, $value);
        }

        return '<li>' . $key . $this->listing($type, $value) . '</li>';
    }

    /**
     * Build Html attribute string from an array.
     */
    public function attributes(array $attributes = []): string
    {
        $html = [];

        foreach ($attributes as $key => $value) {
            $element = $this->attributeElement($key, $value);

            if ( ! is_null($element)) {
                $html[] = $element;
            }
        }

        return count($html) > 0 ? ' ' . implode(' ', $html) : '';
    }

    /**
     * Build a single attribute element.
     */
    protected function attributeElement(mixed $key, mixed $value): ?string
    {
        // For numeric keys, we will assume that the value is a boolean attribute
        // where the presence of the attribute represents a true value and the
        // absence represents a false value.
        // This will convert Html attributes such as "required" to a correct
        // form instead of using incorrect numerics.
        if (is_numeric($key)) {
            return $value;
        }

        // Treat boolean attributes as Html properties
        if (is_bool($value) && $key !== 'value') {
            return $value ? $key : '';
        }

        if (is_array($value) && $key === 'class') {
            return 'class="' . implode(' ', $value) . '"';
        }

        if ( ! is_null($value)) {
            return $key . '="' . e($value, false) . '"';
        }

        return null;
    }

    /**
     * Obfuscate a string to prevent spam-bots from sniffing it.
     *
     * @throws Exception
     */
    public function obfuscate(string $value): string
    {
        $safe = '';

        foreach (str_split($value) as $letter) {
            if (ord($letter) > 128) {
                return $letter;
            }

            // To properly obfuscate the value, we will randomly convert each letter to
            // its entity or hexadecimal representation, keeping a bot from sniffing
            // the randomly obfuscated letters out of the string on the responses.
            switch (random_int(1, 3)) {
                case 1:
                    $safe .= '&#' . ord($letter) . ';';
                    break;

                case 2:
                    $safe .= '&#x' . dechex(ord($letter)) . ';';
                    break;

                case 3:
                    $safe .= $letter;
            }
        }

        return $safe;
    }

    /**
     * Generate a meta tag.
     */
    public function meta(string $name, string $content, array $attributes = []): HtmlString
    {
        $defaults = compact('name', 'content');

        $attributes = array_merge($defaults, $attributes);

        return $this->toHtmlString('<meta' . $this->attributes($attributes) . '>');
    }

    /**
     * Generate Html tag.
     */
    public function tag(string $tag, mixed $content, array $attributes = []): HtmlString
    {
        $content = is_array($content) ? implode('', $content) : $content;
        return $this->toHtmlString('<' . $tag . $this->attributes($attributes) . '>' . $this->toHtmlString($content) . '</' . $tag . '>');
    }

    /**
     * Transform the string to Html serializable object
     */
    protected function toHtmlString($html): HtmlString
    {
        return new HtmlString($html);
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @throws BadMethodCallException
     */
    public function __call(mixed $method, mixed $parameters): mixed
    {
        if (static::hasComponent($method)) {
            return $this->componentCall($method, $parameters);
        }

        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        throw new BadMethodCallException("Method {$method} does not exist.");
    }
}
