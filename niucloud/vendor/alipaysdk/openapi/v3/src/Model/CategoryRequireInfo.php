<?php
/**
 * CategoryRequireInfo
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
 * CategoryRequireInfo Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class CategoryRequireInfo implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'CategoryRequireInfo';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'businessLicenceRequired' => 'bool',
        'categoryCode' => 'string',
        'categoryName' => 'string',
        'categoryRequirements' => 'string',
        'doorPhotoRequired' => 'bool',
        'specialLicenceRequired' => 'bool'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'businessLicenceRequired' => null,
        'categoryCode' => null,
        'categoryName' => null,
        'categoryRequirements' => null,
        'doorPhotoRequired' => null,
        'specialLicenceRequired' => null
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
        'businessLicenceRequired' => 'business_licence_required',
        'categoryCode' => 'category_code',
        'categoryName' => 'category_name',
        'categoryRequirements' => 'category_requirements',
        'doorPhotoRequired' => 'door_photo_required',
        'specialLicenceRequired' => 'special_licence_required'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'businessLicenceRequired' => 'setBusinessLicenceRequired',
        'categoryCode' => 'setCategoryCode',
        'categoryName' => 'setCategoryName',
        'categoryRequirements' => 'setCategoryRequirements',
        'doorPhotoRequired' => 'setDoorPhotoRequired',
        'specialLicenceRequired' => 'setSpecialLicenceRequired'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'businessLicenceRequired' => 'getBusinessLicenceRequired',
        'categoryCode' => 'getCategoryCode',
        'categoryName' => 'getCategoryName',
        'categoryRequirements' => 'getCategoryRequirements',
        'doorPhotoRequired' => 'getDoorPhotoRequired',
        'specialLicenceRequired' => 'getSpecialLicenceRequired'
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
        $this->container['businessLicenceRequired'] = $data['businessLicenceRequired'] ?? null;
        $this->container['categoryCode'] = $data['categoryCode'] ?? null;
        $this->container['categoryName'] = $data['categoryName'] ?? null;
        $this->container['categoryRequirements'] = $data['categoryRequirements'] ?? null;
        $this->container['doorPhotoRequired'] = $data['doorPhotoRequired'] ?? null;
        $this->container['specialLicenceRequired'] = $data['specialLicenceRequired'] ?? null;
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
     * Gets businessLicenceRequired
     *
     * @return bool|null
     */
    public function getBusinessLicenceRequired()
    {
        return $this->container['businessLicenceRequired'];
    }

    /**
     * Sets businessLicenceRequired
     *
     * @param bool|null $businessLicenceRequired 营业执照页面是否必填
     *
     * @return self
     */
    public function setBusinessLicenceRequired($businessLicenceRequired)
    {
        $this->container['businessLicenceRequired'] = $businessLicenceRequired;

        return $this;
    }

    /**
     * Gets categoryCode
     *
     * @return string|null
     */
    public function getCategoryCode()
    {
        return $this->container['categoryCode'];
    }

    /**
     * Sets categoryCode
     *
     * @param string|null $categoryCode 类目code（各级类目code下划线\"_\"拼接）。类目信息参考alipay.open.mini.category.query
     *
     * @return self
     */
    public function setCategoryCode($categoryCode)
    {
        $this->container['categoryCode'] = $categoryCode;

        return $this;
    }

    /**
     * Gets categoryName
     *
     * @return string|null
     */
    public function getCategoryName()
    {
        return $this->container['categoryName'];
    }

    /**
     * Sets categoryName
     *
     * @param string|null $categoryName 类目名称（各级类目名称下划线\"_\"拼接）
     *
     * @return self
     */
    public function setCategoryName($categoryName)
    {
        $this->container['categoryName'] = $categoryName;

        return $this;
    }

    /**
     * Gets categoryRequirements
     *
     * @return string|null
     */
    public function getCategoryRequirements()
    {
        return $this->container['categoryRequirements'];
    }

    /**
     * Sets categoryRequirements
     *
     * @param string|null $categoryRequirements 类目要求原始描述信息
     *
     * @return self
     */
    public function setCategoryRequirements($categoryRequirements)
    {
        $this->container['categoryRequirements'] = $categoryRequirements;

        return $this;
    }

    /**
     * Gets doorPhotoRequired
     *
     * @return bool|null
     */
    public function getDoorPhotoRequired()
    {
        return $this->container['doorPhotoRequired'];
    }

    /**
     * Sets doorPhotoRequired
     *
     * @param bool|null $doorPhotoRequired 门头照页面是否必填
     *
     * @return self
     */
    public function setDoorPhotoRequired($doorPhotoRequired)
    {
        $this->container['doorPhotoRequired'] = $doorPhotoRequired;

        return $this;
    }

    /**
     * Gets specialLicenceRequired
     *
     * @return bool|null
     */
    public function getSpecialLicenceRequired()
    {
        return $this->container['specialLicenceRequired'];
    }

    /**
     * Sets specialLicenceRequired
     *
     * @param bool|null $specialLicenceRequired 特殊资质页面是否必填
     *
     * @return self
     */
    public function setSpecialLicenceRequired($specialLicenceRequired)
    {
        $this->container['specialLicenceRequired'] = $specialLicenceRequired;

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


