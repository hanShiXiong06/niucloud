<?php
/**
 * AlipayMarketingActivityVoucherCreateModel
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
 * AlipayMarketingActivityVoucherCreateModel Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class AlipayMarketingActivityVoucherCreateModel implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AlipayMarketingActivityVoucherCreateModel';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'activityBaseInfo' => '\Alipay\OpenAPISDK\Model\ActivityBaseInfo',
        'activityName' => 'string',
        'belongMerchantInfo' => '\Alipay\OpenAPISDK\Model\PaymentVoucherBelongMerchantInfo',
        'merchantAccessMode' => 'string',
        'outBizNo' => 'string',
        'publishEndTime' => 'string',
        'publishStartTime' => 'string',
        'voucherAvailableScopeInfo' => '\Alipay\OpenAPISDK\Model\VoucherAvailableScopeInfo',
        'voucherBudgetInfo' => '\Alipay\OpenAPISDK\Model\PaymentVoucherBudgetInfo',
        'voucherBudgetSupplyInfo' => '\Alipay\OpenAPISDK\Model\VoucherBudgetSupplyInfo',
        'voucherCustomerGuideInfo' => '\Alipay\OpenAPISDK\Model\VoucherCustomerGuideInfo',
        'voucherDeductInfo' => '\Alipay\OpenAPISDK\Model\VoucherDeductInfo',
        'voucherDisplayInfo' => '\Alipay\OpenAPISDK\Model\PaymentVoucherDisplayInfo',
        'voucherDisplayPatternInfo' => '\Alipay\OpenAPISDK\Model\VoucherDisplayPatternInfo',
        'voucherSendModeInfo' => '\Alipay\OpenAPISDK\Model\VoucherSendModeInfo',
        'voucherSendRule' => '\Alipay\OpenAPISDK\Model\PaymentVoucherSendRule',
        'voucherType' => 'string',
        'voucherUseRule' => '\Alipay\OpenAPISDK\Model\PaymentVoucherUseRule',
        'voucherUseRuleInfo' => '\Alipay\OpenAPISDK\Model\VoucherUseRuleInfo'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'activityBaseInfo' => null,
        'activityName' => null,
        'belongMerchantInfo' => null,
        'merchantAccessMode' => null,
        'outBizNo' => null,
        'publishEndTime' => null,
        'publishStartTime' => null,
        'voucherAvailableScopeInfo' => null,
        'voucherBudgetInfo' => null,
        'voucherBudgetSupplyInfo' => null,
        'voucherCustomerGuideInfo' => null,
        'voucherDeductInfo' => null,
        'voucherDisplayInfo' => null,
        'voucherDisplayPatternInfo' => null,
        'voucherSendModeInfo' => null,
        'voucherSendRule' => null,
        'voucherType' => null,
        'voucherUseRule' => null,
        'voucherUseRuleInfo' => null
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
        'activityBaseInfo' => 'activity_base_info',
        'activityName' => 'activity_name',
        'belongMerchantInfo' => 'belong_merchant_info',
        'merchantAccessMode' => 'merchant_access_mode',
        'outBizNo' => 'out_biz_no',
        'publishEndTime' => 'publish_end_time',
        'publishStartTime' => 'publish_start_time',
        'voucherAvailableScopeInfo' => 'voucher_available_scope_info',
        'voucherBudgetInfo' => 'voucher_budget_info',
        'voucherBudgetSupplyInfo' => 'voucher_budget_supply_info',
        'voucherCustomerGuideInfo' => 'voucher_customer_guide_info',
        'voucherDeductInfo' => 'voucher_deduct_info',
        'voucherDisplayInfo' => 'voucher_display_info',
        'voucherDisplayPatternInfo' => 'voucher_display_pattern_info',
        'voucherSendModeInfo' => 'voucher_send_mode_info',
        'voucherSendRule' => 'voucher_send_rule',
        'voucherType' => 'voucher_type',
        'voucherUseRule' => 'voucher_use_rule',
        'voucherUseRuleInfo' => 'voucher_use_rule_info'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'activityBaseInfo' => 'setActivityBaseInfo',
        'activityName' => 'setActivityName',
        'belongMerchantInfo' => 'setBelongMerchantInfo',
        'merchantAccessMode' => 'setMerchantAccessMode',
        'outBizNo' => 'setOutBizNo',
        'publishEndTime' => 'setPublishEndTime',
        'publishStartTime' => 'setPublishStartTime',
        'voucherAvailableScopeInfo' => 'setVoucherAvailableScopeInfo',
        'voucherBudgetInfo' => 'setVoucherBudgetInfo',
        'voucherBudgetSupplyInfo' => 'setVoucherBudgetSupplyInfo',
        'voucherCustomerGuideInfo' => 'setVoucherCustomerGuideInfo',
        'voucherDeductInfo' => 'setVoucherDeductInfo',
        'voucherDisplayInfo' => 'setVoucherDisplayInfo',
        'voucherDisplayPatternInfo' => 'setVoucherDisplayPatternInfo',
        'voucherSendModeInfo' => 'setVoucherSendModeInfo',
        'voucherSendRule' => 'setVoucherSendRule',
        'voucherType' => 'setVoucherType',
        'voucherUseRule' => 'setVoucherUseRule',
        'voucherUseRuleInfo' => 'setVoucherUseRuleInfo'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'activityBaseInfo' => 'getActivityBaseInfo',
        'activityName' => 'getActivityName',
        'belongMerchantInfo' => 'getBelongMerchantInfo',
        'merchantAccessMode' => 'getMerchantAccessMode',
        'outBizNo' => 'getOutBizNo',
        'publishEndTime' => 'getPublishEndTime',
        'publishStartTime' => 'getPublishStartTime',
        'voucherAvailableScopeInfo' => 'getVoucherAvailableScopeInfo',
        'voucherBudgetInfo' => 'getVoucherBudgetInfo',
        'voucherBudgetSupplyInfo' => 'getVoucherBudgetSupplyInfo',
        'voucherCustomerGuideInfo' => 'getVoucherCustomerGuideInfo',
        'voucherDeductInfo' => 'getVoucherDeductInfo',
        'voucherDisplayInfo' => 'getVoucherDisplayInfo',
        'voucherDisplayPatternInfo' => 'getVoucherDisplayPatternInfo',
        'voucherSendModeInfo' => 'getVoucherSendModeInfo',
        'voucherSendRule' => 'getVoucherSendRule',
        'voucherType' => 'getVoucherType',
        'voucherUseRule' => 'getVoucherUseRule',
        'voucherUseRuleInfo' => 'getVoucherUseRuleInfo'
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
        $this->container['activityBaseInfo'] = $data['activityBaseInfo'] ?? null;
        $this->container['activityName'] = $data['activityName'] ?? null;
        $this->container['belongMerchantInfo'] = $data['belongMerchantInfo'] ?? null;
        $this->container['merchantAccessMode'] = $data['merchantAccessMode'] ?? null;
        $this->container['outBizNo'] = $data['outBizNo'] ?? null;
        $this->container['publishEndTime'] = $data['publishEndTime'] ?? null;
        $this->container['publishStartTime'] = $data['publishStartTime'] ?? null;
        $this->container['voucherAvailableScopeInfo'] = $data['voucherAvailableScopeInfo'] ?? null;
        $this->container['voucherBudgetInfo'] = $data['voucherBudgetInfo'] ?? null;
        $this->container['voucherBudgetSupplyInfo'] = $data['voucherBudgetSupplyInfo'] ?? null;
        $this->container['voucherCustomerGuideInfo'] = $data['voucherCustomerGuideInfo'] ?? null;
        $this->container['voucherDeductInfo'] = $data['voucherDeductInfo'] ?? null;
        $this->container['voucherDisplayInfo'] = $data['voucherDisplayInfo'] ?? null;
        $this->container['voucherDisplayPatternInfo'] = $data['voucherDisplayPatternInfo'] ?? null;
        $this->container['voucherSendModeInfo'] = $data['voucherSendModeInfo'] ?? null;
        $this->container['voucherSendRule'] = $data['voucherSendRule'] ?? null;
        $this->container['voucherType'] = $data['voucherType'] ?? null;
        $this->container['voucherUseRule'] = $data['voucherUseRule'] ?? null;
        $this->container['voucherUseRuleInfo'] = $data['voucherUseRuleInfo'] ?? null;
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
     * Gets activityBaseInfo
     *
     * @return \Alipay\OpenAPISDK\Model\ActivityBaseInfo|null
     */
    public function getActivityBaseInfo()
    {
        return $this->container['activityBaseInfo'];
    }

    /**
     * Sets activityBaseInfo
     *
     * @param \Alipay\OpenAPISDK\Model\ActivityBaseInfo|null $activityBaseInfo activityBaseInfo
     *
     * @return self
     */
    public function setActivityBaseInfo($activityBaseInfo)
    {
        $this->container['activityBaseInfo'] = $activityBaseInfo;

        return $this;
    }

    /**
     * Gets activityName
     *
     * @return string|null
     */
    public function getActivityName()
    {
        return $this->container['activityName'];
    }

    /**
     * Sets activityName
     *
     * @param string|null $activityName 活动名称。
     *
     * @return self
     */
    public function setActivityName($activityName)
    {
        $this->container['activityName'] = $activityName;

        return $this;
    }

    /**
     * Gets belongMerchantInfo
     *
     * @return \Alipay\OpenAPISDK\Model\PaymentVoucherBelongMerchantInfo|null
     */
    public function getBelongMerchantInfo()
    {
        return $this->container['belongMerchantInfo'];
    }

    /**
     * Sets belongMerchantInfo
     *
     * @param \Alipay\OpenAPISDK\Model\PaymentVoucherBelongMerchantInfo|null $belongMerchantInfo belongMerchantInfo
     *
     * @return self
     */
    public function setBelongMerchantInfo($belongMerchantInfo)
    {
        $this->container['belongMerchantInfo'] = $belongMerchantInfo;

        return $this;
    }

    /**
     * Gets merchantAccessMode
     *
     * @return string|null
     */
    public function getMerchantAccessMode()
    {
        return $this->container['merchantAccessMode'];
    }

    /**
     * Sets merchantAccessMode
     *
     * @param string|null $merchantAccessMode 商户接入模式
     *
     * @return self
     */
    public function setMerchantAccessMode($merchantAccessMode)
    {
        $this->container['merchantAccessMode'] = $merchantAccessMode;

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
     * @param string|null $outBizNo 用作幂等控制。  幂等作用： 参数不变的情况下，再次请求返回与上一次相同的结果。  外部接入方需保证业务单号唯一。
     *
     * @return self
     */
    public function setOutBizNo($outBizNo)
    {
        $this->container['outBizNo'] = $outBizNo;

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
     * @param string|null $publishEndTime 券发放结束时间。 格式为：yyyy-MM-dd HH:mm:ss 限制： 券发放结束时间 publish_end_time 与 券发放开始时间  publish_start_time 间隔必须小于等于180天
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
     * Gets voucherAvailableScopeInfo
     *
     * @return \Alipay\OpenAPISDK\Model\VoucherAvailableScopeInfo|null
     */
    public function getVoucherAvailableScopeInfo()
    {
        return $this->container['voucherAvailableScopeInfo'];
    }

    /**
     * Sets voucherAvailableScopeInfo
     *
     * @param \Alipay\OpenAPISDK\Model\VoucherAvailableScopeInfo|null $voucherAvailableScopeInfo voucherAvailableScopeInfo
     *
     * @return self
     */
    public function setVoucherAvailableScopeInfo($voucherAvailableScopeInfo)
    {
        $this->container['voucherAvailableScopeInfo'] = $voucherAvailableScopeInfo;

        return $this;
    }

    /**
     * Gets voucherBudgetInfo
     *
     * @return \Alipay\OpenAPISDK\Model\PaymentVoucherBudgetInfo|null
     */
    public function getVoucherBudgetInfo()
    {
        return $this->container['voucherBudgetInfo'];
    }

    /**
     * Sets voucherBudgetInfo
     *
     * @param \Alipay\OpenAPISDK\Model\PaymentVoucherBudgetInfo|null $voucherBudgetInfo voucherBudgetInfo
     *
     * @return self
     */
    public function setVoucherBudgetInfo($voucherBudgetInfo)
    {
        $this->container['voucherBudgetInfo'] = $voucherBudgetInfo;

        return $this;
    }

    /**
     * Gets voucherBudgetSupplyInfo
     *
     * @return \Alipay\OpenAPISDK\Model\VoucherBudgetSupplyInfo|null
     */
    public function getVoucherBudgetSupplyInfo()
    {
        return $this->container['voucherBudgetSupplyInfo'];
    }

    /**
     * Sets voucherBudgetSupplyInfo
     *
     * @param \Alipay\OpenAPISDK\Model\VoucherBudgetSupplyInfo|null $voucherBudgetSupplyInfo voucherBudgetSupplyInfo
     *
     * @return self
     */
    public function setVoucherBudgetSupplyInfo($voucherBudgetSupplyInfo)
    {
        $this->container['voucherBudgetSupplyInfo'] = $voucherBudgetSupplyInfo;

        return $this;
    }

    /**
     * Gets voucherCustomerGuideInfo
     *
     * @return \Alipay\OpenAPISDK\Model\VoucherCustomerGuideInfo|null
     */
    public function getVoucherCustomerGuideInfo()
    {
        return $this->container['voucherCustomerGuideInfo'];
    }

    /**
     * Sets voucherCustomerGuideInfo
     *
     * @param \Alipay\OpenAPISDK\Model\VoucherCustomerGuideInfo|null $voucherCustomerGuideInfo voucherCustomerGuideInfo
     *
     * @return self
     */
    public function setVoucherCustomerGuideInfo($voucherCustomerGuideInfo)
    {
        $this->container['voucherCustomerGuideInfo'] = $voucherCustomerGuideInfo;

        return $this;
    }

    /**
     * Gets voucherDeductInfo
     *
     * @return \Alipay\OpenAPISDK\Model\VoucherDeductInfo|null
     */
    public function getVoucherDeductInfo()
    {
        return $this->container['voucherDeductInfo'];
    }

    /**
     * Sets voucherDeductInfo
     *
     * @param \Alipay\OpenAPISDK\Model\VoucherDeductInfo|null $voucherDeductInfo voucherDeductInfo
     *
     * @return self
     */
    public function setVoucherDeductInfo($voucherDeductInfo)
    {
        $this->container['voucherDeductInfo'] = $voucherDeductInfo;

        return $this;
    }

    /**
     * Gets voucherDisplayInfo
     *
     * @return \Alipay\OpenAPISDK\Model\PaymentVoucherDisplayInfo|null
     */
    public function getVoucherDisplayInfo()
    {
        return $this->container['voucherDisplayInfo'];
    }

    /**
     * Sets voucherDisplayInfo
     *
     * @param \Alipay\OpenAPISDK\Model\PaymentVoucherDisplayInfo|null $voucherDisplayInfo voucherDisplayInfo
     *
     * @return self
     */
    public function setVoucherDisplayInfo($voucherDisplayInfo)
    {
        $this->container['voucherDisplayInfo'] = $voucherDisplayInfo;

        return $this;
    }

    /**
     * Gets voucherDisplayPatternInfo
     *
     * @return \Alipay\OpenAPISDK\Model\VoucherDisplayPatternInfo|null
     */
    public function getVoucherDisplayPatternInfo()
    {
        return $this->container['voucherDisplayPatternInfo'];
    }

    /**
     * Sets voucherDisplayPatternInfo
     *
     * @param \Alipay\OpenAPISDK\Model\VoucherDisplayPatternInfo|null $voucherDisplayPatternInfo voucherDisplayPatternInfo
     *
     * @return self
     */
    public function setVoucherDisplayPatternInfo($voucherDisplayPatternInfo)
    {
        $this->container['voucherDisplayPatternInfo'] = $voucherDisplayPatternInfo;

        return $this;
    }

    /**
     * Gets voucherSendModeInfo
     *
     * @return \Alipay\OpenAPISDK\Model\VoucherSendModeInfo|null
     */
    public function getVoucherSendModeInfo()
    {
        return $this->container['voucherSendModeInfo'];
    }

    /**
     * Sets voucherSendModeInfo
     *
     * @param \Alipay\OpenAPISDK\Model\VoucherSendModeInfo|null $voucherSendModeInfo voucherSendModeInfo
     *
     * @return self
     */
    public function setVoucherSendModeInfo($voucherSendModeInfo)
    {
        $this->container['voucherSendModeInfo'] = $voucherSendModeInfo;

        return $this;
    }

    /**
     * Gets voucherSendRule
     *
     * @return \Alipay\OpenAPISDK\Model\PaymentVoucherSendRule|null
     */
    public function getVoucherSendRule()
    {
        return $this->container['voucherSendRule'];
    }

    /**
     * Sets voucherSendRule
     *
     * @param \Alipay\OpenAPISDK\Model\PaymentVoucherSendRule|null $voucherSendRule voucherSendRule
     *
     * @return self
     */
    public function setVoucherSendRule($voucherSendRule)
    {
        $this->container['voucherSendRule'] = $voucherSendRule;

        return $this;
    }

    /**
     * Gets voucherType
     *
     * @return string|null
     */
    public function getVoucherType()
    {
        return $this->container['voucherType'];
    }

    /**
     * Sets voucherType
     *
     * @param string|null $voucherType 券类型
     *
     * @return self
     */
    public function setVoucherType($voucherType)
    {
        $this->container['voucherType'] = $voucherType;

        return $this;
    }

    /**
     * Gets voucherUseRule
     *
     * @return \Alipay\OpenAPISDK\Model\PaymentVoucherUseRule|null
     */
    public function getVoucherUseRule()
    {
        return $this->container['voucherUseRule'];
    }

    /**
     * Sets voucherUseRule
     *
     * @param \Alipay\OpenAPISDK\Model\PaymentVoucherUseRule|null $voucherUseRule voucherUseRule
     *
     * @return self
     */
    public function setVoucherUseRule($voucherUseRule)
    {
        $this->container['voucherUseRule'] = $voucherUseRule;

        return $this;
    }

    /**
     * Gets voucherUseRuleInfo
     *
     * @return \Alipay\OpenAPISDK\Model\VoucherUseRuleInfo|null
     */
    public function getVoucherUseRuleInfo()
    {
        return $this->container['voucherUseRuleInfo'];
    }

    /**
     * Sets voucherUseRuleInfo
     *
     * @param \Alipay\OpenAPISDK\Model\VoucherUseRuleInfo|null $voucherUseRuleInfo voucherUseRuleInfo
     *
     * @return self
     */
    public function setVoucherUseRuleInfo($voucherUseRuleInfo)
    {
        $this->container['voucherUseRuleInfo'] = $voucherUseRuleInfo;

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


