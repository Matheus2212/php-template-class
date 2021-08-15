# PHP Template Engine

A PHP Engine for templates. It uses MVC.

## Overview

Simple PHP template engine inspired by PUG.js and My Own experiences.

---

## How to use

It uses a MVC style to render pages.
The view files are usually .html files. I didn't tested with the .phtml ones (yet).

### Instantiate Class

First, we **must** create a new class instance using:

```php
$layout = new Template();
```

The `__construct` method of this class accepts an array within options like:

```php
$layout = new Template(array("dir" => "our/dir/to/views", "file" => "which_file_to_load.html", "vars" => array("var_key"=>"var_value")));
```

Yay! We made a new class instance!

But wait... Whats the purpose of this `"vars"` ?

### Setting vars on HTML file

We can add reference for vars on the HTML files. It uses the engine syntax for it. The engine will put the var automatically on the var reference.

You can use:

```html
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{title}}</title>
    <!-- One method for var reference -->
  </head>
  <body>
    {{$nav}}
    <!-- Another method for var reference -->

    <_template:body_var/>
    <!-- Another method for var reference -->

    <_template:$footer_var/>
    <!-- Another method for var reference -->
  </body>
</html>
```

And you have two methods to add vars on the engine:

- The array within the new class instance (intializing);
- The method `addVar`, like `$layout->addVar("var_key","var_value")`.

Oh yeah! You can render the template with `$layout->render()` or `$layout->render(true)`. The argument will "echo" the result on the page.

**ATTENTION**: Note that the _var reference_ is the _var_key_ on the definition.

### Managing Blocks

Sometimes we just want to use one single file to keep repeating data within the block HTML. To achieve this, we first need to use the `getBlock` method, like this:

```php
$layout = new Template();
$layout->loadFile("path/to/file.html");
$block = $layout->getBlock("loop"); // loop block must exists on the file
if($block){ // important to check if the block really exists on the file
        // from now on, we have a template block
        if(true){
                $block->loadFile("path/to/block/file.html"); // we can load an specific file
        }else{
                // we can just ignore it. The file HTML will be what was inserted on the parent file
        }
        $block->setBlock(array("var_key"=>"var_value")); // we can pass a new array to render the template partial within our data (arrays for additional information)
        $layout->setBlock($block); // now we pass back the block we've got to the original template file to render it (the block itself to render)
}
```

And the best: we can set **_MULTIPLE CHILD BLOCKS, WITHIN MULTIPLE BLOCKS_**!

You can set blocks with `<_template:block(blockName)></_template:block>`. **ATTENTION** please remember to give a _name_ for the blocks. Each name should be unique (for now).

Here's an example:

```html
<_template:block(childBlock)>
  <!-- here I can use as block the HTML I'll insert here, or I can load a HTML file -->
</_template:block>
```

You can also UNSET blocks. For this, just user: `$layout->unsetBlock(blockName)`.

---

### And does it have any condditional operations?

Yes it have! We can set `Ifs` for the template. Of course, _false_ conditions will remove the whole code inside a IF block in the end.

Here's how we can set it:

- On the HTML file:

```html
<_template:if(hasVar)>
        Some code here.
</_template:if>
```

- On PHP

```php
$vars = array(
  "title" => "Title",
);
$layout->setIf("hasVar",(isset($vars)));
```

Wow, simple... Thats just it? Yes.

### I want to add a custom code there. How can I do it?

Custom codes will be setted **at the end of the to be rendered HTML**. With the `code` method, you can insert more blocks or ifs or vars, and it will be rendered on the end.

Use it like this:

```php
$layout->code("My custom <b>code</b> <em>here</em>");
```

### Okay. You explained almost everything so far, but can we use functions?

Of course!

We can both:

- Set a var with the function output
- Use the `code` to insert a result of a function
- Use the template tag to automatically call a function, like this: `<_template:function.time()/>` or this: `<_template:function.time/>` Or even so: `<_template:function.date(Y-m-d)/>`

### One more thing.

To make things easier and faster, you can automatically sets **_many_** template operations only on the HTML.
For example: You can automatically call `loadFile`:

`<_template:loadFile(path/to/file.html)>`

---

Enjoy! 

If you find any bug, please report.