<?php
/**
 * AlipayUserCertdocCertverifyPreconsultModel
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
 * AlipayUserCertdocCertverifyPreconsultModel Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class AlipayUserCertdocCertverifyPreconsultModel implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AlipayUserCertdocCertverifyPreconsultModel';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'certNo' => 'string',
        'certType' => 'string',
        'extInfo' => 'string',
        'logonId' => 'string',
        'mobile' => 'string',
        'userName' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'certNo' => null,
        'certType' => null,
        'extInfo' => null,
        'logonId' => null,
        'mobile' => null,
        'userName' => null
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
        'certNo' => 'cert_no',
        'certType' => 'cert_type',
        'extInfo' => 'ext_info',
        'logonId' => 'logon_id',
        'mobile' => 'mobile',
        'userName' => 'user_name'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'certNo' => 'setCertNo',
        'certType' => 'setCertType',
        'extInfo' => 'setExtInfo',
        'logonId' => 'setLogonId',
        'mobile' => 'setMobile',
        'userName' => 'setUserName'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'certNo' => 'getCertNo',
        'certType' => 'getCertType',
        'extInfo' => 'getExtInfo',
        'logonId' => 'getLogonId',
        'mobile' => 'getMobile',
        'userName' => 'getUserName'
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
        $this->container['certNo'] = $data['certNo'] ?? null;
        $this->container['certType'] = $data['certType'] ?? null;
        $this->container['extInfo'] = $data['extInfo'] ?? null;
        $this->container['logonId'] = $data['logonId'] ?? null;
        $this->container['mobile'] = $data['mobile'] ?? null;
        $this->container['userName'] = $data['userName'] ?? null;
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
     * Gets certNo
     *
     * @return string|null
     */
    public function getCertNo()
    {
        return $this->container['certNo'];
    }

    /**
     * Sets certNo
     *
     * @param string|null $certNo 证件号
     *
     * @return self
     */
    public function setCertNo($certNo)
    {
        $this->container['certNo'] = $certNo;

        return $this;
    }

    /**
     * Gets certType
     *
     * @return string|null
     */
    public function getCertType()
    {
        return $this->container['certType'];
    }

    /**
     * Sets certType
     *
     * @param string|null $certType 证件类型。暂仅支持 IDENTITY_CARD （身份证）。
     *
     * @return self
     */
    public function setCertType($certType)
    {
        $this->container['certType'] = $certType;

        return $this;
    }

    /**
     * Gets extInfo
     *
     * @return string|null
     */
    public function getExtInfo()
    {
        return $this->container['extInfo'];
    }

    /**
     * Sets extInfo
     *
     * @param string|null $extInfo 拓展字段,JSON格式
     *
     * @return self
     */
    public function setExtInfo($extInfo)
    {
        $this->container['extInfo'] = $extInfo;

        return $this;
    }

    /**
     * Gets logonId
     *
     * @return string|null
     */
    public function getLogonId()
    {
        return $this->container['logonId'];
    }

    /**
     * Sets logonId
     *
     * @param string|null $logonId 支付宝登录名
     *
     * @return self
     */
    public function setLogonId($logonId)
    {
        $this->container['logonId'] = $logonId;

        return $this;
    }

    /**
     * Gets mobile
     *
     * @return string|null
     */
    public function getMobile()
    {
        return $this->container['mobile'];
    }

    /**
     * Sets mobile
     *
     * @param string|null $mobile 手机号码
     *
     * @return self
     */
    public function setMobile($mobile)
    {
        $this->container['mobile'] = $mobile;

        return $this;
    }

    /**
     * Gets userName
     *
     * @return string|null
     */
    public function getUserName()
    {
        return $this->container['userName'];
    }

    /**
     * Sets userName
     *
     * @param string|null $userName 真实姓名
     *
     * @return self
     */
    public function setUserName($userName)
    {
        $this->container['userName'] = $userName;

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


