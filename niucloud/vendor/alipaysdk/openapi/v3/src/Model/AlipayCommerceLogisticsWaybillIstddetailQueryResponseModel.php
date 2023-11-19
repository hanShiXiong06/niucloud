<?php
/**
 * AlipayCommerceLogisticsWaybillIstddetailQueryResponseModel
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
 * AlipayCommerceLogisticsWaybillIstddetailQueryResponseModel Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class AlipayCommerceLogisticsWaybillIstddetailQueryResponseModel implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AlipayCommerceLogisticsWaybillIstddetailQueryResponseModel';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'reachDuration' => 'int',
        'riderLat' => 'string',
        'riderLng' => 'string',
        'riderMobileNo' => 'string',
        'riderName' => 'string',
        'riderPoiLink' => 'string',
        'status' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'reachDuration' => null,
        'riderLat' => null,
        'riderLng' => null,
        'riderMobileNo' => null,
        'riderName' => null,
        'riderPoiLink' => null,
        'status' => null
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
        'reachDuration' => 'reach_duration',
        'riderLat' => 'rider_lat',
        'riderLng' => 'rider_lng',
        'riderMobileNo' => 'rider_mobile_no',
        'riderName' => 'rider_name',
        'riderPoiLink' => 'rider_poi_link',
        'status' => 'status'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'reachDuration' => 'setReachDuration',
        'riderLat' => 'setRiderLat',
        'riderLng' => 'setRiderLng',
        'riderMobileNo' => 'setRiderMobileNo',
        'riderName' => 'setRiderName',
        'riderPoiLink' => 'setRiderPoiLink',
        'status' => 'setStatus'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'reachDuration' => 'getReachDuration',
        'riderLat' => 'getRiderLat',
        'riderLng' => 'getRiderLng',
        'riderMobileNo' => 'getRiderMobileNo',
        'riderName' => 'getRiderName',
        'riderPoiLink' => 'getRiderPoiLink',
        'status' => 'getStatus'
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
        $this->container['reachDuration'] = $data['reachDuration'] ?? null;
        $this->container['riderLat'] = $data['riderLat'] ?? null;
        $this->container['riderLng'] = $data['riderLng'] ?? null;
        $this->container['riderMobileNo'] = $data['riderMobileNo'] ?? null;
        $this->container['riderName'] = $data['riderName'] ?? null;
        $this->container['riderPoiLink'] = $data['riderPoiLink'] ?? null;
        $this->container['status'] = $data['status'] ?? null;
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
     * Gets reachDuration
     *
     * @return int|null
     */
    public function getReachDuration()
    {
        return $this->container['reachDuration'];
    }

    /**
     * Sets reachDuration
     *
     * @param int|null $reachDuration 预计还剩多久送达 单位：秒
     *
     * @return self
     */
    public function setReachDuration($reachDuration)
    {
        $this->container['reachDuration'] = $reachDuration;

        return $this;
    }

    /**
     * Gets riderLat
     *
     * @return string|null
     */
    public function getRiderLat()
    {
        return $this->container['riderLat'];
    }

    /**
     * Sets riderLat
     *
     * @param string|null $riderLat 骑手位置纬度
     *
     * @return self
     */
    public function setRiderLat($riderLat)
    {
        $this->container['riderLat'] = $riderLat;

        return $this;
    }

    /**
     * Gets riderLng
     *
     * @return string|null
     */
    public function getRiderLng()
    {
        return $this->container['riderLng'];
    }

    /**
     * Sets riderLng
     *
     * @param string|null $riderLng 骑手位置经度
     *
     * @return self
     */
    public function setRiderLng($riderLng)
    {
        $this->container['riderLng'] = $riderLng;

        return $this;
    }

    /**
     * Gets riderMobileNo
     *
     * @return string|null
     */
    public function getRiderMobileNo()
    {
        return $this->container['riderMobileNo'];
    }

    /**
     * Sets riderMobileNo
     *
     * @param string|null $riderMobileNo 骑手电话
     *
     * @return self
     */
    public function setRiderMobileNo($riderMobileNo)
    {
        $this->container['riderMobileNo'] = $riderMobileNo;

        return $this;
    }

    /**
     * Gets riderName
     *
     * @return string|null
     */
    public function getRiderName()
    {
        return $this->container['riderName'];
    }

    /**
     * Sets riderName
     *
     * @param string|null $riderName 骑手姓名
     *
     * @return self
     */
    public function setRiderName($riderName)
    {
        $this->container['riderName'] = $riderName;

        return $this;
    }

    /**
     * Gets riderPoiLink
     *
     * @return string|null
     */
    public function getRiderPoiLink()
    {
        return $this->container['riderPoiLink'];
    }

    /**
     * Sets riderPoiLink
     *
     * @param string|null $riderPoiLink 骑手实时定位H5链接字段，在骑手已接单后，将可展示骑手实时位置的H5页面链接同步商户
     *
     * @return self
     */
    public function setRiderPoiLink($riderPoiLink)
    {
        $this->container['riderPoiLink'] = $riderPoiLink;

        return $this;
    }

    /**
     * Gets status
     *
     * @return string|null
     */
    public function getStatus()
    {
        return $this->container['status'];
    }

    /**
     * Sets status
     *
     * @param string|null $status 即时配送运单状态
     *
     * @return self
     */
    public function setStatus($status)
    {
        $this->container['status'] = $status;

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


