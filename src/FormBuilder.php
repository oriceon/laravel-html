<?php

namespace Oriceon\Html;

use BadMethodCallException;
use DateTime;
use DateTimeInterface;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Traits\Macroable;

class FormBuilder
{
    use Macroable, Componentable {
        Macroable::__call as macroCall;
        Componentable::__call as componentCall;
    }

    /**
     * The Html builder instance.
     */
    protected HtmlBuilder $html;

    /**
     * The Url generator instance.
     */
    protected UrlGenerator $url;

    /**
     * The View factory instance.
     */
    protected Factory $view;

    /**
     * The CSRF token used by the form builder.
     */
    protected ?string $csrfToken;

    /**
     * Consider Request variables while auto fill.
     */
    protected bool $considerRequest = false;

    /**
     * The session store implementation.
     */
    protected Session $session;

    /**
     * The current model instance for the form.
     */
    protected $model;

    /**
     * An array of label names we've created.
     */
    protected array $labels = [];

    protected ?Request $request;

    /**
     * The reserved form open attributes.
     */
    protected array $reserved = ['method', 'url', 'route', 'action', 'files'];

    /**
     * The form methods that should be spoofed in uppercase.
     */
    protected array $spoofedMethods = ['DELETE', 'PATCH', 'PUT'];

    /**
     * The types of inputs to not fill values on by default.
     */
    protected array $skipValueTypes = ['file', 'password', 'checkbox', 'radio'];


    /**
     * Input Type.
     */
    protected ?string $type = null;

    /**
     * Create a new form builder instance.
     */
    public function __construct(HtmlBuilder $html, UrlGenerator $url, Factory $view, ?string $csrfToken = null, ?Request $request = null)
    {
        $this->url       = $url;
        $this->html      = $html;
        $this->view      = $view;
        $this->csrfToken = $csrfToken;
        $this->request   = $request;
    }

    /**
     * Open up a new Html form
     */
    public function open(array $options = []): HtmlString
    {
        $method = Arr::get($options, 'method', 'post');

        // We need to extract the proper method from the attributes. If the method is
        // something other than GET or POST, we'll use POST since we will spoof the
        // actual method since forms don't support the reserved methods in Html.
        $attributes['method'] = $this->getMethod($method);

        // fix share from the blade that js cannot easily to get the form method #750
        $attributes['data-method'] = $method;

        $attributes['action'] = $this->getAction($options);

        $attributes['accept-charset'] = 'UTF-8';

        // If the method is PUT, PATCH or DELETE, we will need to add a spoofed hidden
        // field that will instruct the Symfony request to pretend the method is a
        // different method than it actually is, for convenience from the forms.
        $append = $this->getAppendage($method);

        if (isset($options['files']) && $options['files']) {
            $options['enctype'] = 'multipart/form-data';
        }

        // Finally, we're ready to create the final form HTML field.
        // We will attribute format the array of attributes.
        // We will also add on the appendage which is used to spoof requests for this PUT, PATCH, etc. methods on forms.
        $attributes = array_merge(
          $attributes, Arr::except($options, $this->reserved)
        );

        // Finally, we will concatenate all the attributes into a single string,
        // so we can build out the final form open statement.
        // We'll also append on an extra value for the hidden _method field if it's needed for the form.
        $attributes = $this->html->attributes($attributes);

        return $this->toHtmlString('<form' . $attributes . '>' . $append);
    }

    /**
     * Create a new model-based form builder.
     */
    public function model(mixed $model, array $options = []): HtmlString
    {
        $this->model = $model;

        return $this->open($options);
    }

    /**
     * Set the model instance on the form builder.
     */
    public function setModel(mixed $model): void
    {
        $this->model = $model;
    }

    /**
     * Get the current model instance on the form builder.
     */
    public function getModel(): mixed
    {
        return $this->model;
    }

    /**
     * Close the current form.
     */
    public function close(): HtmlString|string
    {
        $this->labels = [];

        $this->model = null;

        return $this->toHtmlString('</form>');
    }

    /**
     * Generate a hidden field with the current CSRF token.
     */
    public function token(): HtmlString|string
    {
        $token = ! empty($this->csrfToken) ? $this->csrfToken : $this->session->token();

        return $this->hidden('_token', $token);
    }

