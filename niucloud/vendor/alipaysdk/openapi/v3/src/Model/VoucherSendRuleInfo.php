<?php
/**
 * VoucherSendRuleInfo
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
 * VoucherSendRuleInfo Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class VoucherSendRuleInfo implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'VoucherSendRuleInfo';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'maxQuantityByDay' => 'int',
        'naturalPersonLimit' => 'bool',
        'phoneNumberLimit' => 'bool',
        'phoneNumberNeedInputLimit' => 'bool',
        'publishEndTime' => 'string',
        'publishStartTime' => 'string',
        'quantity' => 'int',
        'quantityLimitPerUser' => 'int',
        'quantityLimitPerUserPeriodType' => 'string',
        'realNameLimit' => 'bool'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'maxQuantityByDay' => null,
        'naturalPersonLimit' => null,
        'phoneNumberLimit' => null,
        'phoneNumberNeedInputLimit' => null,
        'publishEndTime' => null,
        'publishStartTime' => null,
        'quantity' => null,
        'quantityLimitPerUser' => null,
        'quantityLimitPerUserPeriodType' => null,
        'realNameLimit' => null
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
        'maxQuantityByDay' => 'max_quantity_by_day',
        'naturalPersonLimit' => 'natural_person_limit',
        'phoneNumberLimit' => 'phone_number_limit',
        'phoneNumberNeedInputLimit' => 'phone_number_need_input_limit',
        'publishEndTime' => 'publish_end_time',
        'publishStartTime' => 'publish_start_time',
        'quantity' => 'quantity',
        'quantityLimitPerUser' => 'quantity_limit_per_user',
        'quantityLimitPerUserPeriodType' => 'quantity_limit_per_user_period_type',
        'realNameLimit' => 'real_name_limit'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'maxQuantityByDay' => 'setMaxQuantityByDay',
        'naturalPersonLimit' => 'setNaturalPersonLimit',
        'phoneNumberLimit' => 'setPhoneNumberLimit',
        'phoneNumberNeedInputLimit' => 'setPhoneNumberNeedInputLimit',
        'publishEndTime' => 'setPublishEndTime',
        'publishStartTime' => 'setPublishStartTime',
        'quantity' => 'setQuantity',
        'quantityLimitPerUser' => 'setQuantityLimitPerUser',
        'quantityLimitPerUserPeriodType' => 'setQuantityLimitPerUserPeriodType',
        'realNameLimit' => 'setRealNameLimit'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'maxQuantityByDay' => 'getMaxQuantityByDay',
        'naturalPersonLimit' => 'getNaturalPersonLimit',
        'phoneNumberLimit' => 'getPhoneNumberLimit',
        'phoneNumberNeedInputLimit' => 'getPhoneNumberNeedInputLimit',
        'publishEndTime' => 'getPublishEndTime',
        'publishStartTime' => 'getPublishStartTime',
        'quantity' => 'getQuantity',
        'quantityLimitPerUser' => 'getQuantityLimitPerUser',
        'quantityLimitPerUserPeriodType' => 'getQuantityLimitPerUserPeriodType',
        'realNameLimit' => 'getRealNameLimit'
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
        $this->container['maxQuantityByDay'] = $data['maxQuantityByDay'] ?? null;
        $this->container['naturalPersonLimit'] = $data['naturalPersonLimit'] ?? null;
        $this->container['phoneNumberLimit'] = $data['phoneNumberLimit'] ?? null;
        $this->container['phoneNumberNeedInputLimit'] = $data['phoneNumberNeedInputLimit'] ?? null;
        $this->container['publishEndTime'] = $data['publishEndTime'] ?? null;
        $this->container['publishStartTime'] = $data['publishStartTime'] ?? null;
        $this->container['quantity'] = $data['quantity'] ?? null;
        $this->container['quantityLimitPerUser'] = $data['quantityLimitPerUser'] ?? null;
        $this->container['quantityLimitPerUserPeriodType'] = $data['quantityLimitPerUserPeriodType'] ?? null;
        $this->container['realNameLimit'] = $data['realNameLimit'] ?? null;
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
     * Gets maxQuantityByDay
     *
     * @return int|null
     */
    public function getMaxQuantityByDay()
    {
        return $this->container['maxQuantityByDay'];
    }

    /**
     * Sets maxQuantityByDay
     *
     * @param int|null $maxQuantityByDay 设置此字段，允许指定单天最大发券数量。
     *
     * @return self
     */
    public function setMaxQuantityByDay($maxQuantityByDay)
    {
        $this->container['maxQuantityByDay'] = $maxQuantityByDay;

        return $this;
    }

    /**
     * Gets naturalPersonLimit
     *
     * @return bool|null
     */
    public function getNaturalPersonLimit()
    {
        return $this->container['naturalPersonLimit'];
    }

    /**
     * Sets naturalPersonLimit
     *
     * @param bool|null $naturalPersonLimit 是否开启自然人领取限制。 自然人表示按照身份证纬度进行领取限制。
     *
     * @return self
     */
    public function setNaturalPersonLimit($naturalPersonLimit)
    {
        $this->container['naturalPersonLimit'] = $naturalPersonLimit;

        return $this;
    }

    /**
     * Gets phoneNumberLimit
     *
     * @return bool|null
     */
    public function getPhoneNumberLimit()
    {
        return $this->container['phoneNumberLimit'];
    }

    /**
     * Sets phoneNumberLimit
     *
     * @param bool|null $phoneNumberLimit 是否开启电话号码领取限制。
     *
     * @return self
     */
    public function setPhoneNumberLimit($phoneNumberLimit)
    {
        $this->container['phoneNumberLimit'] = $phoneNumberLimit;

        return $this;
    }

    /**
     * Gets phoneNumberNeedInputLimit
     *
     * @return bool|null
     */
    public function getPhoneNumberNeedInputLimit()
    {
        return $this->container['phoneNumberNeedInputLimit'];
    }

    /**
     * Sets phoneNumberNeedInputLimit
     *
     * @param bool|null $phoneNumberNeedInputLimit 下单时是否需要用户填写手机号码
     *
     * @return self
     */
    public function setPhoneNumberNeedInputLimit($phoneNumberNeedInputLimit)
    {
        $this->container['phoneNumberNeedInputLimit'] = $phoneNumberNeedInputLimit;

        return $this;
    }

    /**
     * Gets publishEndTime
     *
     * @return string|null
     */
    public function getPublishEndTime()
    {
        return $this->container['publishEndTime'];
    }

    /**
     * Sets publishEndTime
     *
     * @param string|null $publishEndTime 券发放结束时间。 格式为：yyyy-MM-dd HH:mm:ss
     *
     * @return self
     */
    public function setPublishEndTime($publishEndTime)
    {
        $this->container['publishEndTime'] = $publishEndTime;

        return $this;
    }

    /**
     * Gets publishStartTime
     *
     * @return string|null
     */
    public function getPublishStartTime()
    {
        return $this->container['publishStartTime'];
    }

    /**
     * Sets publishStartTime
     *
     * @param string|null $publishStartTime 券发放开始时间。 格式为：yyyy-MM-dd HH:mm:ss
     *
     * @return self
     */
    public function setPublishStartTime($publishStartTime)
    {
        $this->container['publishStartTime'] = $publishStartTime;

        return $this;
    }

    /**
     * Gets quantity
     *
     * @return int|null
     */
    public function getQuantity()
    {
        return $this->container['quantity'];
    }

    /**
     * Sets quantity
     *
     * @param int|null $quantity 发行券的数量。
     *
     * @return self
     */
    public function setQuantity($quantity)
    {
        $this->container['quantity'] = $quantity;

        return $this;
    }

    /**
     * Gets quantityLimitPerUser
     *
     * @return int|null
     */
    public function getQuantityLimitPerUser()
    {
        return $this->container['quantityLimitPerUser'];
    }

    /**
     * Sets quantityLimitPerUser
     *
     * @param int|null $quantityLimitPerUser 每人领取限制。 默认按照支付宝账号进行领取限制; 不填写或填入0表示没有领取限制.
     *
     * @return self
     */
    public function setQuantityLimitPerUser($quantityLimitPerUser)
    {
        $this->container['quantityLimitPerUser'] = $quantityLimitPerUser;

        return $this;
    }

    /**
     * Gets quantityLimitPerUserPeriodType
     *
     * @return string|null
     */
    public function getQuantityLimitPerUserPeriodType()
    {
        return $this->container['quantityLimitPerUserPeriodType'];
    }

    /**
     * Sets quantityLimitPerUserPeriodType
     *
     * @param string|null $quantityLimitPerUserPeriodType 周期限领配置,限制每人在固定周期内领取张数(voucher_quantity_limit_per_user),默认LIFE_CYCLE
     *
     * @return self
     */
    public function setQuantityLimitPerUserPeriodType($quantityLimitPerUserPeriodType)
    {
        $this->container['quantityLimitPerUserPeriodType'] = $quantityLimitPerUserPeriodType;

        return $this;
    }

    /**
     * Gets realNameLimit
     *
     * @return bool|null
     */
    public function getRealNameLimit()
    {
        return $this->container['realNameLimit'];
    }

    /**
     * Sets realNameLimit
     *
     * @param bool|null $realNameLimit 限制支付宝实名用户才能领取支付券,默认为false表示不限制 枚举值 true\\false
     *
     * @return self
     */
    public function setRealNameLimit($realNameLimit)
    {
        $this->container['realNameLimit'] = $realNameLimit;

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

