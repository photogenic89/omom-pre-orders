<?php
/**
 * Singleton principle
 */
namespace Omom\PreOrders\Abstracts;

defined( 'ABSPATH' ) || die;

abstract class Singleton 
{	    
     /**
      * The single instance of the class.
      */
     private static array $instances = [];

     /**
      * Main Hooks instance.
      */
     final public static function getInstance(): object
     {
          $called_class = get_called_class();
          if (! isset( self::$instances[$called_class] )) self::$instances[$called_class] = new $called_class();
          return self::$instances[$called_class];
     }

     /**
      * Constructor
      */
     abstract protected function __construct();
}