    /**
     * Create a form label element.
     */
    public function label(string $name, ?string $value = null, array $options = [], bool $escape_html = true): HtmlString
    {
        $this->labels[] = $name;

        $value = $this->formatLabel($name, $value);

        if ($escape_html) {
            $value = $this->html->entities($value);
        }

        return $this->toHtmlString('<label for="' . $name . '"' . $this->html->attributes($options) . '>' . $value . '</label>');
    }

    /**
     * Format the label value.
     */
    protected function formatLabel(string $name, ?string $value): string
    {
        return $value ?: ucwords(str_replace('_', ' ', $name));
    }

    /**
     * Create a form input field.
     */
    public function input(string $type, ?string $name = null, mixed $value = null, array $options = []): HtmlString
    {
        $this->type = $type;

        if ( ! isset($options['name'])) {
            $options['name'] = $name;
        }

        // We will get the appropriate value for the given field. We will look for the
        // value in the session for the value in the old input data then we'll look
        // in the model instance if one is set. Otherwise, we will just use empty.
        $id = $this->getIdAttribute($name, $options);

        if ( ! in_array($type, $this->skipValueTypes, true)) {
            $value = $this->getValueAttribute($name, $value);
        }

        // Once we have the type, value, and ID, we can merge them into the rest of the
        // attributes' array, so we can convert them into their Html attribute format
        // when creating the Html element. Then, we will return the entire input.
        $merge = compact('type', 'value', 'id');

        $options = array_merge($options, $merge);

        return $this->toHtmlString('<input' . $this->html->attributes($options) . '>');
    }

    /**
     * Create a text input field.
     */
    public function text(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->input('text', $name, $value, $options);
    }

    /**
     * Create a password input field.
     */
    public function password(string $name, array $options = []): HtmlString
    {
        return $this->input('password', $name, '', $options);
    }

    /**
     * Create a range input field.
     */
    public function range(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->input('range', $name, $value, $options);
    }

    /**
     * Create a hidden input field.
     */
    public function hidden(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->input('hidden', $name, $value, $options);
    }

    /**
     * Create a search input field.
     */
    public function search(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->input('search', $name, $value, $options);
    }

    /**
     * Create an e-mail input field.
     */
    public function email(string $name, ?string $value = null, array $options = []): HtmlString
    {
        return $this->input('email', $name, $value, $options);
    }

    /**
     * Create a tel input field.
     */
    public function tel(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->input('tel', $name, $value, $options);
    }

    /**
     * Create a number input field.
     */
    public function number(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->input('number', $name, $value, $options);
    }

    /**
     * Create a date input field.
     */
    public function date(string $name, mixed $value = null, array $options = []): HtmlString
    {
        // Pre-process date values #706
        $value ??= $this->getValueAttribute($name, $value);

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        return $this->input('date', $name, $value, $options);
    }

