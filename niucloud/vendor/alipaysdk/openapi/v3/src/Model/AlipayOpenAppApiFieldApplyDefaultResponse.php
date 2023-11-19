<?php
/**
 * AlipayOpenAppApiFieldApplyDefaultResponse
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
 * AlipayOpenAppApiFieldApplyDefaultResponse Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class AlipayOpenAppApiFieldApplyDefaultResponse implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'alipay_open_app_api_field_apply_default_response';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'code' => 'string',
        'links' => 'string',
        'message' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'code' => null,
        'links' => null,
        'message' => null
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
        'code' => 'code',
        'links' => 'links',
        'message' => 'message'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'code' => 'setCode',
        'links' => 'setLinks',
        'message' => 'setMessage'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'code' => 'getCode',
        'links' => 'getLinks',
        'message' => 'getMessage'
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

    public const CODE_BIZ_ERROR = 'BIZ_ERROR';
    public const CODE_SYSTEM_ERROR = 'SYSTEM_ERROR';
    public const CODE_APP_ID_IS_BLANK = 'APP_ID_IS_BLANK';
    public const CODE_APP_INFO_NOT_EXIST = 'APP_INFO_NOT_EXIST';
    public const CODE_AUTHFIELDAPPLY_IS_BLANK = 'AUTHFIELDAPPLY_IS_BLANK';
    public const CODE_API_NAME_IS_BLANK = 'API_NAME_IS_BLANK';
    public const CODE_FIELD_NAME_IS_BLANK = 'FIELD_NAME_IS_BLANK';
    public const CODE_PACKAGE_CODE_IS_BLANK = 'PACKAGE_CODE_IS_BLANK';
    public const CODE_SCENE_CODE_IS_BLANK = 'SCENE_CODE_IS_BLANK';
    public const CODE_QPS_ANSWER_IS_BLANK = 'QPS_ANSWER_IS_BLANK';
    public const CODE_CUSTOMER_ANSWER_IS_BLANK = 'CUSTOMER_ANSWER_IS_BLANK';
    public const CODE_FILE_ITEMS_IS_BLANK = 'FILE_ITEMS_IS_BLANK';
    public const CODE_AUTH_FIELD_SCENE_IS_BLANK = 'AUTH_FIELD_SCENE_IS_BLANK';
    public const CODE_SCENE_CODE_ERROR = 'SCENE_CODE_ERROR';
    public const CODE_USER_ID_NO_PERMISSION = 'USER_ID_NO_PERMISSION';
    public const CODE_SHOW_PACKAGE_IS_BLANK = 'SHOW_PACKAGE_IS_BLANK';
    public const CODE_PACKAGE_CODE_ERROR = 'PACKAGE_CODE_ERROR';
    public const CODE_TINYAPP_HEALTH_CHECK_INVALID = 'TINYAPP_HEALTH_CHECK_INVALID';
    public const CODE_TINYAPP_SAFETY_CHECK_INVALID = 'TINYAPP_SAFETY_CHECK_INVALID';
    public const CODE_API_NAME_ERROR = 'API_NAME_ERROR';
    public const CODE_AUTH_API_FIELD_IS_BLANK = 'AUTH_API_FIELD_IS_BLANK';
    public const CODE_AUTH_API_APPLY_IS_PASS = 'AUTH_API_APPLY_IS_PASS';
    public const CODE_FILE_FORMAT_IS_INVALID = 'FILE_FORMAT_IS_INVALID';
    public const CODE_FILE_SIZE_MIN_LIMIT = 'FILE_SIZE_MIN_LIMIT';
    public const CODE_FILE_SIZE_OUT_LIMIT = 'FILE_SIZE_OUT_LIMIT';
    public const CODE_FILE_QUALITY_IS_INVALID = 'FILE_QUALITY_IS_INVALID';
    public const CODE_AUTH_API_FIELD_IS_AUDITING = 'AUTH_API_FIELD_IS_AUDITING';
    public const CODE_ACCOUNT_TYPE_ERROR = 'ACCOUNT_TYPE_ERROR';

    /**
     * Gets allowable values of the enum
     *
     * @return string[]
     */
    public function getCodeAllowableValues()
    {
        return [
            self::CODE_BIZ_ERROR,
            self::CODE_SYSTEM_ERROR,
            self::CODE_APP_ID_IS_BLANK,
            self::CODE_APP_INFO_NOT_EXIST,
            self::CODE_AUTHFIELDAPPLY_IS_BLANK,
            self::CODE_API_NAME_IS_BLANK,
            self::CODE_FIELD_NAME_IS_BLANK,
            self::CODE_PACKAGE_CODE_IS_BLANK,
            self::CODE_SCENE_CODE_IS_BLANK,
            self::CODE_QPS_ANSWER_IS_BLANK,
            self::CODE_CUSTOMER_ANSWER_IS_BLANK,
            self::CODE_FILE_ITEMS_IS_BLANK,
            self::CODE_AUTH_FIELD_SCENE_IS_BLANK,
            self::CODE_SCENE_CODE_ERROR,
            self::CODE_USER_ID_NO_PERMISSION,
            self::CODE_SHOW_PACKAGE_IS_BLANK,
            self::CODE_PACKAGE_CODE_ERROR,
            self::CODE_TINYAPP_HEALTH_CHECK_INVALID,
            self::CODE_TINYAPP_SAFETY_CHECK_INVALID,
            self::CODE_API_NAME_ERROR,
            self::CODE_AUTH_API_FIELD_IS_BLANK,
            self::CODE_AUTH_API_APPLY_IS_PASS,
            self::CODE_FILE_FORMAT_IS_INVALID,
            self::CODE_FILE_SIZE_MIN_LIMIT,
            self::CODE_FILE_SIZE_OUT_LIMIT,
            self::CODE_FILE_QUALITY_IS_INVALID,
            self::CODE_AUTH_API_FIELD_IS_AUDITING,
            self::CODE_ACCOUNT_TYPE_ERROR,
        ];
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
        $this->container['code'] = $data['code'] ?? null;
        $this->container['links'] = $data['links'] ?? null;
        $this->container['message'] = $data['message'] ?? null;
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        if ($this->container['code'] === null) {
            $invalidProperties[] = "'code' can't be null";
        }
        $allowedValues = $this->getCodeAllowableValues();
        if (!is_null($this->container['code']) && !in_array($this->container['code'], $allowedValues, true)) {
            $invalidProperties[] = sprintf(
                "invalid value '%s' for 'code', must be one of '%s'",
                $this->container['code'],
                implode("', '", $allowedValues)
            );
        }

        if ($this->container['message'] === null) {
            $invalidProperties[] = "'message' can't be null";
        }
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
     * Gets code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->container['code'];
    }

    /**
     * Sets code
     *
     * @param string $code 错误码
     *
     * @return self
     */
    public function setCode($code)
    {
        $allowedValues = $this->getCodeAllowableValues();
        if (!in_array($code, $allowedValues, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Invalid value '%s' for 'code', must be one of '%s'",
                    $code,
                    implode("', '", $allowedValues)
                )
            );
        }
        $this->container['code'] = $code;

        return $this;
    }

    /**
     * Gets links
     *
     * @return string|null
     */
    public function getLinks()
    {
        return $this->container['links'];
    }

    /**
     * Sets links
     *
     * @param string|null $links 解决方案链接
     *
     * @return self
     */
    public function setLinks($links)
    {
        $this->container['links'] = $links;

        return $this;
    }

    /**
     * Gets message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->container['message'];
    }

    /**
     * Sets message
     *
     * @param string $message 错误描述
     *
     * @return self
     */
    public function setMessage($message)
    {
        $this->container['message'] = $message;

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


