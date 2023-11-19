<?php
/**
 * AlipayOpenPublicTemplateMessageIndustryModifyModel
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
 * AlipayOpenPublicTemplateMessageIndustryModifyModel Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class AlipayOpenPublicTemplateMessageIndustryModifyModel implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AlipayOpenPublicTemplateMessageIndustryModifyModel';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'primaryIndustryCode' => 'string',
        'primaryIndustryName' => 'string',
        'secondaryIndustryCode' => 'string',
        'secondaryIndustryName' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'primaryIndustryCode' => null,
        'primaryIndustryName' => null,
        'secondaryIndustryCode' => null,
        'secondaryIndustryName' => null
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
        'primaryIndustryCode' => 'primary_industry_code',
        'primaryIndustryName' => 'primary_industry_name',
        'secondaryIndustryCode' => 'secondary_industry_code',
        'secondaryIndustryName' => 'secondary_industry_name'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'primaryIndustryCode' => 'setPrimaryIndustryCode',
        'primaryIndustryName' => 'setPrimaryIndustryName',
        'secondaryIndustryCode' => 'setSecondaryIndustryCode',
        'secondaryIndustryName' => 'setSecondaryIndustryName'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'primaryIndustryCode' => 'getPrimaryIndustryCode',
        'primaryIndustryName' => 'getPrimaryIndustryName',
        'secondaryIndustryCode' => 'getSecondaryIndustryCode',
        'secondaryIndustryName' => 'getSecondaryIndustryName'
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
        $this->container['primaryIndustryCode'] = $data['primaryIndustryCode'] ?? null;
        $this->container['primaryIndustryName'] = $data['primaryIndustryName'] ?? null;
        $this->container['secondaryIndustryCode'] = $data['secondaryIndustryCode'] ?? null;
        $this->container['secondaryIndustryName'] = $data['secondaryIndustryName'] ?? null;
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
     * Gets primaryIndustryCode
     *
     * @return string|null
     */
    public function getPrimaryIndustryCode()
    {
        return $this->container['primaryIndustryCode'];
    }

    /**
     * Sets primaryIndustryCode
     *
     * @param string|null $primaryIndustryCode 服务窗消息模板所属主行业一/二级编码，参见 <a href=\"https://alipay.open.taobao.com/doc2/detail?treeId=197&docType=1&articleId=105043\">查看行业信息</a>。
     *
     * @return self
     */
    public function setPrimaryIndustryCode($primaryIndustryCode)
    {
        $this->container['primaryIndustryCode'] = $primaryIndustryCode;

        return $this;
    }

    /**
     * Gets primaryIndustryName
     *
     * @return string|null
     */
    public function getPrimaryIndustryName()
    {
        return $this->container['primaryIndustryName'];
    }

    /**
     * Sets primaryIndustryName
     *
     * @param string|null $primaryIndustryName 服务窗消息模板所属主行业一/二级名称，参见 <a href=\"https://alipay.open.taobao.com/doc2/detail?treeId=197&docType=1&articleId=105043\">查看行业信息</a>。
     *
     * @return self
     */
    public function setPrimaryIndustryName($primaryIndustryName)
    {
        $this->container['primaryIndustryName'] = $primaryIndustryName;

        return $this;
    }

    /**
     * Gets secondaryIndustryCode
     *
     * @return string|null
     */
    public function getSecondaryIndustryCode()
    {
        return $this->container['secondaryIndustryCode'];
    }

    /**
     * Sets secondaryIndustryCode
     *
     * @param string|null $secondaryIndustryCode 服务窗消息模板所属副行业一/二级编码，参见 <a href=\"https://alipay.open.taobao.com/doc2/detail?treeId=197&docType=1&articleId=105043\">查看行业信息</a>。
     *
     * @return self
     */
    public function setSecondaryIndustryCode($secondaryIndustryCode)
    {
        $this->container['secondaryIndustryCode'] = $secondaryIndustryCode;

        return $this;
    }

    /**
     * Gets secondaryIndustryName
     *
     * @return string|null
     */
    public function getSecondaryIndustryName()
    {
        return $this->container['secondaryIndustryName'];
    }

    /**
     * Sets secondaryIndustryName
     *
     * @param string|null $secondaryIndustryName 服务窗消息模板所属副行业一/二级名称，参见 <a href=\"https://alipay.open.taobao.com/doc2/detail?treeId=197&docType=1&articleId=105043\">查看行业信息</a>。
     *
     * @return self
     */
    public function setSecondaryIndustryName($secondaryIndustryName)
    {
        $this->container['secondaryIndustryName'] = $secondaryIndustryName;

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


