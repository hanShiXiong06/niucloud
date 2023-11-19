<?php
/**
 * AlipayPcreditHuabeiAuthAccumulationQueryResponseModel
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
 * AlipayPcreditHuabeiAuthAccumulationQueryResponseModel Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class AlipayPcreditHuabeiAuthAccumulationQueryResponseModel implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AlipayPcreditHuabeiAuthAccumulationQueryResponseModel';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'payAmount' => 'string',
        'totalDiscountAmount' => 'string',
        'totalPayCount' => 'string',
        'totalRealPayAmount' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'payAmount' => null,
        'totalDiscountAmount' => null,
        'totalPayCount' => null,
        'totalRealPayAmount' => null
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
        'payAmount' => 'pay_amount',
        'totalDiscountAmount' => 'total_discount_amount',
        'totalPayCount' => 'total_pay_count',
        'totalRealPayAmount' => 'total_real_pay_amount'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'payAmount' => 'setPayAmount',
        'totalDiscountAmount' => 'setTotalDiscountAmount',
        'totalPayCount' => 'setTotalPayCount',
        'totalRealPayAmount' => 'setTotalRealPayAmount'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'payAmount' => 'getPayAmount',
        'totalDiscountAmount' => 'getTotalDiscountAmount',
        'totalPayCount' => 'getTotalPayCount',
        'totalRealPayAmount' => 'getTotalRealPayAmount'
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
        $this->container['payAmount'] = $data['payAmount'] ?? null;
        $this->container['totalDiscountAmount'] = $data['totalDiscountAmount'] ?? null;
        $this->container['totalPayCount'] = $data['totalPayCount'] ?? null;
        $this->container['totalRealPayAmount'] = $data['totalRealPayAmount'] ?? null;
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
     * Gets payAmount
     *
     * @return string|null
     */
    public function getPayAmount()
    {
        return $this->container['payAmount'];
    }

    /**
     * Sets payAmount
     *
     * @param string|null $payAmount 本周期内支付宝端根据用户消费情况试算出的应付费用，仅供参考使用。
     *
     * @return self
     */
    public function setPayAmount($payAmount)
    {
        $this->container['payAmount'] = $payAmount;

        return $this;
    }

    /**
     * Gets totalDiscountAmount
     *
     * @return string|null
     */
    public function getTotalDiscountAmount()
    {
        return $this->container['totalDiscountAmount'];
    }

    /**
     * Sets totalDiscountAmount
     *
     * @param string|null $totalDiscountAmount 本周期内用户累计享受的优惠金额
     *
     * @return self
     */
    public function setTotalDiscountAmount($totalDiscountAmount)
    {
        $this->container['totalDiscountAmount'] = $totalDiscountAmount;

        return $this;
    }

    /**
     * Gets totalPayCount
     *
     * @return string|null
     */
    public function getTotalPayCount()
    {
        return $this->container['totalPayCount'];
    }

    /**
     * Sets totalPayCount
     *
     * @param string|null $totalPayCount 本周期内用户总的消费次数
     *
     * @return self
     */
    public function setTotalPayCount($totalPayCount)
    {
        $this->container['totalPayCount'] = $totalPayCount;

        return $this;
    }

    /**
     * Gets totalRealPayAmount
     *
     * @return string|null
     */
    public function getTotalRealPayAmount()
    {
        return $this->container['totalRealPayAmount'];
    }

    /**
     * Sets totalRealPayAmount
     *
     * @param string|null $totalRealPayAmount 本周期内用户累计支付宝付款金额
     *
     * @return self
     */
    public function setTotalRealPayAmount($totalRealPayAmount)
    {
        $this->container['totalRealPayAmount'] = $totalRealPayAmount;

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


