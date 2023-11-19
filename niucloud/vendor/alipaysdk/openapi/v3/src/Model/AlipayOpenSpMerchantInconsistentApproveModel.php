<?php
/**
 * AlipayOpenSpMerchantInconsistentApproveModel
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
 * AlipayOpenSpMerchantInconsistentApproveModel Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class AlipayOpenSpMerchantInconsistentApproveModel implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AlipayOpenSpMerchantInconsistentApproveModel';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'backCard' => 'string',
        'commitmentLetter' => 'string',
        'frontCard' => 'string',
        'handheldBusinessLicense' => 'string',
        'handheldCard' => 'string',
        'itemCode' => 'string',
        'merchantPid' => 'string',
        'miniAppid' => 'string',
        'outBizNo' => 'string',
        'promotorPid' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'backCard' => null,
        'commitmentLetter' => null,
        'frontCard' => null,
        'handheldBusinessLicense' => null,
        'handheldCard' => null,
        'itemCode' => null,
        'merchantPid' => null,
        'miniAppid' => null,
        'outBizNo' => null,
        'promotorPid' => null
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
        'backCard' => 'back_card',
        'commitmentLetter' => 'commitment_letter',
        'frontCard' => 'front_card',
        'handheldBusinessLicense' => 'handheld_business_license',
        'handheldCard' => 'handheld_card',
        'itemCode' => 'item_code',
        'merchantPid' => 'merchant_pid',
        'miniAppid' => 'mini_appid',
        'outBizNo' => 'out_biz_no',
        'promotorPid' => 'promotor_pid'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'backCard' => 'setBackCard',
        'commitmentLetter' => 'setCommitmentLetter',
        'frontCard' => 'setFrontCard',
        'handheldBusinessLicense' => 'setHandheldBusinessLicense',
        'handheldCard' => 'setHandheldCard',
        'itemCode' => 'setItemCode',
        'merchantPid' => 'setMerchantPid',
        'miniAppid' => 'setMiniAppid',
        'outBizNo' => 'setOutBizNo',
        'promotorPid' => 'setPromotorPid'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'backCard' => 'getBackCard',
        'commitmentLetter' => 'getCommitmentLetter',
        'frontCard' => 'getFrontCard',
        'handheldBusinessLicense' => 'getHandheldBusinessLicense',
        'handheldCard' => 'getHandheldCard',
        'itemCode' => 'getItemCode',
        'merchantPid' => 'getMerchantPid',
        'miniAppid' => 'getMiniAppid',
        'outBizNo' => 'getOutBizNo',
        'promotorPid' => 'getPromotorPid'
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
        $this->container['backCard'] = $data['backCard'] ?? null;
        $this->container['commitmentLetter'] = $data['commitmentLetter'] ?? null;
        $this->container['frontCard'] = $data['frontCard'] ?? null;
        $this->container['handheldBusinessLicense'] = $data['handheldBusinessLicense'] ?? null;
        $this->container['handheldCard'] = $data['handheldCard'] ?? null;
        $this->container['itemCode'] = $data['itemCode'] ?? null;
        $this->container['merchantPid'] = $data['merchantPid'] ?? null;
        $this->container['miniAppid'] = $data['miniAppid'] ?? null;
        $this->container['outBizNo'] = $data['outBizNo'] ?? null;
        $this->container['promotorPid'] = $data['promotorPid'] ?? null;
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
     * Gets backCard
     *
     * @return string|null
     */
    public function getBackCard()
    {
        return $this->container['backCard'];
    }

    /**
     * Sets backCard
     *
     * @param string|null $backCard 身份证背面照，图片文件大小在 50K-5M 之间，不限制长宽，支持 png、bmp、gif、jpg、jpeg 格式。 请传入<a href=\"https://opendocs.alipay.com/apis/01ea4t\">alipay.open.sp.image.upload</a>(图片上传接口) 返回的 image_id。
     *
     * @return self
     */
    public function setBackCard($backCard)
    {
        $this->container['backCard'] = $backCard;

        return $this;
    }

    /**
     * Gets commitmentLetter
     *
     * @return string|null
     */
    public function getCommitmentLetter()
    {
        return $this->container['commitmentLetter'];
    }

    /**
     * Sets commitmentLetter
     *
     * @param string|null $commitmentLetter 实际经营人承诺函照片，要求证件文本信息清晰可见，图片文件大小在 50K-5M 之间，不限制长宽，支持 png、bmp、gif、jpg、jpeg 格式。 请传入<a href=\"https://opendocs.alipay.com/apis/01ea4t\">alipay.open.sp.image.upload</a>(图片上传接口) 返回的 image_id。 该资质是否必传请参见报名资质要求。
     *
     * @return self
     */
    public function setCommitmentLetter($commitmentLetter)
    {
        $this->container['commitmentLetter'] = $commitmentLetter;

        return $this;
    }

    /**
     * Gets frontCard
     *
     * @return string|null
     */
    public function getFrontCard()
    {
        return $this->container['frontCard'];
    }

    /**
     * Sets frontCard
     *
     * @param string|null $frontCard 身份证正面照，要求证件文本信息清晰可见，图片文件大小在 50K-5M 之间，不限制长宽，支持 png、bmp、gif、jpg、jpeg 格式. 请传<a href=\"https://opendocs.alipay.com/apis/01ea4t\">alipay.open.sp.image.upload</a>(图片上传接口) 返回的 image_id。
     *
     * @return self
     */
    public function setFrontCard($frontCard)
    {
        $this->container['frontCard'] = $frontCard;

        return $this;
    }

    /**
     * Gets handheldBusinessLicense
     *
     * @return string|null
     */
    public function getHandheldBusinessLicense()
    {
        return $this->container['handheldBusinessLicense'];
    }

    /**
     * Sets handheldBusinessLicense
     *
     * @param string|null $handheldBusinessLicense 手持营业执照合照，要求证件文本信息清晰可见，图片文件大小在 50K-5M 之间，不限制长宽，支持 png、bmp、gif、jpg、jpeg 格式。 请传入<a href=\"https://opendocs.alipay.com/apis/01ea4t\">alipay.open.sp.image.upload</a>(图片上传接口) 返回的 image_id。 该资质是否必传请参见报名资质要求。
     *
     * @return self
     */
    public function setHandheldBusinessLicense($handheldBusinessLicense)
    {
        $this->container['handheldBusinessLicense'] = $handheldBusinessLicense;

        return $this;
    }

    /**
     * Gets handheldCard
     *
     * @return string|null
     */
    public function getHandheldCard()
    {
        return $this->container['handheldCard'];
    }

    /**
     * Sets handheldCard
     *
     * @param string|null $handheldCard 手持身份证合照，要求证件文本信息清晰可见，图片文件大小在 50K-5M 之间，不限制长宽，支持 png、bmp、gif、jpg、jpeg 格式。 请传入<a href=\"https://opendocs.alipay.com/apis/01ea4t\">alipay.open.sp.image.upload</a>(图片上传接口) 返回的 image_id。 该资质是否必传请参见报名资质要求。
     *
     * @return self
     */
    public function setHandheldCard($handheldCard)
    {
        $this->container['handheldCard'] = $handheldCard;

        return $this;
    }

    /**
     * Gets itemCode
     *
     * @return string|null
     */
    public function getItemCode()
    {
        return $this->container['itemCode'];
    }

    /**
     * Sets itemCode
     *
     * @param string|null $itemCode 服务优选商品编码
     *
     * @return self
     */
    public function setItemCode($itemCode)
    {
        $this->container['itemCode'] = $itemCode;

        return $this;
    }

    /**
     * Gets merchantPid
     *
     * @return string|null
     */
    public function getMerchantPid()
    {
        return $this->container['merchantPid'];
    }

    /**
     * Sets merchantPid
     *
     * @param string|null $merchantPid 商家pid
     *
     * @return self
     */
    public function setMerchantPid($merchantPid)
    {
        $this->container['merchantPid'] = $merchantPid;

        return $this;
    }

    /**
     * Gets miniAppid
     *
     * @return string|null
     */
    public function getMiniAppid()
    {
        return $this->container['miniAppid'];
    }

    /**
     * Sets miniAppid
     *
     * @param string|null $miniAppid 商家小程序appId
     *
     * @return self
     */
    public function setMiniAppid($miniAppid)
    {
        $this->container['miniAppid'] = $miniAppid;

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
     * @param string|null $outBizNo 外部业务号，需不重复
     *
     * @return self
     */
    public function setOutBizNo($outBizNo)
    {
        $this->container['outBizNo'] = $outBizNo;

        return $this;
    }

    /**
     * Gets promotorPid
     *
     * @return string|null
     */
    public function getPromotorPid()
    {
        return $this->container['promotorPid'];
    }

    /**
     * Sets promotorPid
     *
     * @param string|null $promotorPid 推广服务商(S2)pid
     *
     * @return self
     */
    public function setPromotorPid($promotorPid)
    {
        $this->container['promotorPid'] = $promotorPid;

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


