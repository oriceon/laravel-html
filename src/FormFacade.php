<?php

namespace Oriceon\Html;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Oriceon\Html\FormBuilder
 */
class FormFacade extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'form';
    }
}
