<?php

namespace Parse;

use Parse\Internal\Encodable;

/**
 * ParseClient - Main class for Parse initialization and communication
 *
 * @package  Parse
 * @author   Fosco Marotto <fjm@fb.com>
 */
final class ParseClient
{

  /**
   * Constant for the API Server Host Address.
   * @ignore
   */
  const HOST_NAME = 'https://api.parse.com';

  /**
   * @var - String for applicationId.
   */
  private static $applicationId;

  /**
   * @var - String for REST API Key.
   */
  private static $restKey;

  /**
   * @var - String for Master Key.
   */
  private static $masterKey;

  /**
   * @var - Boolean for Enable/Disable curl exceptions.
   */
  private static $enableCurlExceptions;

  /**
   * @var ParseStorageInterface Object for managing persistence
   */
  private static $storage;

  /**
   * Constant for version string to include with requests.
   * @ignore
   */
  const VERSION_STRING = 'php1.0.5';

  /**
   * Parse\Client::initialize, must be called before using Parse features.
   *
   * @param string $app_id                Parse Application ID
   * @param string $rest_key              Parse REST API Key
   * @param string $master_key            Parse Master Key
   * @param string $enableCurlExceptions  Enable or disable Parse curl exceptions
   *
   * @return null
   */
  public static function initialize($app_id, $rest_key, $master_key, $enableCurlExceptions = true)
  {
    ParseUser::registerSubclass();
    ParseRole::registerSubclass();
    ParseInstallation::registerSubclass();
    self::$applicationId = $app_id;
    self::$restKey = $rest_key;
    self::$masterKey = $master_key;
    self::$enableCurlExceptions = $enableCurlExceptions;
    if (!static::$storage) {
      if (session_status() === PHP_SESSION_ACTIVE) {
        self::setStorage(new ParseSessionStorage());
      } else {
        self::setStorage(new ParseMemoryStorage());
      }
    }
  }

  /**
   * ParseClient::_encode, internal method for encoding object values.
   *
   * @param mixed $value             Value to encode
   * @param bool  $allowParseObjects Allow nested objects
   *
   * @return mixed Encoded results.
   *
   * @throws \Exception
   * @ignore
   */
  public static function _encode($value, $allowParseObjects)
  {
    if ($value instanceof \DateTime) {
      return array(
        '__type' => 'Date', 'iso' => self::getProperDateFormat($value)
      );
    }

    if ($value instanceof \stdClass) {
      return $value;
    }

    if ($value instanceof ParseObject) {
      if (!$allowParseObjects) {
        throw new \Exception('ParseObjects not allowed here.');
      }
      return $value->_toPointer();
    }

    if ($value instanceof Encodable) {
      return $value->_encode();
    }

    if (is_array($value)) {
      return self::_encodeArray($value, $allowParseObjects);
    }

    if (!is_scalar($value) && $value !== null) {
      throw new \Exception('Invalid type encountered.');
    }
    return $value;
  }

  /**
   * ParseClient::_decode, internal method for decoding server responses.
   *
   * @param mixed $data The value to decode
   *
   * @return mixed
   * @ignore
   */
  public static function _decode($data)
  {
    // The json decoded response from Parse will make JSONObjects into stdClass
    //   objects.  We'll change it to an associative array here.
    if ($data instanceof \stdClass) {
      $tmp = (array)$data;
      if (!empty($tmp)) {
        return self::_decode(get_object_vars($data));
      }
    }

    if (!$data && !is_array($data)) {
      return null;
    }

    if (is_array($data)) {
      $typeString = (isset($data['__type']) ? $data['__type'] : null);

      if ($typeString === 'Date') {
        return new \DateTime($data['iso']);
      }

      if ($typeString === 'Bytes') {
        return base64_decode($data['base64']);
      }

      if ($typeString === 'Pointer') {
        return ParseObject::create($data['className'], $data['objectId']);
      }

      if ($typeString === 'File') {
        return ParseFile::_createFromServer($data['name'], $data['url']);
      }

      if ($typeString === 'GeoPoint') {
        return new ParseGeoPoint($data['latitude'], $data['longitude']);
      }

      if ($typeString === 'Object') {
        $output = ParseObject::create($data['className']);
        $output->_mergeAfterFetch($data);
        return $output;
      }

      if ($typeString === 'Relation') {
        return $data;
      }

      $newDict = array();
      foreach ($data as $key => $value) {
        $newDict[$key] = static::_decode($value);
      }
      return $newDict;

    }

    return $data;
  }

  /**
   * ParseClient::_encodeArray, internal method for encoding arrays.
   *
   * @param array $value             Array to encode.
   * @param bool  $allowParseObjects Allow nested objects.
   *
   * @return array Encoded results.
   * @ignore
   */
  public static function _encodeArray($value, $allowParseObjects)
  {
    $output = array();
    foreach ($value as $key => $item) {
      $output[$key] = self::_encode($item, $allowParseObjects);
    }
    return $output;
  }

