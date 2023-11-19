<?php
/**
 * AmountTypeSyncData
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
 * AmountTypeSyncData Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class AmountTypeSyncData implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AmountTypeSyncData';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'discountAmount' => 'string',
        'discountDesc' => 'string',
        'hasAlipayTrade' => 'bool',
        'taskAmount' => 'string',
        'taskDesc' => 'string',
        'tradeNo' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'discountAmount' => null,
        'discountDesc' => null,
        'hasAlipayTrade' => null,
        'taskAmount' => null,
        'taskDesc' => null,
        'tradeNo' => null
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
        'discountAmount' => 'discount_amount',
        'discountDesc' => 'discount_desc',
        'hasAlipayTrade' => 'has_alipay_trade',
        'taskAmount' => 'task_amount',
        'taskDesc' => 'task_desc',
        'tradeNo' => 'trade_no'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'discountAmount' => 'setDiscountAmount',
        'discountDesc' => 'setDiscountDesc',
        'hasAlipayTrade' => 'setHasAlipayTrade',
        'taskAmount' => 'setTaskAmount',
        'taskDesc' => 'setTaskDesc',
        'tradeNo' => 'setTradeNo'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'discountAmount' => 'getDiscountAmount',
        'discountDesc' => 'getDiscountDesc',
        'hasAlipayTrade' => 'getHasAlipayTrade',
        'taskAmount' => 'getTaskAmount',
        'taskDesc' => 'getTaskDesc',
        'tradeNo' => 'getTradeNo'
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
        $this->container['discountAmount'] = $data['discountAmount'] ?? null;
        $this->container['discountDesc'] = $data['discountDesc'] ?? null;
        $this->container['hasAlipayTrade'] = $data['hasAlipayTrade'] ?? null;
        $this->container['taskAmount'] = $data['taskAmount'] ?? null;
        $this->container['taskDesc'] = $data['taskDesc'] ?? null;
        $this->container['tradeNo'] = $data['tradeNo'] ?? null;
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
     * Gets discountAmount
     *
     * @return string|null
     */
    public function getDiscountAmount()
    {
        return $this->container['discountAmount'];
    }

    /**
     * Sets discountAmount
     *
     * @param string|null $discountAmount 商户回传的优惠金额，如用户享受的红包金额，单位元
     *
     * @return self
     */
    public function setDiscountAmount($discountAmount)
    {
        $this->container['discountAmount'] = $discountAmount;

        return $this;
    }

    /**
     * Gets discountDesc
     *
     * @return string|null
     */
    public function getDiscountDesc()
    {
        return $this->container['discountDesc'];
    }

    /**
     * Sets discountDesc
     *
     * @param string|null $discountDesc 商户数据回传的优惠信息的名称。
     *
     * @return self
     */
    public function setDiscountDesc($discountDesc)
    {
        $this->container['discountDesc'] = $discountDesc;

        return $this;
    }

    /**
     * Gets hasAlipayTrade
     *
     * @return bool|null
     */
    public function getHasAlipayTrade()
    {
        return $this->container['hasAlipayTrade'];
    }

    /**
     * Sets hasAlipayTrade
     *
     * @param bool|null $hasAlipayTrade 是否为支付宝交易，默认false，表示是否要做支付宝交易关联。此时tradeNo必须传支付宝交易号。
     *
     * @return self
     */
    public function setHasAlipayTrade($hasAlipayTrade)
    {
        $this->container['hasAlipayTrade'] = $hasAlipayTrade;

        return $this;
    }

    /**
     * Gets taskAmount
     *
     * @return string|null
     */
    public function getTaskAmount()
    {
        return $this->container['taskAmount'];
    }

    /**
     * Sets taskAmount
     *
     * @param string|null $taskAmount 用户和商户发生交易的交易单金额，单位元。
     *
     * @return self
     */
    public function setTaskAmount($taskAmount)
    {
        $this->container['taskAmount'] = $taskAmount;

        return $this;
    }

    /**
     * Gets taskDesc
     *
     * @return string|null
     */
    public function getTaskDesc()
    {
        return $this->container['taskDesc'];
    }

    /**
     * Sets taskDesc
     *
     * @param string|null $taskDesc 任务描述
     *
     * @return self
     */
    public function setTaskDesc($taskDesc)
    {
        $this->container['taskDesc'] = $taskDesc;

        return $this;
    }

    /**
     * Gets tradeNo
     *
     * @return string|null
     */
    public function getTradeNo()
    {
        return $this->container['tradeNo'];
    }

    /**
     * Sets tradeNo
     *
     * @param string|null $tradeNo 用户和商户发生金额类交易的交易单号。
     *
     * @return self
     */
    public function setTradeNo($tradeNo)
    {
        $this->container['tradeNo'] = $tradeNo;

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


