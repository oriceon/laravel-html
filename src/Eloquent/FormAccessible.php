<?php

namespace Oriceon\Html\Eloquent;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;

trait FormAccessible
{
    /**
     * A cached ReflectionClass instance for $this
     */
    protected ReflectionClass $reflection;

    public function getFormValue(string $key): mixed
    {
        $value = $this->getAttributeFromArray($key);

        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        if ( ! is_null($value) && in_array($key, $this->getDates(), true)) {
            $value = $this->asDateTime($value);
        }

        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasFormMutator($key)) {
            return $this->mutateFormAttribute($key, $value);
        }

        $keys = explode('.', $key);

        if ($this->isNestedModel($keys[0])) {
            $relatedModel = $this->getRelation($keys[0]);

            unset($keys[0]);
            $key = implode('.', $keys);

            if ($key !== '' && method_exists($relatedModel, 'hasFormMutator') && $relatedModel->hasFormMutator($key)) {
                return $relatedModel->getFormValue($key);
            }

            return data_get($relatedModel, empty($key) ? null : $key);
        }

        // No form mutator, let the model resolve this
        return data_get($this, $key);
    }

    /**
     * Check for a nested model.
     */
    public function isNestedModel(string $key): bool
    {
        return array_key_exists($key, $this->getRelations());
    }

    public function hasFormMutator($key): bool
    {
        $methods = $this->getReflection()->getMethods(ReflectionMethod::IS_PUBLIC);

        $mutator = collect($methods)
          ->first(function (ReflectionMethod $method) use ($key) {
              return $method->getName() === 'form' . Str::studly($key) . 'Attribute';
          });

        return (bool) $mutator;
    }

    private function mutateFormAttribute($key, $value): mixed
    {
        return $this->{'form' . Str::studly($key) . 'Attribute'}($value);
    }

    /**
     * Get a ReflectionClass Instance
     */
    protected function getReflection(): ReflectionClass
    {
        return $this->reflection;
    }
}
