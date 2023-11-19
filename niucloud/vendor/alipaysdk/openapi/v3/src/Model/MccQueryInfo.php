<?php
/**
 * MccQueryInfo
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
 * MccQueryInfo Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class MccQueryInfo implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'MccQueryInfo';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'isSpecial' => 'bool',
        'mccLevel1' => 'string',
        'mccLevel1Name' => 'string',
        'mccLevel2' => 'string',
        'mccLevel2Name' => 'string',
        'mccRequirements' => 'string',
        'specialQualRequired' => 'bool'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'isSpecial' => null,
        'mccLevel1' => null,
        'mccLevel1Name' => null,
        'mccLevel2' => null,
        'mccLevel2Name' => null,
        'mccRequirements' => null,
        'specialQualRequired' => null
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
        'isSpecial' => 'is_special',
        'mccLevel1' => 'mcc_level_1',
        'mccLevel1Name' => 'mcc_level_1_name',
        'mccLevel2' => 'mcc_level_2',
        'mccLevel2Name' => 'mcc_level_2_name',
        'mccRequirements' => 'mcc_requirements',
        'specialQualRequired' => 'special_qual_required'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'isSpecial' => 'setIsSpecial',
        'mccLevel1' => 'setMccLevel1',
        'mccLevel1Name' => 'setMccLevel1Name',
        'mccLevel2' => 'setMccLevel2',
        'mccLevel2Name' => 'setMccLevel2Name',
        'mccRequirements' => 'setMccRequirements',
        'specialQualRequired' => 'setSpecialQualRequired'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'isSpecial' => 'getIsSpecial',
        'mccLevel1' => 'getMccLevel1',
        'mccLevel1Name' => 'getMccLevel1Name',
        'mccLevel2' => 'getMccLevel2',
        'mccLevel2Name' => 'getMccLevel2Name',
        'mccRequirements' => 'getMccRequirements',
        'specialQualRequired' => 'getSpecialQualRequired'
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
        $this->container['isSpecial'] = $data['isSpecial'] ?? null;
        $this->container['mccLevel1'] = $data['mccLevel1'] ?? null;
        $this->container['mccLevel1Name'] = $data['mccLevel1Name'] ?? null;
        $this->container['mccLevel2'] = $data['mccLevel2'] ?? null;
        $this->container['mccLevel2Name'] = $data['mccLevel2Name'] ?? null;
        $this->container['mccRequirements'] = $data['mccRequirements'] ?? null;
        $this->container['specialQualRequired'] = $data['specialQualRequired'] ?? null;
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
     * Gets isSpecial
     *
     * @return bool|null
     */
    public function getIsSpecial()
    {
        return $this->container['isSpecial'];
    }

    /**
     * Sets isSpecial
     *
     * @param bool|null $isSpecial 是否特殊行业
     *
     * @return self
     */
    public function setIsSpecial($isSpecial)
    {
        $this->container['isSpecial'] = $isSpecial;

        return $this;
    }

    /**
     * Gets mccLevel1
     *
     * @return string|null
     */
    public function getMccLevel1()
    {
        return $this->container['mccLevel1'];
    }

    /**
     * Sets mccLevel1
     *
     * @param string|null $mccLevel1 一级类目code
     *
     * @return self
     */
    public function setMccLevel1($mccLevel1)
    {
        $this->container['mccLevel1'] = $mccLevel1;

        return $this;
    }

    /**
     * Gets mccLevel1Name
     *
     * @return string|null
     */
    public function getMccLevel1Name()
    {
        return $this->container['mccLevel1Name'];
    }

    /**
     * Sets mccLevel1Name
     *
     * @param string|null $mccLevel1Name 商户一级类目名称
     *
     * @return self
     */
    public function setMccLevel1Name($mccLevel1Name)
    {
        $this->container['mccLevel1Name'] = $mccLevel1Name;

        return $this;
    }

    /**
     * Gets mccLevel2
     *
     * @return string|null
     */
    public function getMccLevel2()
    {
        return $this->container['mccLevel2'];
    }

    /**
     * Sets mccLevel2
     *
     * @param string|null $mccLevel2 二级类目code
     *
     * @return self
     */
    public function setMccLevel2($mccLevel2)
    {
        $this->container['mccLevel2'] = $mccLevel2;

        return $this;
    }

    /**
     * Gets mccLevel2Name
     *
     * @return string|null
     */
    public function getMccLevel2Name()
    {
        return $this->container['mccLevel2Name'];
    }

    /**
     * Sets mccLevel2Name
     *
     * @param string|null $mccLevel2Name 二级类目名称
     *
     * @return self
     */
    public function setMccLevel2Name($mccLevel2Name)
    {
        $this->container['mccLevel2Name'] = $mccLevel2Name;

        return $this;
    }

    /**
     * Gets mccRequirements
     *
     * @return string|null
     */
    public function getMccRequirements()
    {
        return $this->container['mccRequirements'];
    }

    /**
     * Sets mccRequirements
     *
     * @param string|null $mccRequirements 特殊行业需要上传的资质
     *
     * @return self
     */
    public function setMccRequirements($mccRequirements)
    {
        $this->container['mccRequirements'] = $mccRequirements;

        return $this;
    }

    /**
     * Gets specialQualRequired
     *
     * @return bool|null
     */
    public function getSpecialQualRequired()
    {
        return $this->container['specialQualRequired'];
    }

    /**
     * Sets specialQualRequired
     *
     * @param bool|null $specialQualRequired 是否需要特殊资质
     *
     * @return self
     */
    public function setSpecialQualRequired($specialQualRequired)
    {
        $this->container['specialQualRequired'] = $specialQualRequired;

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


