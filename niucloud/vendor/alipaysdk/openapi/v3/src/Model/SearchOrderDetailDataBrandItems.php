<?php
/**
 * SearchOrderDetailDataBrandItems
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
 * SearchOrderDetailDataBrandItems Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class SearchOrderDetailDataBrandItems implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'SearchOrderDetailDataBrandItems';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'bizId' => 'string',
        'boxStatus' => 'string',
        'brandBoxKeywords' => 'string',
        'brandDetailList' => '\Alipay\OpenAPISDK\Model\SearchOrderBrandDetail[]',
        'brandTemplateId' => 'string',
        'channel' => 'string',
        'merchantType' => 'string',
        'templateId' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'bizId' => null,
        'boxStatus' => null,
        'brandBoxKeywords' => null,
        'brandDetailList' => null,
        'brandTemplateId' => null,
        'channel' => null,
        'merchantType' => null,
        'templateId' => null
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
        'bizId' => 'biz_id',
        'boxStatus' => 'box_status',
        'brandBoxKeywords' => 'brand_box_keywords',
        'brandDetailList' => 'brand_detail_list',
        'brandTemplateId' => 'brand_template_id',
        'channel' => 'channel',
        'merchantType' => 'merchant_type',
        'templateId' => 'template_id'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'bizId' => 'setBizId',
        'boxStatus' => 'setBoxStatus',
        'brandBoxKeywords' => 'setBrandBoxKeywords',
        'brandDetailList' => 'setBrandDetailList',
        'brandTemplateId' => 'setBrandTemplateId',
        'channel' => 'setChannel',
        'merchantType' => 'setMerchantType',
        'templateId' => 'setTemplateId'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'bizId' => 'getBizId',
        'boxStatus' => 'getBoxStatus',
        'brandBoxKeywords' => 'getBrandBoxKeywords',
        'brandDetailList' => 'getBrandDetailList',
        'brandTemplateId' => 'getBrandTemplateId',
        'channel' => 'getChannel',
        'merchantType' => 'getMerchantType',
        'templateId' => 'getTemplateId'
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
        $this->container['bizId'] = $data['bizId'] ?? null;
        $this->container['boxStatus'] = $data['boxStatus'] ?? null;
        $this->container['brandBoxKeywords'] = $data['brandBoxKeywords'] ?? null;
        $this->container['brandDetailList'] = $data['brandDetailList'] ?? null;
        $this->container['brandTemplateId'] = $data['brandTemplateId'] ?? null;
        $this->container['channel'] = $data['channel'] ?? null;
        $this->container['merchantType'] = $data['merchantType'] ?? null;
        $this->container['templateId'] = $data['templateId'] ?? null;
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
     * Gets bizId
     *
     * @return string|null
     */
    public function getBizId()
    {
        return $this->container['bizId'];
    }

    /**
     * Sets bizId
     *
     * @param string|null $bizId 工单详情bizid
     *
     * @return self
     */
    public function setBizId($bizId)
    {
        $this->container['bizId'] = $bizId;

        return $this;
    }

    /**
     * Gets boxStatus
     *
     * @return string|null
     */
    public function getBoxStatus()
    {
        return $this->container['boxStatus'];
    }

    /**
     * Sets boxStatus
     *
     * @param string|null $boxStatus 上下架状态
     *
     * @return self
     */
    public function setBoxStatus($boxStatus)
    {
        $this->container['boxStatus'] = $boxStatus;

        return $this;
    }

    /**
     * Gets brandBoxKeywords
     *
     * @return string|null
     */
    public function getBrandBoxKeywords()
    {
        return $this->container['brandBoxKeywords'];
    }

    /**
     * Sets brandBoxKeywords
     *
     * @param string|null $brandBoxKeywords 关键词信息
     *
     * @return self
     */
    public function setBrandBoxKeywords($brandBoxKeywords)
    {
        $this->container['brandBoxKeywords'] = $brandBoxKeywords;

        return $this;
    }

    /**
     * Gets brandDetailList
     *
     * @return \Alipay\OpenAPISDK\Model\SearchOrderBrandDetail[]|null
     */
    public function getBrandDetailList()
    {
        return $this->container['brandDetailList'];
    }

    /**
     * Sets brandDetailList
     *
     * @param \Alipay\OpenAPISDK\Model\SearchOrderBrandDetail[]|null $brandDetailList 工单详情数据信息
     *
     * @return self
     */
    public function setBrandDetailList($brandDetailList)
    {
        $this->container['brandDetailList'] = $brandDetailList;

        return $this;
    }

    /**
     * Gets brandTemplateId
     *
     * @return string|null
     */
    public function getBrandTemplateId()
    {
        return $this->container['brandTemplateId'];
    }

    /**
     * Sets brandTemplateId
     *
     * @param string|null $brandTemplateId 品牌展示模板类型
     *
     * @return self
     */
    public function setBrandTemplateId($brandTemplateId)
    {
        $this->container['brandTemplateId'] = $brandTemplateId;

        return $this;
    }

    /**
     * Gets channel
     *
     * @return string|null
     */
    public function getChannel()
    {
        return $this->container['channel'];
    }

    /**
     * Sets channel
     *
     * @param string|null $channel 工单详情数据channel
     *
     * @return self
     */
    public function setChannel($channel)
    {
        $this->container['channel'] = $channel;

        return $this;
    }

    /**
     * Gets merchantType
     *
     * @return string|null
     */
    public function getMerchantType()
    {
        return $this->container['merchantType'];
    }

    /**
     * Sets merchantType
     *
     * @param string|null $merchantType 工单详情数据merchant_type
     *
     * @return self
     */
    public function setMerchantType($merchantType)
    {
        $this->container['merchantType'] = $merchantType;

        return $this;
    }

    /**
     * Gets templateId
     *
     * @return string|null
     */
    public function getTemplateId()
    {
        return $this->container['templateId'];
    }

    /**
     * Sets templateId
     *
     * @param string|null $templateId 模板id
     *
     * @return self
     */
    public function setTemplateId($templateId)
    {
        $this->container['templateId'] = $templateId;

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


