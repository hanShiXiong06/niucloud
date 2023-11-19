<?php
/**
 * ShopRecommendInfo
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
 * ShopRecommendInfo Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class ShopRecommendInfo implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'ShopRecommendInfo';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'recommend' => 'string',
        'recommendAddress' => 'string',
        'recommendLatitude' => 'string',
        'recommendLongtitude' => 'string',
        'recommendName' => 'string',
        'unconfidenceReason' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'recommend' => null,
        'recommendAddress' => null,
        'recommendLatitude' => null,
        'recommendLongtitude' => null,
        'recommendName' => null,
        'unconfidenceReason' => null
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
        'recommend' => 'recommend',
        'recommendAddress' => 'recommend_address',
        'recommendLatitude' => 'recommend_latitude',
        'recommendLongtitude' => 'recommend_longtitude',
        'recommendName' => 'recommend_name',
        'unconfidenceReason' => 'unconfidence_reason'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'recommend' => 'setRecommend',
        'recommendAddress' => 'setRecommendAddress',
        'recommendLatitude' => 'setRecommendLatitude',
        'recommendLongtitude' => 'setRecommendLongtitude',
        'recommendName' => 'setRecommendName',
        'unconfidenceReason' => 'setUnconfidenceReason'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'recommend' => 'getRecommend',
        'recommendAddress' => 'getRecommendAddress',
        'recommendLatitude' => 'getRecommendLatitude',
        'recommendLongtitude' => 'getRecommendLongtitude',
        'recommendName' => 'getRecommendName',
        'unconfidenceReason' => 'getUnconfidenceReason'
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
        $this->container['recommend'] = $data['recommend'] ?? null;
        $this->container['recommendAddress'] = $data['recommendAddress'] ?? null;
        $this->container['recommendLatitude'] = $data['recommendLatitude'] ?? null;
        $this->container['recommendLongtitude'] = $data['recommendLongtitude'] ?? null;
        $this->container['recommendName'] = $data['recommendName'] ?? null;
        $this->container['unconfidenceReason'] = $data['unconfidenceReason'] ?? null;
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
     * Gets recommend
     *
     * @return string|null
     */
    public function getRecommend()
    {
        return $this->container['recommend'];
    }

    /**
     * Sets recommend
     *
     * @param string|null $recommend 门店修改建议
     *
     * @return self
     */
    public function setRecommend($recommend)
    {
        $this->container['recommend'] = $recommend;

        return $this;
    }

    /**
     * Gets recommendAddress
     *
     * @return string|null
     */
    public function getRecommendAddress()
    {
        return $this->container['recommendAddress'];
    }

    /**
     * Sets recommendAddress
     *
     * @param string|null $recommendAddress 推荐详细地址
     *
     * @return self
     */
    public function setRecommendAddress($recommendAddress)
    {
        $this->container['recommendAddress'] = $recommendAddress;

        return $this;
    }

    /**
     * Gets recommendLatitude
     *
     * @return string|null
     */
    public function getRecommendLatitude()
    {
        return $this->container['recommendLatitude'];
    }

    /**
     * Sets recommendLatitude
     *
     * @param string|null $recommendLatitude 推荐纬度
     *
     * @return self
     */
    public function setRecommendLatitude($recommendLatitude)
    {
        $this->container['recommendLatitude'] = $recommendLatitude;

        return $this;
    }

    /**
     * Gets recommendLongtitude
     *
     * @return string|null
     */
    public function getRecommendLongtitude()
    {
        return $this->container['recommendLongtitude'];
    }

    /**
     * Sets recommendLongtitude
     *
     * @param string|null $recommendLongtitude 推荐经度
     *
     * @return self
     */
    public function setRecommendLongtitude($recommendLongtitude)
    {
        $this->container['recommendLongtitude'] = $recommendLongtitude;

        return $this;
    }

    /**
     * Gets recommendName
     *
     * @return string|null
     */
    public function getRecommendName()
    {
        return $this->container['recommendName'];
    }

    /**
     * Sets recommendName
     *
     * @param string|null $recommendName 推荐门店名称
     *
     * @return self
     */
    public function setRecommendName($recommendName)
    {
        $this->container['recommendName'] = $recommendName;

        return $this;
    }

    /**
     * Gets unconfidenceReason
     *
     * @return string|null
     */
    public function getUnconfidenceReason()
    {
        return $this->container['unconfidenceReason'];
    }

    /**
     * Sets unconfidenceReason
     *
     * @param string|null $unconfidenceReason 门店不置信原因
     *
     * @return self
     */
    public function setUnconfidenceReason($unconfidenceReason)
    {
        $this->container['unconfidenceReason'] = $unconfidenceReason;

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

