<?php
/**
 * TradeInfoDTO
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
 * TradeInfoDTO Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class TradeInfoDTO implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'TradeInfoDTO';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'buyerId' => 'string',
        'createTime' => 'string',
        'platformOrderId' => 'string',
        'totalAmount' => 'string',
        'tradeAmount' => 'string',
        'tradeFundBillList' => '\Alipay\OpenAPISDK\Model\TradeFundBillDetail[]',
        'tradeNo' => 'string',
        'tradeStatus' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'buyerId' => null,
        'createTime' => null,
        'platformOrderId' => null,
        'totalAmount' => null,
        'tradeAmount' => null,
        'tradeFundBillList' => null,
        'tradeNo' => null,
        'tradeStatus' => null
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
        'buyerId' => 'buyer_id',
        'createTime' => 'create_time',
        'platformOrderId' => 'platform_order_id',
        'totalAmount' => 'total_amount',
        'tradeAmount' => 'trade_amount',
        'tradeFundBillList' => 'trade_fund_bill_list',
        'tradeNo' => 'trade_no',
        'tradeStatus' => 'trade_status'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'buyerId' => 'setBuyerId',
        'createTime' => 'setCreateTime',
        'platformOrderId' => 'setPlatformOrderId',
        'totalAmount' => 'setTotalAmount',
        'tradeAmount' => 'setTradeAmount',
        'tradeFundBillList' => 'setTradeFundBillList',
        'tradeNo' => 'setTradeNo',
        'tradeStatus' => 'setTradeStatus'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'buyerId' => 'getBuyerId',
        'createTime' => 'getCreateTime',
        'platformOrderId' => 'getPlatformOrderId',
        'totalAmount' => 'getTotalAmount',
        'tradeAmount' => 'getTradeAmount',
        'tradeFundBillList' => 'getTradeFundBillList',
        'tradeNo' => 'getTradeNo',
        'tradeStatus' => 'getTradeStatus'
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
        $this->container['buyerId'] = $data['buyerId'] ?? null;
        $this->container['createTime'] = $data['createTime'] ?? null;
        $this->container['platformOrderId'] = $data['platformOrderId'] ?? null;
        $this->container['totalAmount'] = $data['totalAmount'] ?? null;
        $this->container['tradeAmount'] = $data['tradeAmount'] ?? null;
        $this->container['tradeFundBillList'] = $data['tradeFundBillList'] ?? null;
        $this->container['tradeNo'] = $data['tradeNo'] ?? null;
        $this->container['tradeStatus'] = $data['tradeStatus'] ?? null;
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
     * Gets buyerId
     *
     * @return string|null
     */
    public function getBuyerId()
    {
        return $this->container['buyerId'];
    }

    /**
     * Sets buyerId
     *
     * @param string|null $buyerId 买家ID
     *
     * @return self
     */
    public function setBuyerId($buyerId)
    {
        $this->container['buyerId'] = $buyerId;

        return $this;
    }

    /**
     * Gets createTime
     *
     * @return string|null
     */
    public function getCreateTime()
    {
        return $this->container['createTime'];
    }

    /**
     * Sets createTime
     *
     * @param string|null $createTime 交易创建时间
     *
     * @return self
     */
    public function setCreateTime($createTime)
    {
        $this->container['createTime'] = $createTime;

        return $this;
    }

    /**
     * Gets platformOrderId
     *
     * @return string|null
     */
    public function getPlatformOrderId()
    {
        return $this->container['platformOrderId'];
    }

    /**
     * Sets platformOrderId
     *
     * @param string|null $platformOrderId 外部平台订单号
     *
     * @return self
     */
    public function setPlatformOrderId($platformOrderId)
    {
        $this->container['platformOrderId'] = $platformOrderId;

        return $this;
    }

    /**
     * Gets totalAmount
     *
     * @return string|null
     */
    public function getTotalAmount()
    {
        return $this->container['totalAmount'];
    }

    /**
     * Sets totalAmount
     *
     * @param string|null $totalAmount 订单总金额
     *
     * @return self
     */
    public function setTotalAmount($totalAmount)
    {
        $this->container['totalAmount'] = $totalAmount;

        return $this;
    }

    /**
     * Gets tradeAmount
     *
     * @return string|null
     */
    public function getTradeAmount()
    {
        return $this->container['tradeAmount'];
    }

    /**
     * Sets tradeAmount
     *
     * @param string|null $tradeAmount 订单总金额
     *
     * @return self
     */
    public function setTradeAmount($tradeAmount)
    {
        $this->container['tradeAmount'] = $tradeAmount;

        return $this;
    }

    /**
     * Gets tradeFundBillList
     *
     * @return \Alipay\OpenAPISDK\Model\TradeFundBillDetail[]|null
     */
    public function getTradeFundBillList()
    {
        return $this->container['tradeFundBillList'];
    }

    /**
     * Sets tradeFundBillList
     *
     * @param \Alipay\OpenAPISDK\Model\TradeFundBillDetail[]|null $tradeFundBillList 资金单明细
     *
     * @return self
     */
    public function setTradeFundBillList($tradeFundBillList)
    {
        $this->container['tradeFundBillList'] = $tradeFundBillList;

        return $this;
    }

    /**
     * Gets tradeNo
     *
     * @return string|null
     */
    public function getTradeNo()
    {
        return $this->container['tradeNo'];
    }

    /**
     * Sets tradeNo
     *
     * @param string|null $tradeNo 交易单号
     *
     * @return self
     */
    public function setTradeNo($tradeNo)
    {
        $this->container['tradeNo'] = $tradeNo;

        return $this;
    }

    /**
     * Gets tradeStatus
     *
     * @return string|null
     */
    public function getTradeStatus()
    {
        return $this->container['tradeStatus'];
    }

    /**
     * Sets tradeStatus
     *
     * @param string|null $tradeStatus 交易状态：WAIT_BUYER_PAY（交易创建，等待买家付款）、TRADE_CLOSED（未付款交易超时关闭，或支付完成后全额退款）、TRADE_SUCCESS（交易支付成功）、TRADE_FINISHED（交易结束，不可退款）
     *
     * @return self
     */
    public function setTradeStatus($tradeStatus)
    {
        $this->container['tradeStatus'] = $tradeStatus;

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


