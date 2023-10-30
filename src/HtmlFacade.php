<?php

namespace Oriceon\Html;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Oriceon\Html\HtmlBuilder
 */
class HtmlFacade extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'html';
    }
}
