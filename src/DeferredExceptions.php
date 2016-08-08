<?php
/**
 * DeferredExceptions trait allows you to choose: to throw an exception now,
 * later or simply handle errors. Also it accumulate exceptions of all classes
 * wich use this trait.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GPL-3.0
 * @author deMagog <seotils@gmail.com>
 *
 */

namespace Seotils\Traits;

/*
 * Exception-type
 */
class DeferredExceptionsException extends \Exception {}

/**
  * Local storage for a proper use of static variables in a trait
  */
abstract class DeferredExceptionsGlobal {

  /**
   * Cached classes
   *
   * @var array
   */
  public static $defExcClasses = [];

  /**
   * Global stack of accumulated exceptions
   *
   * @var array
   */
  public static $defExcErrorsGlobal = [];

  /**
   * Default template for an errors list item
   *
   * @var string
   * @see Seotils\Traits\DeferredExceptions::throwAll()
   */
  public static $defExcDefaultErrorListTemplate
      = "::time Exception: `::class`, code: #::code, message: ::message";

}

/**
 * Allows you to choose: to throw an exception now,
 * later or simply handle errors. Also it accumulate exceptions
 * of all classes wich use this trait.
 */
trait DeferredExceptions {

  /**
   * Stack of an exceptions for a current instance
   *
   * @var array
   */
  protected $defExcErrors = [];

  /**
   * Message for a last occuried exception
   *
   * @var string
   */
  protected $defExcLastErrorMessage = null;

  /**
   * Error code for a last occuried exception
   *
   * @var int
   */
  protected $defExcLastError = null;

  /**
   * Throw exception or save it
   *
   * @var boolean
   */
  protected $defExcUseExceptions = true;

  /**
   * Returns and sets( in defined $useExceptions) the option `useExceptions`:
   * throw an exceptions or save to stack
   *
   * @param boolean $useExceptions Throw exception or save to stack
   *
   * @return boolean
   */
  public function useExceptions( $useExceptions = 'undefined' ){
    if( 'undefined' !== $useExceptions) {
      $this->defExcUseExceptions = (bool) $useExceptions;
    }
    return $this->defExcUseExceptions;
  }

  /**
   * Check the class for existance and inheritance from the \Exception class
   *
   * @param string $className Class name to check
   * @return mixed Class name or NULL
   */
  protected function checkClassForCompability( $className ) {
    $result = $className;
    if( isset( DeferredExceptionsGlobal::$defExcClasses [$className])){
       if( ! DeferredExceptionsGlobal::$defExcClasses [$className] ){
         $result = null;
       }
    } else {
      if( ! class_exists( $className ) || ! is_subclass_of( $className, 'Exception')) {
        DeferredExceptionsGlobal::$defExcClasses [$className] = false;
        $result = null;
      } else {
        DeferredExceptionsGlobal::$defExcClasses [$className] = true;
      }
    }
    return $result;
  }

  /**
   * Returns the class to throw exception
   *
   * @param string $className The desired class to use
   * @return string
   */
  protected function getCompatibleClass( $className )
  {
    if( ! $className || ! is_string( $className )){
      $className = get_class();
    }
    $class = $className;

    if( ! $this->checkClassForCompability( $class )){
      $class = $this->checkClassForCompability( $class .'Exception' );
    }

    if( ! $class ) {
      if( $parent = get_parent_class( $className ) ) {
        $class = $this->getCompatibleClass( $parent );
      } else {
        $class = __NAMESPACE__ .'\DeferredExceptionsException';
      }
    }

    return $class;
  }


  /**
   * Throws the Exception or save last error
   *
   * @param string $message Exception error message.
   * @param int $code Exception error code. Default 0.
   * @param \Exception $prevException  Previous exception. Default NULL.
   * @param string $className Name of exception source class. Default NULL.
   * @throws mixed
   */
  public function exception( $message, $code = 0, $prevException = null, $className = null) {
    $class = $this->getCompatibleClass( $className );

    $this->defExcLastErrorMessage = $message;
    $this->defExcLastError = $code;

    $exception = [
      'time' => microtime( true ),
      'class' => $class,
      'code' => $code,
      'message' => $message,
    ];

    $this->defExcErrors [] = $exception;
    DeferredExceptionsGlobal::$defExcErrorsGlobal [] = $exception;

    if( $this->useExceptions() ){
      throw new $class( $message, $code, $prevException);
    }
  }

  /**
   * Format message string for output.
   *
   * @staticvar array $patterns
   *
   * @param array $error Source error
   * @param string $template Template
   * @return string
   */
  protected static function __formatMessage( array $error, $template = null ) {
    static $patterns = [
      '~\:\:time~us',
      '~\:\:microtime~us',
      '~\:\:class~us',
      '~\:\:code~us',
      '~\:\:message~us',
    ];
    if( empty( $error ['time'] )) $error ['time'] = microtime( true );
    if( empty( $error ['class'] )) $error ['class'] = __NAMESPACE__ .'\DeferredExceptionsException';
    if( empty( $error ['code'] )) $error ['code'] = 0;
    if( empty( $error ['message'] )) $error ['message'] = '';

    $time= [];
    preg_match('~(\d*)(\.(\d{0,6}))?~', $error ['time'], $time);
    $sec =  (int) $time [1];
    $usec = sprintf( '%0-6s', isset( $time [3]) ? (int) $time [3] : 0);

    $replaces = [
      date('Y-m-d H:i:s', $sec),
      date('Y-m-d H:i:s', $sec) . ':' . $usec,
      $error ['class'],
      $error ['code'],
      $error ['message'],
    ];

    return preg_replace(
        $patterns, $replaces,
        ! empty( $template ) && is_string( $template )
          ? $template
          : DeferredExceptionsGlobal::$defExcDefaultErrorListTemplate
    );
  }

