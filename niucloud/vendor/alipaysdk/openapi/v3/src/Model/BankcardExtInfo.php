<?php
/**
 * BankcardExtInfo
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
 * BankcardExtInfo Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class BankcardExtInfo implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'BankcardExtInfo';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'accountType' => 'string',
        'bankCode' => 'string',
        'instBranchName' => 'string',
        'instCity' => 'string',
        'instName' => 'string',
        'instProvince' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'accountType' => null,
        'bankCode' => null,
        'instBranchName' => null,
        'instCity' => null,
        'instName' => null,
        'instProvince' => null
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
        'accountType' => 'account_type',
        'bankCode' => 'bank_code',
        'instBranchName' => 'inst_branch_name',
        'instCity' => 'inst_city',
        'instName' => 'inst_name',
        'instProvince' => 'inst_province'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'accountType' => 'setAccountType',
        'bankCode' => 'setBankCode',
        'instBranchName' => 'setInstBranchName',
        'instCity' => 'setInstCity',
        'instName' => 'setInstName',
        'instProvince' => 'setInstProvince'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'accountType' => 'getAccountType',
        'bankCode' => 'getBankCode',
        'instBranchName' => 'getInstBranchName',
        'instCity' => 'getInstCity',
        'instName' => 'getInstName',
        'instProvince' => 'getInstProvince'
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
        $this->container['accountType'] = $data['accountType'] ?? null;
        $this->container['bankCode'] = $data['bankCode'] ?? null;
        $this->container['instBranchName'] = $data['instBranchName'] ?? null;
        $this->container['instCity'] = $data['instCity'] ?? null;
        $this->container['instName'] = $data['instName'] ?? null;
        $this->container['instProvince'] = $data['instProvince'] ?? null;
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
     * Gets accountType
     *
     * @return string|null
     */
    public function getAccountType()
    {
        return $this->container['accountType'];
    }

    /**
     * Sets accountType
     *
     * @param string|null $accountType 收款账户类型。  1：对公（在金融机构开设的公司账户）,如果银行卡为对公，必须传递省市支行信息或者联行号  2：对私（在金融机构开设的个人账户）
     *
     * @return self
     */
    public function setAccountType($accountType)
    {
        $this->container['accountType'] = $accountType;

        return $this;
    }

    /**
     * Gets bankCode
     *
     * @return string|null
     */
    public function getBankCode()
    {
        return $this->container['bankCode'];
    }

    /**
     * Sets bankCode
     *
     * @param string|null $bankCode 银行支行联行号
     *
     * @return self
     */
    public function setBankCode($bankCode)
    {
        $this->container['bankCode'] = $bankCode;

        return $this;
    }

    /**
     * Gets instBranchName
     *
     * @return string|null
     */
    public function getInstBranchName()
    {
        return $this->container['instBranchName'];
    }

    /**
     * Sets instBranchName
     *
     * @param string|null $instBranchName 收款银行所属支行
     *
     * @return self
     */
    public function setInstBranchName($instBranchName)
    {
        $this->container['instBranchName'] = $instBranchName;

        return $this;
    }

    /**
     * Gets instCity
     *
     * @return string|null
     */
    public function getInstCity()
    {
        return $this->container['instCity'];
    }

    /**
     * Sets instCity
     *
     * @param string|null $instCity 收款银行所在市
     *
     * @return self
     */
    public function setInstCity($instCity)
    {
        $this->container['instCity'] = $instCity;

        return $this;
    }

    /**
     * Gets instName
     *
     * @return string|null
     */
    public function getInstName()
    {
        return $this->container['instName'];
    }

    /**
     * Sets instName
     *
     * @param string|null $instName 机构名称
     *
     * @return self
     */
    public function setInstName($instName)
    {
        $this->container['instName'] = $instName;

        return $this;
    }

    /**
     * Gets instProvince
     *
     * @return string|null
     */
    public function getInstProvince()
    {
        return $this->container['instProvince'];
    }

    /**
     * Sets instProvince
     *
     * @param string|null $instProvince 银行所在省份
     *
     * @return self
     */
    public function setInstProvince($instProvince)
    {
        $this->container['instProvince'] = $instProvince;

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


