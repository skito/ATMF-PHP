# ATMF-PHP [![Tweet](https://img.shields.io/twitter/url/http/shields.io.svg?style=social)](https://twitter.com/intent/tweet?text=Build%20complex%20localized%20%23PHP%20web%20solutions%20with%20%23ATMF%0A%0A&url=https://github.com/skito/ATMF-PHP)

__PHP support for ATMF (Advanced-Template-Markup-Format)__

![GitHub release (latest by date)](https://img.shields.io/badge/php-%3E%3D7-blue)
![GitHub release (latest by date)](https://img.shields.io/badge/production-ready-green)
![GitHub release (latest by date)](https://img.shields.io/badge/coverage-100%25-green)

&nbsp;
 
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
__('#template master', 'templates/master.html');
__('#template page', 'templates/template.html');

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
$atmf->SetTemplate('header', 'templates/header.html');
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
$atmf->DiscoverTemplates(__DIR__.'/templates'); // Default: No auto discovery
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

__Functions - #each #if #else #end__
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

__Extensions__
```html
<h1>Today date is {/date "M d, Y"}</h1>
```

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