  /**
   * Throws an a deferred exception(s).
   *
   * @param boolean $lastErrorOnly Throw the last exception only or all of them
   * @param boolean $releaseOnThrow Release a thrown exception(s). Default TRUE.
   * @param string $template Template with fields
   *
   * @return boolean The stack of an exceptions is not empty
   * @throws Seotils\Traits\DeferredExceptionsException
   * @throws mixed Other deferred exceptions classes
   */
  protected function __throw( $lastErrorOnly, $releaseOnThrow = true, $template = null) {
    if( ! empty( $this->defExcErrors )) {
      if( ! $lastErrorOnly ) {
        $class = get_class( $this );
        $message = "The instance of the `{$class}` has the following exceptions:\n";
        foreach( $this->defExcErrors as $error) {
          $message .= self::__formatMessage( $error, $template ) . "\n";
        }
        if( $releaseOnThrow ){
          $this->defExcErrors = [];
        }
        throw new DeferredExceptionsException( $message );
      } else {
        $idx = count( $this->defExcErrors ) - 1;
        $error = $this->defExcErrors [$idx];
        if( $releaseOnThrow ){
          unset( $this->defExcErrors [$idx] );
        }
        throw new $error ['class']( $error ['message'], $error ['code'] );
      }
    }
    return false;
  }

  /**
   * Throws exception with a list of all deferred exceptions of a class instance.
   *
   * @param boolean $releaseOnThrow Release a thrown exceptions. Default TRUE.
   * @param string $template <pre>Template for error list item with fields:
   *                              ::time      - time of exception
   *                              ::microtime - time of exception with microseconds
   *                              ::class     - Class of exception
   *                              ::code      - exception code
   *                              ::message   - error message
   * </pre>
   * <b>Example</b> "::microtime Exception: `::class`, code: #::code, message: ::message"
   *
   * @return boolean The stack of an exceptions is not empty
   * @throws Seotils\Traits\DeferredExceptionsException
   * @throws mixed Other deferred exceptions classes
   */
  public function throwAll( $releaseOnThrow = true, $template = null) {
    return $this->__throw( false, $releaseOnThrow, $template);
  }

  /**
   * Throws only last deferred exception.
   *
   * @param boolean $releaseOnThrow Release a thrown exception. Default TRUE.
   *
   * @return boolean Exception stack is not empty
   * @throws mixed Last deferred exception class
   */
  public function throwLast( $releaseOnThrow = true ) {
    return $this->__throw( true, $releaseOnThrow);
  }

  /**
   * Returns last error code
   *
   * @return int
   */
  public function getLastError() {
    return $this->defExcLastError;
  }

  /**
   * Returns last error message
   *
   * @return string
   */
  public function getErrorMessage() {
    return $this->defExcLastErrorMessage;
  }

  /**
   * Returns stack of exceptions for current instance
   *
   * @return array
   */
  public function getExceptions() {
    return $this->defExcErrors;
  }

  /**
   * The class instance has an exceptions
   *
   * @return bool
   */
  public function hasExceptions() {
    return ! empty( $this->defExcErrors );
  }

  /**
   * Clear the stack of an exceptions of the class instance
   *
   * @return void
   */
  public function releaseExceptions() {
    $this->defExcErrors = [];
  }


  /**
   * Returns stack of exceptions for all DeferredExeptioins classes
   *
   * @return array
   */
  public static function getGlobalExceptions() {
    return DeferredExceptionsGlobal::$defExcErrorsGlobal;
  }

  /**
   * One or more DeferredExeptioins classes has an exceptions
   *
   * @return bool
   */
  public static function hasGlobalExceptions() {
    return ! empty( DeferredExceptionsGlobal::$defExcErrorsGlobal );
  }

  /**
   * Clear the stack of an exceptions globally
   *
   * @return void
   */
  public static function releaseGlobalExceptions() {
    DeferredExceptionsGlobal::$defExcErrorsGlobal = [];
  }

  /**
   * Throws all deferred exceptions of all derived classes.
   *
   * @param boolean $releaseOnThrow Release a thrown exceptions. Default FALSE.
   * @param string $template <pre>Template for error list item with fields:
   *                              ::time      - time of exception
   *                              ::microtime - time of exception with microseconds
   *                              ::class     - Class of exception
   *                              ::code      - exception code
   *                              ::message   - error message
   * </pre>
   * <b>Example</b> "::microtime Exception: `::class`, code: #::code, message: ::message"
   *
   * @return boolean The global stack of an exceptions is not empty
   * @throws Seotils\Traits\DeferredExceptionsException
   */
  public static function throwGlobal( $releaseOnThrow = false, $template = null ) {
    if( ! empty( DeferredExceptionsGlobal::$defExcErrorsGlobal )) {
        $message = "There are the following exceptions:\n";
        foreach( DeferredExceptionsGlobal::$defExcErrorsGlobal as $error) {
          $message .= self::__formatMessage( $error, $template ) . "\n";
        }
        if( $releaseOnThrow ){
          DeferredExceptionsGlobal::$defExcErrorsGlobal = [];
        }
        $result = count( DeferredExceptionsGlobal::$defExcErrorsGlobal );
        throw new DeferredExceptionsException( $message );
    }
    return false;
  }

}