  /**
   * Parse\Client::_request, internal method for communicating with Parse.
   *
   * @param string $method       HTTP Method for this request.
   * @param string $relativeUrl  REST API Path.
   * @param null   $sessionToken Session Token.
   * @param null   $data         Data to provide with the request.
   * @param bool   $useMasterKey Whether to use the Master Key.
   *
   * @return mixed          Result from Parse API Call.
   * @throws \Exception
   * @ignore
   */
  public static function _request($method, $relativeUrl, $sessionToken = null,
                                  $data = null, $useMasterKey = false)
  {
    if ($data === '[]') {
      $data = '{}';
    }
    self::assertParseInitialized();
    $headers = self::_getRequestHeaders($sessionToken, $useMasterKey);

    $url = self::HOST_NAME . $relativeUrl;
    if ($method === 'GET' && !empty($data)) {
      $url .= '?' . http_build_query($data);
    }
    $rest = curl_init();
    curl_setopt($rest, CURLOPT_URL, $url);
    curl_setopt($rest, CURLOPT_RETURNTRANSFER, 1);
    if ($method === 'POST') {
      $headers[] = 'Content-Type: application/json';
      curl_setopt($rest, CURLOPT_POST, 1);
      curl_setopt($rest, CURLOPT_POSTFIELDS, $data);
    }
    if ($method === 'PUT') {
      $headers[] = 'Content-Type: application/json';
      curl_setopt($rest, CURLOPT_CUSTOMREQUEST, $method);
      curl_setopt($rest, CURLOPT_POSTFIELDS, $data);
    }
    if ($method === 'DELETE') {
      curl_setopt($rest, CURLOPT_CUSTOMREQUEST, $method);
    }
    curl_setopt($rest, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($rest);
    $status = curl_getinfo($rest, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($rest, CURLINFO_CONTENT_TYPE);
    if (curl_errno($rest)) {
      if (self::$enableCurlExceptions) {
        throw new ParseException(curl_error($rest), curl_errno($rest));
      } else {
        return false;
      }
    }
    curl_close($rest);
    if (strpos($contentType, 'text/html') !== false) {
      throw new ParseException('Bad Request', -1);
    }

    $decoded = json_decode($response, true);
    if (isset($decoded['error'])) {
      throw new ParseException($decoded['error'],
        isset($decoded['code']) ? $decoded['code'] : 0
      );
    }
    return $decoded;

  }

  /**
   * ParseClient::setStorage, will update the storage object used for
   * persistence.
   *
   * @param ParseStorageInterface $storageObject
   *
   * @return null
   */
  public static function setStorage(ParseStorageInterface $storageObject)
  {
    self::$storage = $storageObject;
  }

  /**
   * ParseClient::getStorage, will return the storage object used for
   * persistence.
   *
   * @return ParseStorageInterface
   */
  public static function getStorage()
  {
    return self::$storage;
  }

  /**
   * ParseClient::_unsetStorage, will null the storage object.
   *
   * Without some ability to clear the storage objects, all test cases would
   *   use the first assigned storage object.
   *
   * @return null
   * @ignore
   */
  public static function _unsetStorage()
  {
    self::$storage = null;
  }

  private static function assertParseInitialized()
  {
    if (self::$applicationId === null) {
      throw new \Exception(
        'You must call Parse::initialize() before making any requests.'
      );
    }
  }

  /**
   * @param $sessionToken
   * @param $useMasterKey
   *
   * @return array
   * @ignore
   */
  public static function _getRequestHeaders($sessionToken, $useMasterKey)
  {
    $headers = array('X-Parse-Application-Id: ' . self::$applicationId,
      'X-Parse-Client-Version: ' . self::VERSION_STRING);
    if ($sessionToken) {
      $headers[] = 'X-Parse-Session-Token: ' . $sessionToken;
    }
    if ($useMasterKey) {
      $headers[] = 'X-Parse-Master-Key: ' . self::$masterKey;
    } else {
      $headers[] = 'X-Parse-REST-API-Key: ' . self::$restKey;
    }
    /**
     * Set an empty Expect header to stop the 100-continue behavior for post
     *   data greater than 1024 bytes.
     *   http://pilif.github.io/2007/02/the-return-of-except-100-continue/
     */
    $headers[] = 'Expect: ';
    return $headers;
  }

  /**
   * Get a date value in the format stored on Parse.
   *
   * All the SDKs do some slightly different date handling.
   * PHP provides 6 digits for the microseconds (u) so we have to chop 3 off.
   *
   * @param \DateTime $value DateTime value to format.
   *
   * @return string
   */
  public static function getProperDateFormat($value)
  {
    $dateFormatString = 'Y-m-d\TH:i:s.u';
    $date = date_format($value, $dateFormatString);
    $date = substr($date, 0, -3) . 'Z';
    return $date;
  }

  /**
   * Get a date value in the format to use in Local Push Scheduling on Parse.
   *
   * All the SDKs do some slightly different date handling.
   * Format from Parse doc: an ISO 8601 date without a time zone, i.e. 2014-10-16T12:00:00 .
   *
   * @param \DateTime $value DateTime value to format.
   *
   * @return string
   */
  public static function getLocalPushDateFormat($value)
  {
    $dateFormatString = 'Y-m-d\TH:i:s';
    $date = date_format($value, $dateFormatString);
    return $date;
  }
}
