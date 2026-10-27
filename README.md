# ATMF-PHP [![Tweet](https://img.shields.io/twitter/url/http/shields.io.svg?style=social)](https://twitter.com/intent/tweet?text=Build%20complex%20localized%20%23PHP%20web%20solutions%20with%20%23ATMF%0A%0A&url=https://github.com/skito/ATMF-PHP)

__PHP support for ATMF (Advanced-Template-Markup-Format)__

![PHP7 or greater](https://img.shields.io/badge/php-%3E%3D7-blue)
![Production ready](https://img.shields.io/badge/production-ready-green)
![Coverage 100%](https://img.shields.io/badge/coverage-100%25-green)

![Cover](cover.png)
 
# More about ATMF
Full specificaiton of the format is [available here](https://github.com/skito/ATMF)

# Setup
Copy the ``atmf`` folder in your project and then simply include the ``engine.php`` file.
```php
<?php
require_once(__DIR__.'/atmf/engine.php');
$atmf = new \ATMF\Engine();
// ...
$atmf->Rend();
```

# Usage Back-End
__Global selector ``__()``__

```php
<?php

// Assign some templates
__('#template master', file_get_contents('./templates/master.html'));
__('#template page', file_get_contents('./templates/template.html'));

// Assign variables
__('$fullname', 'Advanced-Template-Markup-Format');
__('$shortname', 'ATMF');
__('$pagetitle', '{$fullname " (" $shortname ")"}');
__('$slogan', 'Cultural made easy!');
__('$userData', __escape('{$crossScripting}'));
```

__Native__
```php
<?php

$atmf->vars['slogan'] = 'Cultural made easy!';
$atmf->SetTemplate('header', file_get_contents('./templates/header.html'));
```

__Read variables and translations__
```php
__('$fullname'); //Output: Advanced Template Markup Format
__('$pagetitle'); //Output: $fullname ($shortname)
__('@page.theFox 12 red'); //Output: The red fox made 12 steps
```

__Configuration Options__
```php
$atmf->SetCultureFolder(__DIR__.'/culture'); //Default: culture
$atmf->SetCulture('bg-BG'); // Default: en-US
$atmf->SetTemplateDiscoveryPath(__DIR__.'/templates'); // Default: No auto discovery
$atmf->allowGlobals = true; // Default: FALSE. Be careful with that if set it to TRUE!
```

# Usage Front-End (templates)
__Variables__
```html
<h1>{$pageTitle}</h1>
```

__Language resources__
```html
<h1>{@page.title}</h1>
<h1>{@page.theFox 10 red}</h1>
```

__Functions - #each #if #elseif #else #end__
```html
<table style="min-width:500px" cellspacing="10">
    <tr style="font-weight:bold">
        <td>Author Name</td>
        <td>Books</td>
        <td>Sold Out</td>
    </tr>
    {#each $authors as $author}
        <tr>
            <td>
                {$author.firstName " " $author.lastName}
            </td>
            <td>
                <ul>
                {#each $author.books as $book}
                    <li>{$book}</li>
                {#end}
                </ul>
            </td>
            <td>
                {#if $author.soldOut}
                    <span style="color:red">Sold out</span>
                {#else}
                    <span style="color:green">In stock</span>
                {#end}
            </td>
        </tr>
    {#end}
</table>
```

__Conditions - #elseif, comparisons, ! && ||__
```html
{#if $cart.total >= 100}
    Free shipping
{#elseif $cart.total >= 50}
    Shipping: {$reducedShippingPrice}
{#else}
    Shipping: {$shippingPrice}
{#end}

{#if $user.role == "admin" || $user.role == editor}...{#end}
{#if $stock > 0 && !$discontinued}...{#end}
{#if $items > 3}...{#end} <!-- Arrays are compared by the number of items -->
{#if {/my-extension} != ""}...{#end}
```
Supported operators are ``==``, ``!=``, ``>``, ``<``, ``>=`` and ``<=``. Operands can be variables, language resources, extensions, ``{...}`` blocks, numbers, ``true``, ``false``, ``null`` and text. Use quotes for text with spaces or special characters. ``&&`` takes precedence over ``||``. Operands without ``&&`` or ``||`` between them are joined with ``&&``.

__Extensions__
```html
<h1>Today date is {/date "M d, Y"}</h1>
```

__Default values with ??__
```html
<h1>{@welcome ?? Welcome}</h1>
<span>{$totalSum ?? 5}</span>
<div>{/my-extension ?? "No results"}</div>
<div>{$nickname ?? $firstName ?? "Guest"}</div>
<div>{$title ?? "Dear " $firstName}</div>
<div>{$question "??"}</div> <!-- Quotes escape the operator -->
```
The default is used when the value on the left renders empty - not set, ``null``, empty string, ``false`` or empty array. Zero is a value. Everything left of ``??`` is the value, including the appended arguments. Everything right of it is the default, appended the same way. Use ``{...}`` to apply a default to a nested tag only, e.g. ``{/upper {$name ?? "guest"}}``.

__Escaping with backslash__
```html
<h1>\{@page.title}</h1>
```

Full demo available at ``index.php`` and the ``templates`` folder

# Custom Extensions
You can make your own custom extensions by exending the ``\ATMF\Extension`` interface.

```php
<?php
class MyCustomExtension implements \ATMF\Extension
{
    public $str = "My custom extension";
    public function __construct() {}
    
    public function Get($args) 
    {
        return $this->str;
    }
    
    public function Set($args, $value) 
    {
        $this->str = $value;
    }
}
\ATMF\Extensions::Register('mycustom', new MyCustomExtension());
```

The template:
```html
<div>
    <span>{/mycustom}</span> <!-- OUTPUT: My custom extension -->
</div>
```

The backend:
```php
<?php
__('/mycustom') // Returns "My custom extension"
__('/mycustom', 'Another custom value') // Change the value
__('/mycustom') // Returns "Another custom value"
```

For more advanced example with arguments check the date core extension inside ``atmf/core/ext/date``


