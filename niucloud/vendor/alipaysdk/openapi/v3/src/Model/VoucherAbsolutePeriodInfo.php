<?php
/**
 * VoucherAbsolutePeriodInfo
 *
 * PHP version 7.4
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 */

/**
 * 支付宝开放平台API
 *
 * 支付宝开放平台v3协议文档
 *
 * The version of the OpenAPI document: 2023-10-25
 * Generated by: https://openapi-generator.tech
 * OpenAPI Generator version: 6.2.1
 */

/**
 * NOTE: This class is auto generated by OpenAPI Generator (https://openapi-generator.tech).
 * https://openapi-generator.tech
 * Do not edit the class manually.
 */

namespace Alipay\OpenAPISDK\Model;

use \ArrayAccess;
use \Alipay\OpenAPISDK\ObjectSerializer;

/**
 * VoucherAbsolutePeriodInfo Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class VoucherAbsolutePeriodInfo implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'VoucherAbsolutePeriodInfo';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'timeRestrictInfo' => '\Alipay\OpenAPISDK\Model\TimeRestrictInfo',
        'validBeginTime' => 'string',
        'validEndTime' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'timeRestrictInfo' => null,
        'validBeginTime' => null,
        'validEndTime' => null
    ];

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function openAPITypes()
    {
        return self::$openAPITypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function openAPIFormats()
    {
        return self::$openAPIFormats;
    }

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'timeRestrictInfo' => 'time_restrict_info',
        'validBeginTime' => 'valid_begin_time',
        'validEndTime' => 'valid_end_time'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'timeRestrictInfo' => 'setTimeRestrictInfo',
        'validBeginTime' => 'setValidBeginTime',
        'validEndTime' => 'setValidEndTime'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'timeRestrictInfo' => 'getTimeRestrictInfo',
        'validBeginTime' => 'getValidBeginTime',
        'validEndTime' => 'getValidEndTime'
    ];

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @return array
     */
    public static function attributeMap()
    {
        return self::$attributeMap;
    }

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @return array
     */
    public static function setters()
    {
        return self::$setters;
    }

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @return array
     */
    public static function getters()
    {
        return self::$getters;
    }

    /**
     * The original name of the model.
     *
     * @return string
     */
    public function getModelName()
    {
        return self::$openAPIModelName;
    }


    /**
     * Associative array for storing property values
     *
     * @var mixed[]
     */
    protected $container = [];

    /**
     * Constructor
     *
     * @param mixed[] $data Associated array of property values
     *                      initializing the model
     */
    public function __construct(array $data = null)
    {
        $this->container['timeRestrictInfo'] = $data['timeRestrictInfo'] ?? null;
        $this->container['validBeginTime'] = $data['validBeginTime'] ?? null;
        $this->container['validEndTime'] = $data['validEndTime'] ?? null;
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        return $invalidProperties;
    }

    /**
     * Validate all the properties in the model
     * return true if all passed
     *
     * @return bool True if all properties are valid
     */
    public function valid()
    {
        return count($this->listInvalidProperties()) === 0;
    }


    /**
     * Gets timeRestrictInfo
     *
     * @return \Alipay\OpenAPISDK\Model\TimeRestrictInfo|null
     */
    public function getTimeRestrictInfo()
    {
        return $this->container['timeRestrictInfo'];
    }

    /**
     * Sets timeRestrictInfo
     *
     * @param \Alipay\OpenAPISDK\Model\TimeRestrictInfo|null $timeRestrictInfo timeRestrictInfo
     *
     * @return self
     */
    public function setTimeRestrictInfo($timeRestrictInfo)
    {
        $this->container['timeRestrictInfo'] = $timeRestrictInfo;

        return $this;
    }

    /**
     * Gets validBeginTime
     *
     * @return string|null
     */
    public function getValidBeginTime()
    {
        return $this->container['validBeginTime'];
    }

    /**
     * Sets validBeginTime
     *
     * @param string|null $validBeginTime 券可使用的开始时间。 格式为：yyyy-MM-dd HH:mm:ss。
     *
     * @return self
     */
    public function setValidBeginTime($validBeginTime)
    {
        $this->container['validBeginTime'] = $validBeginTime;

        return $this;
    }

    /**
     * Gets validEndTime
     *
     * @return string|null
     */
    public function getValidEndTime()
    {
        return $this->container['validEndTime'];
    }

    /**
     * Sets validEndTime
     *
     * @param string|null $validEndTime 券可使用的结束时间。 格式为yyyy-MM-dd HH:mm:ss。
     *
     * @return self
     */
    public function setValidEndTime($validEndTime)
    {
        $this->container['validEndTime'] = $validEndTime;

        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer $offset Offset
     *
     * @return boolean
     */
    public function offsetExists($offset): bool
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer $offset Offset
     *
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->container[$offset] ?? null;
    }

    /**
     * Sets value based on offset.
     *
     * @param int|null $offset Offset
     * @param mixed    $value  Value to be set
     *
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Unsets offset.
     *
     * @param integer $offset Offset
     *
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->container[$offset]);
    }

    /**
     * Serializes the object to a value that can be serialized natively by json_encode().
     * @link https://www.php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed Returns data which can be serialized by json_encode(), which is a value
     * of any type other than a resource.
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
       return ObjectSerializer::sanitizeForSerialization($this);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode(
            ObjectSerializer::sanitizeForSerialization($this),
            JSON_PRETTY_PRINT
        );
    }

    /**
     * Gets a header-safe presentation of the object
     *
     * @return string
     */
    public function toHeaderValue()
    {
        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}


