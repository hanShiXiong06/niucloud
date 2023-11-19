<?php
/**
 * AlipayFundTransToaccountTransferErrorResponseModel
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
 * AlipayFundTransToaccountTransferErrorResponseModel Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class AlipayFundTransToaccountTransferErrorResponseModel implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AlipayFundTransToaccountTransferErrorResponseModel';

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

    public const CODE_INVALID_PARAMETER = 'INVALID_PARAMETER';
    public const CODE_SYSTEM_ERROR = 'SYSTEM_ERROR';
    public const CODE_EXCEED_LIMIT_SM_AMOUNT = 'EXCEED_LIMIT_SM_AMOUNT';
    public const CODE_EXCEED_LIMIT_DM_AMOUNT = 'EXCEED_LIMIT_DM_AMOUNT';
    public const CODE_PERMIT_CHECK_PERM_LIMITED = 'PERMIT_CHECK_PERM_LIMITED';
    public const CODE_PAYCARD_UNABLE_PAYMENT = 'PAYCARD_UNABLE_PAYMENT';
    public const CODE_PAYEE_NOT_EXIST = 'PAYEE_NOT_EXIST';
    public const CODE_PAYER_DATA_INCOMPLETE = 'PAYER_DATA_INCOMPLETE';
    public const CODE_PERM_AML_NOT_REALNAME_REV = 'PERM_AML_NOT_REALNAME_REV';
    public const CODE_PAYER_STATUS_ERROR = 'PAYER_STATUS_ERROR';
    public const CODE_PAYEE_USER_INFO_ERROR = 'PAYEE_USER_INFO_ERROR';
    public const CODE_PAYER_USER_INFO_ERROR = 'PAYER_USER_INFO_ERROR';
    public const CODE_PAYER_BALANCE_NOT_ENOUGH = 'PAYER_BALANCE_NOT_ENOUGH';
    public const CODE_PAYMENT_INFO_INCONSISTENCY = 'PAYMENT_INFO_INCONSISTENCY';
    public const CODE_CERT_MISS_TRANS_LIMIT = 'CERT_MISS_TRANS_LIMIT';
    public const CODE_CERT_MISS_ACC_LIMIT = 'CERT_MISS_ACC_LIMIT';
    public const CODE_PAYEE_ACC_OCUPIED = 'PAYEE_ACC_OCUPIED';
    public const CODE_MEMO_REQUIRED_IN_TRANSFER_ERROR = 'MEMO_REQUIRED_IN_TRANSFER_ERROR';
    public const CODE_PERMIT_NON_BANK_LIMIT_PAYEE = 'PERMIT_NON_BANK_LIMIT_PAYEE';
    public const CODE_PERMIT_PAYER_LOWEST_FORBIDDEN = 'PERMIT_PAYER_LOWEST_FORBIDDEN';
    public const CODE_PERMIT_PAYER_FORBIDDEN = 'PERMIT_PAYER_FORBIDDEN';
    public const CODE_PERMIT_CHECK_PERM_IDENTITY_THEFT = 'PERMIT_CHECK_PERM_IDENTITY_THEFT';
    public const CODE_REMARK_HAS_SENSITIVE_WORD = 'REMARK_HAS_SENSITIVE_WORD';
    public const CODE_ACCOUNT_NOT_EXIST = 'ACCOUNT_NOT_EXIST';
    public const CODE_PAYER_CERT_EXPIRED = 'PAYER_CERT_EXPIRED';
    public const CODE_EXCEED_LIMIT_PERSONAL_SM_AMOUNT = 'EXCEED_LIMIT_PERSONAL_SM_AMOUNT';
    public const CODE_EXCEED_LIMIT_ENT_SM_AMOUNT = 'EXCEED_LIMIT_ENT_SM_AMOUNT';
    public const CODE_EXCEED_LIMIT_SM_MIN_AMOUNT = 'EXCEED_LIMIT_SM_MIN_AMOUNT';
    public const CODE_EXCEED_LIMIT_DM_MAX_AMOUNT = 'EXCEED_LIMIT_DM_MAX_AMOUNT';
    public const CODE_EXCEED_LIMIT_UNRN_DM_AMOUNT = 'EXCEED_LIMIT_UNRN_DM_AMOUNT';
    public const CODE_PAYER_PAYEE_CANNOT_SAME = 'PAYER_PAYEE_CANNOT_SAME';
    public const CODE_SYNC_SECURITY_CHECK_FAILED = 'SYNC_SECURITY_CHECK_FAILED';
    public const CODE_MAX_VISIT_LIMIT = 'MAX_VISIT_LIMIT';
    public const CODE_RELEASE_USER_FORBBIDEN_RECIEVE = 'RELEASE_USER_FORBBIDEN_RECIEVE';
    public const CODE_PAYEE_USER_TYPE_ERROR = 'PAYEE_USER_TYPE_ERROR';
    public const CODE_BLOCK_USER_FORBBIDEN_RECIEVE = 'BLOCK_USER_FORBBIDEN_RECIEVE';
    public const CODE_REQUEST_PROCESSING = 'REQUEST_PROCESSING';
    public const CODE_EXCEED_LIMIT_MM_AMOUNT = 'EXCEED_LIMIT_MM_AMOUNT';
    public const CODE_PERM_RECEIVE_CUSTOMER_ALMS_LIMIT = 'PERM_RECEIVE_CUSTOMER_ALMS_LIMIT';
    public const CODE_PERM_PAY_CUSTOMER_MONTH_QUOTA_ORG_BALANCE_LIMIT = 'PERM_PAY_CUSTOMER_MONTH_QUOTA_ORG_BALANCE_LIMIT';

    /**
     * Gets allowable values of the enum
     *
     * @return string[]
     */
    public function getCodeAllowableValues()
    {
        return [
            self::CODE_INVALID_PARAMETER,
            self::CODE_SYSTEM_ERROR,
            self::CODE_EXCEED_LIMIT_SM_AMOUNT,
            self::CODE_EXCEED_LIMIT_DM_AMOUNT,
            self::CODE_PERMIT_CHECK_PERM_LIMITED,
            self::CODE_PAYCARD_UNABLE_PAYMENT,
            self::CODE_PAYEE_NOT_EXIST,
            self::CODE_PAYER_DATA_INCOMPLETE,
            self::CODE_PERM_AML_NOT_REALNAME_REV,
            self::CODE_PAYER_STATUS_ERROR,
            self::CODE_PAYEE_USER_INFO_ERROR,
            self::CODE_PAYER_USER_INFO_ERROR,
            self::CODE_PAYER_BALANCE_NOT_ENOUGH,
            self::CODE_PAYMENT_INFO_INCONSISTENCY,
            self::CODE_CERT_MISS_TRANS_LIMIT,
            self::CODE_CERT_MISS_ACC_LIMIT,
            self::CODE_PAYEE_ACC_OCUPIED,
            self::CODE_MEMO_REQUIRED_IN_TRANSFER_ERROR,
            self::CODE_PERMIT_NON_BANK_LIMIT_PAYEE,
            self::CODE_PERMIT_PAYER_LOWEST_FORBIDDEN,
            self::CODE_PERMIT_PAYER_FORBIDDEN,
            self::CODE_PERMIT_CHECK_PERM_IDENTITY_THEFT,
            self::CODE_REMARK_HAS_SENSITIVE_WORD,
            self::CODE_ACCOUNT_NOT_EXIST,
            self::CODE_PAYER_CERT_EXPIRED,
            self::CODE_EXCEED_LIMIT_PERSONAL_SM_AMOUNT,
            self::CODE_EXCEED_LIMIT_ENT_SM_AMOUNT,
            self::CODE_EXCEED_LIMIT_SM_MIN_AMOUNT,
            self::CODE_EXCEED_LIMIT_DM_MAX_AMOUNT,
            self::CODE_EXCEED_LIMIT_UNRN_DM_AMOUNT,
            self::CODE_PAYER_PAYEE_CANNOT_SAME,
            self::CODE_SYNC_SECURITY_CHECK_FAILED,
            self::CODE_MAX_VISIT_LIMIT,
            self::CODE_RELEASE_USER_FORBBIDEN_RECIEVE,
            self::CODE_PAYEE_USER_TYPE_ERROR,
            self::CODE_BLOCK_USER_FORBBIDEN_RECIEVE,
            self::CODE_REQUEST_PROCESSING,
            self::CODE_EXCEED_LIMIT_MM_AMOUNT,
            self::CODE_PERM_RECEIVE_CUSTOMER_ALMS_LIMIT,
            self::CODE_PERM_PAY_CUSTOMER_MONTH_QUOTA_ORG_BALANCE_LIMIT,
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


