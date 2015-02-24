<?php

use Parse\ParseClient;
use Parse\ParseQuery;
use Parse\ParseObject;

class ParseTestHelper
{

  public static function setUp()
  {
    ini_set('error_reporting', E_ALL);
    ini_set('display_errors', 1);
    date_default_timezone_set('UTC');
    ParseClient::initialize(
      'lSII6Ttxzch0rEHIBhEJxt32SsCtibaDKDHm2AFa',
      'WaMEFrz2cjWWxoE7f46IF3uZUP0GRHzEWApJ4nmB',
      '90GXpoo86rIvgc5W0bC5BzqflCLNq3PxV6qpqRHh'
    );
  }

  public static function tearDown()
  {

  }

  public static function clearClass($class)
  {
    $query = new ParseQuery($class);
    $query->each(function(ParseObject $obj) {
      $obj->destroy(true);
    }, true);
  }

} 