    /**
     * Create a datetime input field.
     */
    public function datetime(string $name, mixed $value = null, array $options = []): HtmlString
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTimeInterface::RFC3339);
        }

        return $this->input('datetime', $name, $value, $options);
    }

    /**
     * Create a datetime-local input field.
     */
    public function datetimeLocal(string $name, mixed $value = null, array $options = []): HtmlString
    {
        if ($value instanceof DateTime) {
            $value = $value->format('Y-m-d\TH:i');
        }

        return $this->input('datetime-local', $name, $value, $options);
    }

    /**
     * Create a time input field.
     */
    public function time(string $name, mixed $value = null, array $options = []): HtmlString
    {
        if ($value instanceof DateTime) {
            $value = $value->format('H:i');
        }

        return $this->input('time', $name, $value, $options);
    }

    /**
     * Create a url input field.
     */
    public function url(string $name, ?string $value = null, array $options = []): HtmlString
    {
        return $this->input('url', $name, $value, $options);
    }

    /**
     * Create a week input field.
     */
    public function week(string $name, mixed $value = null, array $options = []): HtmlString
    {
        if ($value instanceof DateTime) {
            $value = $value->format('Y-\WW');
        }

        return $this->input('week', $name, $value, $options);
    }

    /**
     * Create a file input field.
     */
    public function file(string $name, array $options = []): HtmlString
    {
        return $this->input('file', $name, null, $options);
    }

    /**
     * Create a textarea input field.
     */
    public function textarea(string $name, mixed $value = null, array $options = []): HtmlString
    {
        $this->type = 'textarea';

        if ( ! isset($options['name'])) {
            $options['name'] = $name;
        }

        // Next, we will look for the rows and cols' attributes, as each of these is put
        // on the textarea element definition. If they are not present, we will just
        // assume some correct default values for these attributes for the developer.
        $options = $this->setTextAreaSize($options);

        $options['id'] = $this->getIdAttribute($name, $options);

        $value = (string) $this->getValueAttribute($name, $value);

        unset($options['size']);

        // Next, we will convert the attributes into a string form.
        // Also, we have removed the size attribute, as it was merely a short-cut for the rows and cols on the element.
        // Then we'll create the final textarea elements Html for us.
        $optionsData = $this->html->attributes($options);

        return $this->toHtmlString('<textarea' . $optionsData . '>' . e($value, false). '</textarea>');
    }

    /**
     * Set the text area size on the attributes.
     */
    protected function setTextAreaSize(array $options): array
    {
        if (isset($options['size'])) {
            return $this->setQuickTextAreaSize($options);
        }

        // If the "size" attribute was not specified, we will just look for the regular
        // columns and rows attributes, using correct defaults if these do not exist on
        // the attributes' array.
        // We'll then return this entire options array back.
        $cols = Arr::get($options, 'cols', 50);

        $rows = Arr::get($options, 'rows', 10);

        return array_merge($options, compact('cols', 'rows'));
    }

    /**
     * Set the text area size using the quick "size" attribute.
     */
    protected function setQuickTextAreaSize(array $options): array
    {
        $segments = explode('x', $options['size']);

        return array_merge($options, ['cols' => $segments[0], 'rows' => $segments[1]]);
    }

    /**
     * Create a select box field.
     */
    public function select(
        string $name,
        array  $list = [],
        mixed  $selected = null,
        array  $selectAttributes = [],
        array  $optionsAttributes = [],
        array  $optgroupsAttributes = []
    ): HtmlString
    {
        $this->type = 'select';

        // When building a select box, the "value" attribute is really the selected one,
        // so we will use that when checking the model or session for a value which
        // should provide a convenient method of re-populating the forms on post.
        $selected = $this->getValueAttribute($name, $selected);

        $selectAttributes['id'] = $this->getIdAttribute($name, $selectAttributes);

        if ( ! isset($selectAttributes['name'])) {
            $selectAttributes['name'] = $name;
        }

        // We will simply loop through the options and build Html value for each of
        // them until we have an array of Html declarations. Then we will join them
        // all together into one single Html element that can be put on the form.
        $html = [];

        if (isset($selectAttributes['placeholder'])) {
            $html[] = $this->placeholderOption($selectAttributes['placeholder'], $selected);
            unset($selectAttributes['placeholder']);
        }

        foreach ($list as $value => $display) {
            $optionAttributes = $optionsAttributes[$value] ?? [];
            $optgroupAttributes = $optgroupsAttributes[$value] ?? [];
            $html[] = $this->getSelectOption($display, $value, $selected, $optionAttributes, $optgroupAttributes);
        }

        // Once we have all of this Html, we can join this into a single element after
        // formatting the attributes into Html "attributes" string, then we will
        // build out a final select statement, which will contain all the values.
        $selectAttributesData = $this->html->attributes($selectAttributes);

        $list = implode('', $html);

        return $this->toHtmlString("<select{$selectAttributesData}>{$list}</select>");
    }

    /**
     * Create a select range field.
     */
    public function selectRange(string $name, mixed $begin, mixed $end, mixed $selected = null, array $options = []): HtmlString
    {
        $range = array_combine($range = range($begin, $end), $range);

        return $this->select($name, $range, $selected, $options);
    }

    /**
     * Create a select year field.
     */
    public function selectYear(): mixed
    {
        return call_user_func_array([$this, 'selectRange'], func_get_args());
    }

    /**
     * Create a select month field.
     */
    public function selectMonth(string $name, mixed $selected = null, array $options = [], string $format = 'F'): HtmlString
    {
        $months = [];

        foreach (range(1, 12) as $month) {
            $months[$month] = DateTime::createFromFormat('!m', $month)->format($format);
        }

        return $this->select($name, $months, $selected, $options);
    }

    /**
     * Get the select option for the given value.
     */
    public function getSelectOption(mixed $display, mixed $value, mixed $selected, array $attributes = [], array $optgroupAttributes = []): HtmlString
    {
        if (is_iterable($display)) {
            return $this->optionGroup($display, $value, $selected, $optgroupAttributes, $attributes);
        }

        return $this->option($display, $value, $selected, $attributes);
    }

    /**
     * Create an option group form element.
     */
    protected function optionGroup(array $list, string $label, mixed $selected, array $attributes = [], array $optionsAttributes = [], int $level = 0): HtmlString
    {
        $html = [];

        $space = str_repeat('&nbsp;', $level);

        foreach ($list as $value => $display) {
            $optionAttributes = $optionsAttributes[$value] ?? [];
            if (is_iterable($display)) {
                $html[] = $this->optionGroup($display, $value, $selected, $attributes, $optionAttributes, $level + 5);
            } else {
                $html[] = $this->option($space.$display, $value, $selected, $optionAttributes);
            }
        }

        return $this->toHtmlString('<optgroup label="' . e($space.$label, false) . '"' . $this->html->attributes($attributes) . '>' . implode('', $html) . '</optgroup>');
    }

    /**
     * Create a select element option.
     */
    protected function option(mixed $display, string $value, mixed $selected = null, array $attributes = []): HtmlString
    {
        $selected = $this->getSelectedValue($value, $selected);

        $options = array_merge(['value' => $value, 'selected' => $selected], $attributes);

        $string = '<option' . $this->html->attributes($options) . '>';
        if ( ! is_null($display)) {
            $string .= e($display, false) . '</option>';
        }

        return $this->toHtmlString($string);
    }

    /**
     * Create a placeholder select element option.
     */
    protected function placeholderOption(mixed $display, mixed $selected = null): HtmlString
    {
        $selected = $this->getSelectedValue(null, $selected);

        $options = [
            'selected' => $selected,
            'value'    => '',
            'hidden'   => true, // Fix the placeholder in the select form to prevent it from being selected #755
        ];

        return $this->toHtmlString('<option' . $this->html->attributes($options) . '>' . e($display, false) . '</option>');
    }

    /**
     * Determine if the value is selected.
     */
    protected function getSelectedValue(mixed $value, mixed $selected = null): mixed
    {
        if (is_array($selected)) {
            return in_array($value, $selected, false) || in_array((string) $value, $selected, false) ? 'selected' : null;
        }

        if ($selected instanceof Collection) {
            return $selected->contains($value) ? 'selected' : null;
        }

        return ((string) $value === (string) $selected) ? 'selected' : null;
    }

    /**
     * Create a checkbox input field.
     */
    public function checkbox(string $name, mixed $value = 1, ?bool $checked = null, array $options = []): HtmlString
    {
        return $this->checkable('checkbox', $name, $value, $checked, $options);
    }

    /**
     * Create a radio button input field.
     */
    public function radio(string $name, mixed $value = null, ?bool $checked = null, array $options = []): HtmlString
    {
        if (is_null($value)) {
            $value = $name;
        }

        return $this->checkable('radio', $name, $value, $checked, $options);
    }

    /**
     * Create a checkable input field.
     */
    protected function checkable(string $type, string $name, mixed $value, ?bool $checked, array $options = []): HtmlString
    {
        $this->type = $type;

        $checked = $this->getCheckedState($type, $name, $value, $checked);

        if ($checked) {
            $options['checked'] = 'checked';
        }

        return $this->input($type, $name, $value, $options);
    }

    /**
     * Get the check state for a checkable input.
     */
    protected function getCheckedState(string $type, string $name, mixed $value, ?bool $checked): ?bool
    {
        return match ($type) {
            'checkbox' => $this->getCheckboxCheckedState($name, $value, $checked),
            'radio' => $this->getRadioCheckedState($name, $value, $checked),
            default => $this->compareValues($name, $value),
        };
    }

    /**
     * Get the check state for a checkbox input.
     */
    protected function getCheckboxCheckedState(string $name, mixed $value, ?bool $checked): ?bool
    {
        $request = $this->request($name);

        if ( ! $request && isset($this->session) && ! $this->oldInputIsEmpty() && is_null($this->old($name))) {
            return false;
        }

        if (is_null($request) && $this->missingOldAndModel($name)) {
            return $checked;
        }

        $posted = $this->getValueAttribute($name, $checked);

        if (is_array($posted)) {
            return in_array($value, $posted, false);
        }

        if ($posted instanceof Collection) {
            return $posted->contains('id', $value);
        }

        return (bool) $posted;
    }

    /**
     * Get the check state for a radio input.
     */
    protected function getRadioCheckedState(string $name, mixed $value, ?bool $checked): ?bool
    {
        $request = $this->request($name);

        if ( ! $request && $this->missingOldAndModel($name)) {
            return $checked;
        }

        return $this->compareValues($name, $value);
    }

    /**
     * Determine if the provided value loosely compares to the value assigned to the field.
     * Use loose comparison because Laravel model casting may be in effect and therefore
     * 1 == true and 0 == false.
     */
    protected function compareValues(string $name, mixed $value): bool
    {
        return $this->getValueAttribute($name) == $value;
    }

    /**
     * Determine if old input or model input exists for a key.
     */
    protected function missingOldAndModel(string $name): bool
    {
        return (is_null($this->old($name)) && is_null($this->getModelValueAttribute($name)));
    }

    /**
     * Create Html reset input element.
     */
    public function reset(string $value, array $attributes = []): HtmlString
    {
        return $this->input('reset', null, $value, $attributes);
    }

    /**
     * Create Html image input element.
     */
    public function image(string $url, string $name, array $attributes = []): HtmlString
    {
        $attributes['src'] = $this->url->asset($url);

        return $this->input('image', $name, null, $attributes);
    }

    /**
     * Create a month input field
     */
    public function month(string $name, mixed $value = null, array $options = []): HtmlString
    {
        if ($value instanceof DateTime) {
            $value = $value->format('Y-m');
        }

        return $this->input('month', $name, $value, $options);
    }

    /**
     * Create a color input field.
     */
    public function color(string $name, ?string $value = null, array $options = []): HtmlString
    {
        return $this->input('color', $name, $value, $options);
    }

    /**
     * Create a submit button element.
     */
    public function submit(?string $value = null, array $options = []): HtmlString
    {
        return $this->input('submit', null, $value, $options);
    }

    /**
     * Create a button element.
     */
    public function button(?string $value = null, array $options = []): HtmlString
    {
        if ( ! array_key_exists('type', $options)) {
            $options['type'] = 'button';
        }

        return $this->toHtmlString('<button' . $this->html->attributes($options) . '>' . $value . '</button>');
    }

    /**
     * Create a datalist box field.
     */
    public function datalist(string $id, array $list = []): HtmlString
    {
        $this->type = 'datalist';

        $attributes['id'] = $id;

        $html = [];

        if ($this->isAssociativeArray($list)) {
            foreach ($list as $value => $display) {
                $html[] = $this->option($display, $value);
            }
        } else {
            foreach ($list as $value) {
                $html[] = $this->option($value, $value);
            }
        }

        $attributes = $this->html->attributes($attributes);

        $list = implode('', $html);

        return $this->toHtmlString("<datalist{$attributes}>{$list}</datalist>");
    }

    /**
     * Determine if an array is associative.
     */
    protected function isAssociativeArray(array $array): bool
    {
        return ! array_is_list($array);
    }

    /**
     * Parse the form action method.
     */
    protected function getMethod(string $method): string
    {
        $method = strtoupper($method);

        return $method !== 'GET' ? 'POST' : $method;
    }

    /**
     * Get the form action from the options.
     */
    protected function getAction(array $options = []): string
    {
        // We will also check for a "route" or "action" parameter on the array so that
        // developers can easily specify a route or controller action when creating
        // a form providing a convenient interface for creating the form actions.
        if (isset($options['url'])) {
            return $this->getUrlAction($options['url']);
        }

        if (isset($options['route'])) {
            return $this->getRouteAction($options['route']);
        }

        // If an action is available, we are attempting to open a form to a controller
        // action route. So, we will use the URL generator to get the path to these
        // actions and return them from the method. Otherwise, we'll use current.
        if (isset($options['action'])) {
            return $this->getControllerAction($options['action']);
        }

        return $this->url->current();
    }

    /**
     * Get the action for an "url" option.
     */
    protected function getUrlAction(array|string $options = []): string
    {
        if (is_array($options)) {
            return $this->url->to($options[0], array_slice($options, 1));
        }

        return $this->url->to($options);
    }

    /**
     * Get the action for a "route" option.
     */
    protected function getRouteAction(array|string $options = []): string
    {
        if (is_array($options)) {
            $parameters = array_slice($options, 1);

            if (array_keys($options) === [0, 1]) {
                $parameters = head($parameters);
            }

            return $this->url->route($options[0], $parameters);
        }

        return $this->url->route($options);
    }

    /**
     * Get the action for an "action" option.
     */
    protected function getControllerAction(array|string $options = []): string
    {
        if (is_array($options)) {
            return $this->url->action($options[0], array_slice($options, 1));
        }

        return $this->url->action($options);
    }

    /**
     * Get the form appendage for the given method.
     */
    protected function getAppendage(string $method): string
    {
        [$method, $appendage] = [strtoupper($method), ''];

        // If the HTTP method is in this list of spoofed methods,
        // we will attach the method spoofed hidden input to the form.
        // This allows us to use regular form to initiate PUT and DELETE requests in addition to the typical.
        if (in_array($method, $this->spoofedMethods, true)) {
            $appendage .= $this->hidden('_method', $method);
        }

        // If the method is something other than GET, we will go ahead and attach the
        // CSRF token to the form, as this can't hurt and is convenient to simply
        // always have available on every form the developers created for them.
        if ($method !== 'GET') {
            $appendage .= $this->token();
        }

        return $appendage;
    }

    /**
     * Get the ID attribute for a field name.
     */
    public function getIdAttribute(?string $name = null, array $attributes = []): ?string
    {
        if (array_key_exists('id', $attributes)) {
            return $attributes['id'];
        }

        if (in_array($name, $this->labels, true)) {
            return $name;
        }

        return null;
    }

    /**
     * Get the value that should be assigned to the field.
     */
    public function getValueAttribute(?string $name = null, mixed $value = null): mixed
    {
        if (is_null($name)) {
            return $value;
        }

        $old = $this->old($name);

        if ( ! is_null($old) && $name !== '_method') {
            return $old;
        }

        if (function_exists('app')) {
            $hasNullMiddleware = app(\Illuminate\Contracts\Http\Kernel::class)
                ->hasMiddleware(\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class);

            if ($hasNullMiddleware
                && is_null($old)
                && is_null($value)
                && ! is_null($this->view->shared('errors'))
                && count(is_countable($this->view->shared('errors')) ? $this->view->shared('errors') : []) > 0
            ) {
                return null;
            }
        }

        $request = $this->request($name);
        if ( ! is_null($request) && $name !== '_method') {
            return $request;
        }

        if ( ! is_null($value)) {
            return $value;
        }

        if (isset($this->model)) {
            return $this->getModelValueAttribute($name);
        }

        return null;
    }

    /**
     * Take Request in a fill process
     */
    public function considerRequest(bool $consider = true): void
    {
        $this->considerRequest = $consider;
    }

    /**
     * Get value from current Request
     */
    protected function request(string $name): array|null|string
    {
        if ( ! $this->considerRequest) {
            return null;
        }

        if ( ! isset($this->request)) {
            return null;
        }

        return $this->request->input($this->transformKey($name));
    }

    /**
     * Get the model value that should be assigned to the field.
     */
    protected function getModelValueAttribute(string $name): mixed
    {
        $key = $this->transformKey($name);

        if ((is_string($this->model) || is_object($this->model)) && method_exists($this->model, 'getFormValue')) {
            return $this->model->getFormValue($key);
        }

        return data_get($this->model, $key);
    }

    /**
     * Get a value from the session's old input.
     */
    public function old(string $name): mixed
    {
        if (isset($this->session)) {
            $key = $this->transformKey($name);
            $payload = $this->session->getOldInput($key);

            if ( ! is_array($payload)) {
                return $payload;
            }

            if ( ! in_array($this->type, ['select', 'checkbox'], true)) {
                if ( ! isset($this->payload[$key])) {
                    $this->payload[$key] = collect($payload);
                }

                if ( ! empty($this->payload[$key])) {
                    return $this->payload[$key]->shift();
                }
            }

            return $payload;
        }
    }

    /**
     * Determine if the old input is empty.
     */
    public function oldInputIsEmpty(): bool
    {
        return (isset($this->session) && count((array) $this->session->getOldInput()) === 0);
    }

    /**
     * Transform key from array to dot syntax.
     */
    protected function transformKey(string $key): string|array
    {
        return str_replace(['.', '[]', '[', ']'], ['_', '', '.', ''], $key);
    }

    /**
     * Transform the string to Html serializable object
     */
    protected function toHtmlString($html): HtmlString
    {
        return new HtmlString($html);
    }

    /**
     * Get the session store implementation.
     */
    public function getSessionStore(): Session
    {
        return $this->session;
    }

    /**
     * Set the session store implementation.
     */
    public function setSessionStore(Session $session): static
    {
        $this->session = $session;

        return $this;
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
