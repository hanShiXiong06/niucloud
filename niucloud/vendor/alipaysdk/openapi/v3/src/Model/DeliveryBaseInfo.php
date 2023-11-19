<?php
/**
 * DeliveryBaseInfo
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
 * DeliveryBaseInfo Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class DeliveryBaseInfo implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'DeliveryBaseInfo';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'deliveryBeginTime' => 'string',
        'deliveryEndTime' => 'string',
        'deliveryMaterial' => '\Alipay\OpenAPISDK\Model\DeliveryMaterial',
        'deliveryName' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'deliveryBeginTime' => null,
        'deliveryEndTime' => null,
        'deliveryMaterial' => null,
        'deliveryName' => null
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
        'deliveryBeginTime' => 'delivery_begin_time',
        'deliveryEndTime' => 'delivery_end_time',
        'deliveryMaterial' => 'delivery_material',
        'deliveryName' => 'delivery_name'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'deliveryBeginTime' => 'setDeliveryBeginTime',
        'deliveryEndTime' => 'setDeliveryEndTime',
        'deliveryMaterial' => 'setDeliveryMaterial',
        'deliveryName' => 'setDeliveryName'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'deliveryBeginTime' => 'getDeliveryBeginTime',
        'deliveryEndTime' => 'getDeliveryEndTime',
        'deliveryMaterial' => 'getDeliveryMaterial',
        'deliveryName' => 'getDeliveryName'
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
        $this->container['deliveryBeginTime'] = $data['deliveryBeginTime'] ?? null;
        $this->container['deliveryEndTime'] = $data['deliveryEndTime'] ?? null;
        $this->container['deliveryMaterial'] = $data['deliveryMaterial'] ?? null;
        $this->container['deliveryName'] = $data['deliveryName'] ?? null;
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
     * Gets deliveryBeginTime
     *
     * @return string|null
     */
    public function getDeliveryBeginTime()
    {
        return $this->container['deliveryBeginTime'];
    }

    /**
     * Sets deliveryBeginTime
     *
     * @param string|null $deliveryBeginTime 投放计划开始时间。 格式为：yyyy-MM-dd HH:mm:ss。
     *
     * @return self
     */
    public function setDeliveryBeginTime($deliveryBeginTime)
    {
        $this->container['deliveryBeginTime'] = $deliveryBeginTime;

        return $this;
    }

    /**
     * Gets deliveryEndTime
     *
     * @return string|null
     */
    public function getDeliveryEndTime()
    {
        return $this->container['deliveryEndTime'];
    }

    /**
     * Sets deliveryEndTime
     *
     * @param string|null $deliveryEndTime 投放计划结束时间。 格式为：yyyy-MM-dd HH:mm:ss。
     *
     * @return self
     */
    public function setDeliveryEndTime($deliveryEndTime)
    {
        $this->container['deliveryEndTime'] = $deliveryEndTime;

        return $this;
    }

    /**
     * Gets deliveryMaterial
     *
     * @return \Alipay\OpenAPISDK\Model\DeliveryMaterial|null
     */
    public function getDeliveryMaterial()
    {
        return $this->container['deliveryMaterial'];
    }

    /**
     * Sets deliveryMaterial
     *
     * @param \Alipay\OpenAPISDK\Model\DeliveryMaterial|null $deliveryMaterial deliveryMaterial
     *
     * @return self
     */
    public function setDeliveryMaterial($deliveryMaterial)
    {
        $this->container['deliveryMaterial'] = $deliveryMaterial;

        return $this;
    }

    /**
     * Gets deliveryName
     *
     * @return string|null
     */
    public function getDeliveryName()
    {
        return $this->container['deliveryName'];
    }

    /**
     * Sets deliveryName
     *
     * @param string|null $deliveryName 投放计划名称。 投放计划名称不会对用户进行表达，只用于商户管理使用。长度需要大于等于3，小于20。
     *
     * @return self
     */
    public function setDeliveryName($deliveryName)
    {
        $this->container['deliveryName'] = $deliveryName;

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


