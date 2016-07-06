# DeferredExceptions

## Introduction

DeferredExceptions trait provides a clean, fluent possibility to choose: to throw
an exception now, later or simply handle errors. Also it accumulate exceptions
of all classes that use this trait.

## License

DeferredExceptions is open-sourced software licensed under the [GPL-3.0] (https://www.gnu.org/licenses/gpl-3.0.en.html).

### Usage

Let's say we have a problem with the class code.
Transform it as follows:

```php
<?php

namespace My;

use Seotils\Traits\DeferredExceptions;

/**
 * Add an exception class to clearly understand
 * the class of the error occurred
 */
class SomeClassException extends \Exception {

}

class SomeClass {
    use DeferredExceptions;

    public function troubles(){
        try {
            // A problem code ..
        } catch {
            // Old behavior:
            // throw new SomeClassException('Oh no!');

            // Now:
            $this->exception('Oh no!');
        }
    }
}

```

And use it:

```php
<?php
...
foreach( $list as $item){
    /* @var $item \My\SomeClass */

    // Don't throws exceptions
    $item->useExceptions( false );
    ...
    // Calls a "bad" functions
    $item->troubles();
    $item->anotherTroubles();
    $item->andTroubles();
    ...
    // throws last exception was occured
    $item->throwLast();

    // throws an exception with list of all exception was occured
    // using custom template of each item in list
    $item->throwAll( true, '::class ::code ::message');

}
...
// throws exception with list of all exceptions of all derived classes.
DeferredExceptions::throwGlobal();
// or just get a list of exceptions for manual processing
$exceptions = DeferredExceptions::getGlobalExceptions();

```

That`s all!
