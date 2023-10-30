<?php

namespace Oriceon\Html;

use BadMethodCallException;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

trait Componentable
{
    /**
     * The registered components.
     */
    protected static array $components = [];

    /**
     * Register a custom component.
     */
    public static function component(string $name, $view, array $signature): void
    {
        static::$components[$name] = compact('view', 'signature');
    }

    /**
     * Check if a component is registered.
     */
    public static function hasComponent(string $name): bool
    {
        return isset(static::$components[$name]);
    }

    /**
     * Render a custom component.
     */
    protected function renderComponent(string $name, array $arguments): HtmlString
    {
        $component = static::$components[$name];
        $data = $this->getComponentData($component['signature'], $arguments);

        return new HtmlString(
          $this->view->make($component['view'], $data)->render()
        );
    }

    /**
     * Prepare the component data, while respecting provided defaults.
     */
    protected function getComponentData(array $signature, array $arguments): array
    {
        $data = [];

        $i = 0;
        foreach ($signature as $variable => $default) {
            // If the "variable" value is actually a numeric key, we can assume that
            // no default had been specified for the component argument and we'll
            // just use null instead, so that we can treat them all the same.
            if (is_numeric($variable)) {
                $variable = $default;
                $default = null;
            }

            $data[$variable] = Arr::get($arguments, $i, $default);

            $i++;
        }

        return $data;
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters): HtmlString
    {
        if (static::hasComponent($method)) {
            return $this->renderComponent($method, $parameters);
        }

        throw new BadMethodCallException("Method {$method} does not exist.");
    }
}
