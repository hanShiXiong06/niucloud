<?php
/**
 * IntactChargeInfo
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
 * IntactChargeInfo Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class IntactChargeInfo implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'IntactChargeInfo';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'actualAmount' => 'string',
        'billType' => 'string',
        'gmtPay' => 'string',
        'isRefund' => 'bool',
        'outBizNo' => 'string',
        'planAmount' => 'string',
        'productName' => 'string',
        'serviceTarget' => 'string',
        'serviceType' => 'string',
        'status' => 'string',
        'targetAccountNo' => 'string',
        'targetUserId' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'actualAmount' => null,
        'billType' => null,
        'gmtPay' => null,
        'isRefund' => null,
        'outBizNo' => null,
        'planAmount' => null,
        'productName' => null,
        'serviceTarget' => null,
        'serviceType' => null,
        'status' => null,
        'targetAccountNo' => null,
        'targetUserId' => null
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
        'actualAmount' => 'actual_amount',
        'billType' => 'bill_type',
        'gmtPay' => 'gmt_pay',
        'isRefund' => 'is_refund',
        'outBizNo' => 'out_biz_no',
        'planAmount' => 'plan_amount',
        'productName' => 'product_name',
        'serviceTarget' => 'service_target',
        'serviceType' => 'service_type',
        'status' => 'status',
        'targetAccountNo' => 'target_account_no',
        'targetUserId' => 'target_user_id'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'actualAmount' => 'setActualAmount',
        'billType' => 'setBillType',
        'gmtPay' => 'setGmtPay',
        'isRefund' => 'setIsRefund',
        'outBizNo' => 'setOutBizNo',
        'planAmount' => 'setPlanAmount',
        'productName' => 'setProductName',
        'serviceTarget' => 'setServiceTarget',
        'serviceType' => 'setServiceType',
        'status' => 'setStatus',
        'targetAccountNo' => 'setTargetAccountNo',
        'targetUserId' => 'setTargetUserId'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'actualAmount' => 'getActualAmount',
        'billType' => 'getBillType',
        'gmtPay' => 'getGmtPay',
        'isRefund' => 'getIsRefund',
        'outBizNo' => 'getOutBizNo',
        'planAmount' => 'getPlanAmount',
        'productName' => 'getProductName',
        'serviceTarget' => 'getServiceTarget',
        'serviceType' => 'getServiceType',
        'status' => 'getStatus',
        'targetAccountNo' => 'getTargetAccountNo',
        'targetUserId' => 'getTargetUserId'
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
        $this->container['actualAmount'] = $data['actualAmount'] ?? null;
        $this->container['billType'] = $data['billType'] ?? null;
        $this->container['gmtPay'] = $data['gmtPay'] ?? null;
        $this->container['isRefund'] = $data['isRefund'] ?? null;
        $this->container['outBizNo'] = $data['outBizNo'] ?? null;
        $this->container['planAmount'] = $data['planAmount'] ?? null;
        $this->container['productName'] = $data['productName'] ?? null;
        $this->container['serviceTarget'] = $data['serviceTarget'] ?? null;
        $this->container['serviceType'] = $data['serviceType'] ?? null;
        $this->container['status'] = $data['status'] ?? null;
        $this->container['targetAccountNo'] = $data['targetAccountNo'] ?? null;
        $this->container['targetUserId'] = $data['targetUserId'] ?? null;
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
     * Gets actualAmount
     *
     * @return string|null
     */
    public function getActualAmount()
    {
        return $this->container['actualAmount'];
    }

    /**
     * Sets actualAmount
     *
     * @param string|null $actualAmount 实际收费金额，单位元
     *
     * @return self
     */
    public function setActualAmount($actualAmount)
    {
        $this->container['actualAmount'] = $actualAmount;

        return $this;
    }

    /**
     * Gets billType
     *
     * @return string|null
     */
    public function getBillType()
    {
        return $this->container['billType'];
    }

    /**
     * Sets billType
     *
     * @param string|null $billType 收费类型
     *
     * @return self
     */
    public function setBillType($billType)
    {
        $this->container['billType'] = $billType;

        return $this;
    }

    /**
     * Gets gmtPay
     *
     * @return string|null
     */
    public function getGmtPay()
    {
        return $this->container['gmtPay'];
    }

    /**
     * Sets gmtPay
     *
     * @param string|null $gmtPay 收费时间,时间精确到秒
     *
     * @return self
     */
    public function setGmtPay($gmtPay)
    {
        $this->container['gmtPay'] = $gmtPay;

        return $this;
    }

    /**
     * Gets isRefund
     *
     * @return bool|null
     */
    public function getIsRefund()
    {
        return $this->container['isRefund'];
    }

    /**
     * Sets isRefund
     *
     * @param bool|null $isRefund 是否退费
     *
     * @return self
     */
    public function setIsRefund($isRefund)
    {
        $this->container['isRefund'] = $isRefund;

        return $this;
    }

    /**
     * Gets outBizNo
     *
     * @return string|null
     */
    public function getOutBizNo()
    {
        return $this->container['outBizNo'];
    }

    /**
     * Sets outBizNo
     *
     * @param string|null $outBizNo 外部请求号
     *
     * @return self
     */
    public function setOutBizNo($outBizNo)
    {
        $this->container['outBizNo'] = $outBizNo;

        return $this;
    }

    /**
     * Gets planAmount
     *
     * @return string|null
     */
    public function getPlanAmount()
    {
        return $this->container['planAmount'];
    }

    /**
     * Sets planAmount
     *
     * @param string|null $planAmount 应收费金额，单位元
     *
     * @return self
     */
    public function setPlanAmount($planAmount)
    {
        $this->container['planAmount'] = $planAmount;

        return $this;
    }

    /**
     * Gets productName
     *
     * @return string|null
     */
    public function getProductName()
    {
        return $this->container['productName'];
    }

    /**
     * Sets productName
     *
     * @param string|null $productName 收费产品
     *
     * @return self
     */
    public function setProductName($productName)
    {
        $this->container['productName'] = $productName;

        return $this;
    }

    /**
     * Gets serviceTarget
     *
     * @return string|null
     */
    public function getServiceTarget()
    {
        return $this->container['serviceTarget'];
    }

    /**
     * Sets serviceTarget
     *
     * @param string|null $serviceTarget 收费唯一id
     *
     * @return self
     */
    public function setServiceTarget($serviceTarget)
    {
        $this->container['serviceTarget'] = $serviceTarget;

        return $this;
    }

    /**
     * Gets serviceType
     *
     * @return string|null
     */
    public function getServiceType()
    {
        return $this->container['serviceType'];
    }

    /**
     * Sets serviceType
     *
     * @param string|null $serviceType 收费类型
     *
     * @return self
     */
    public function setServiceType($serviceType)
    {
        $this->container['serviceType'] = $serviceType;

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
     * @param string|null $status 状态
     *
     * @return self
     */
    public function setStatus($status)
    {
        $this->container['status'] = $status;

        return $this;
    }

    /**
     * Gets targetAccountNo
     *
     * @return string|null
     */
    public function getTargetAccountNo()
    {
        return $this->container['targetAccountNo'];
    }

    /**
     * Sets targetAccountNo
     *
     * @param string|null $targetAccountNo 收费目标账号
     *
     * @return self
     */
    public function setTargetAccountNo($targetAccountNo)
    {
        $this->container['targetAccountNo'] = $targetAccountNo;

        return $this;
    }

    /**
     * Gets targetUserId
     *
     * @return string|null
     */
    public function getTargetUserId()
    {
        return $this->container['targetUserId'];
    }

    /**
     * Sets targetUserId
     *
     * @param string|null $targetUserId 收费目标uid
     *
     * @return self
     */
    public function setTargetUserId($targetUserId)
    {
        $this->container['targetUserId'] = $targetUserId;

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


