# DeferredExceptions

## Introduction

DeferredExceptions trait provides a clean, fluent possibility to choose: to throw
an exception now, later or simply handle errors. Also it accumulate exceptions
of all classes that use this trait.

## Description

Sometimes you need to call different functions in sequence and be able to see what exceptions they throw.

Usually if one function throws one exception, you cannot see what exceptions the other functions would have thrown.

This package provides a trait that allows you to queue multiple exceptions thrown by classes that use the trait.
This way you can have access to the whole list of thrown exceptions throw the last or all exceptions that happened.

## License

DeferredExceptions is open-sourced software licensed under the [GPL-3.0] (https://www.gnu.org/licenses/gpl-3.0.en.html).

## Install

```bash
composer require seotils/deferred-exceptions
composer selfupdate
composer update
```

## Usage

Let's say we have a problem with the class code.
Transform it as follows:

```php
<?php

namespace My;

use Seotils\Traits\DeferredExceptions;

/**
 * Exception class to clearly understand
 * the class of the error occurred (will be autodetected)
 */
class SomeClassException extends \Exception {

}

/**
 * Custom exception class (must be to passed as last argument to the ::exception() function)
 */
class MyCustomException extends \Exception {

}

class SomeClass {
    use DeferredExceptions;

    public function troubles(){
        try {
            // A problem code ..
        } catch( Exception $exc ) {
            // Old behavior:
            // throw new SomeClassException('Oh no!', 0, $exc);

            // Short syntax. Exception class will be detected as `My\SomeClassException`
            $this->exception('Oh no!');

            // Full syntax
            $this->exception(
                'Oh no!', // Error message
                0,        // Error code
                // Previous exception.
                // Used if only exception are thrown immediately with ::useExceptions( true )
                $exc, // Default: null
                // Custom exception class name
                'My\MyCustomException'
            );
        }
    }

    public function anotherTroubles(){
        // You can clear exception stack
        // NOTE. ::releaseExceptions() do not clean global stack of an `DeferredException` class !!!
        //       You must to use ::releaseGlobalExceptions()
        $this->releaseExceptions();
        try {
            // A problem code ..
        } catch( Exception $exc ) {
            ...
        }
    }

    public function andTroubles(){
        // You can detect if an instance of the class has deffered exceptions
        if( $this->hasExceptions() ){
            // Do somthing. For example assign default properties.
        }
        try {
            // A problem code ..
        } catch( Exception $exc ) {
            ...
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

    // Don't throws an exceptions.
    $item->useExceptions( false );
    ...
    // Calls a "bad" functions
    $item->troubles();
    $item->anotherTroubles();
    $item->andTroubles();
    ...
    // throws last exception was occured
    $item->throwLast(
        true // Release last exception in stack after throw
    );

    // throws an exception with a list of all exception was occured
    // using custom template of each item in the list
    $item->throwAll(
        true, // Release exception stack after throw
        // Template
        '`::class` exception was thrown at ::microtime with code #::code and say "::message"'
    );

    // Get an array of all exceptions of this class instance
    $errors = $item->getExceptions();
    foreach( $errors as $error)
    {
        $error ['time'];    // Microtime of an exception
        $error ['class'];   // Class of an exception
        $error ['code'];    // Error code
        $error ['message']; // Error message
    }
}
...
// If some exeptions was occured
if( DeferredExceptions::hasGlobalExceptions() ) {
    // Throws exception with a list of all exceptions of all derived classes.
    DeferredExceptions::throwGlobal(
        true, // Release exception stack after throw
        // Template
        '`::class` exception was thrown at ::microtime with code #::code and say "::message"')
    );
    // Or just get a list of exceptions for manual processing
    $errors = DeferredExceptions::getGlobalExceptions();
    foreach( $errors as $error)
    {
        $error ['time'];    // Microtime of an exception
        $error ['class'];   // Class of an exception
        $error ['code'];    // Error code
        $error ['message']; // Error message
    }
}

// Now we can clear the global exception stack and try another sequence
// NOTE. ::releaseGlobalExceptions() do not clear exceptions stacks of instances of a `DeferredException` class !!!
//       You must to use ::releaseExceptions() for each instance if you are want to use these
//       class instanses in second time with a clean exceptions history.
DeferredExceptions::releaseGlobalExceptions();
foreach( $secondList as $item){
    /* @var $item \My\SomeClass */
    // Don't throws an exceptions.
    $item->useExceptions( false );

    // If $item was used in the previous sequence,
    // then clear the exceptions stack of an instance
    if( $item->hasExceptions()){
        $item->releaseExceptions();
    }
    ...
    // Calls a "bad" functions
    $item->troubles();
    $item->anotherTroubles();
    $item->andTroubles();
    ...
}
...

```

That`s all!
