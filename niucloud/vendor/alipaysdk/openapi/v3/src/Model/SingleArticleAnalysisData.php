<?php
/**
 * SingleArticleAnalysisData
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
 * SingleArticleAnalysisData Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class SingleArticleAnalysisData implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'SingleArticleAnalysisData';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'avgReadTime' => 'string',
        'date' => 'string',
        'deliverUserCnt' => 'int',
        'exposeUserCnt' => 'int',
        'praiseUserCnt' => 'int',
        'readUserCnt' => 'int',
        'replyUserCnt' => 'int',
        'shareUserCnt' => 'int',
        'title' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'avgReadTime' => null,
        'date' => null,
        'deliverUserCnt' => null,
        'exposeUserCnt' => null,
        'praiseUserCnt' => null,
        'readUserCnt' => null,
        'replyUserCnt' => null,
        'shareUserCnt' => null,
        'title' => null
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
        'avgReadTime' => 'avg_read_time',
        'date' => 'date',
        'deliverUserCnt' => 'deliver_user_cnt',
        'exposeUserCnt' => 'expose_user_cnt',
        'praiseUserCnt' => 'praise_user_cnt',
        'readUserCnt' => 'read_user_cnt',
        'replyUserCnt' => 'reply_user_cnt',
        'shareUserCnt' => 'share_user_cnt',
        'title' => 'title'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'avgReadTime' => 'setAvgReadTime',
        'date' => 'setDate',
        'deliverUserCnt' => 'setDeliverUserCnt',
        'exposeUserCnt' => 'setExposeUserCnt',
        'praiseUserCnt' => 'setPraiseUserCnt',
        'readUserCnt' => 'setReadUserCnt',
        'replyUserCnt' => 'setReplyUserCnt',
        'shareUserCnt' => 'setShareUserCnt',
        'title' => 'setTitle'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'avgReadTime' => 'getAvgReadTime',
        'date' => 'getDate',
        'deliverUserCnt' => 'getDeliverUserCnt',
        'exposeUserCnt' => 'getExposeUserCnt',
        'praiseUserCnt' => 'getPraiseUserCnt',
        'readUserCnt' => 'getReadUserCnt',
        'replyUserCnt' => 'getReplyUserCnt',
        'shareUserCnt' => 'getShareUserCnt',
        'title' => 'getTitle'
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
        $this->container['avgReadTime'] = $data['avgReadTime'] ?? null;
        $this->container['date'] = $data['date'] ?? null;
        $this->container['deliverUserCnt'] = $data['deliverUserCnt'] ?? null;
        $this->container['exposeUserCnt'] = $data['exposeUserCnt'] ?? null;
        $this->container['praiseUserCnt'] = $data['praiseUserCnt'] ?? null;
        $this->container['readUserCnt'] = $data['readUserCnt'] ?? null;
        $this->container['replyUserCnt'] = $data['replyUserCnt'] ?? null;
        $this->container['shareUserCnt'] = $data['shareUserCnt'] ?? null;
        $this->container['title'] = $data['title'] ?? null;
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
     * Gets avgReadTime
     *
     * @return string|null
     */
    public function getAvgReadTime()
    {
        return $this->container['avgReadTime'];
    }

    /**
     * Sets avgReadTime
     *
     * @param string|null $avgReadTime 人均阅读时长
     *
     * @return self
     */
    public function setAvgReadTime($avgReadTime)
    {
        $this->container['avgReadTime'] = $avgReadTime;

        return $this;
    }

    /**
     * Gets date
     *
     * @return string|null
     */
    public function getDate()
    {
        return $this->container['date'];
    }

    /**
     * Sets date
     *
     * @param string|null $date 文章发布日期
     *
     * @return self
     */
    public function setDate($date)
    {
        $this->container['date'] = $date;

        return $this;
    }

    /**
     * Gets deliverUserCnt
     *
     * @return int|null
     */
    public function getDeliverUserCnt()
    {
        return $this->container['deliverUserCnt'];
    }

    /**
     * Sets deliverUserCnt
     *
     * @param int|null $deliverUserCnt 送达人数
     *
     * @return self
     */
    public function setDeliverUserCnt($deliverUserCnt)
    {
        $this->container['deliverUserCnt'] = $deliverUserCnt;

        return $this;
    }

    /**
     * Gets exposeUserCnt
     *
     * @return int|null
     */
    public function getExposeUserCnt()
    {
        return $this->container['exposeUserCnt'];
    }

    /**
     * Sets exposeUserCnt
     *
     * @param int|null $exposeUserCnt 曝光人数
     *
     * @return self
     */
    public function setExposeUserCnt($exposeUserCnt)
    {
        $this->container['exposeUserCnt'] = $exposeUserCnt;

        return $this;
    }

    /**
     * Gets praiseUserCnt
     *
     * @return int|null
     */
    public function getPraiseUserCnt()
    {
        return $this->container['praiseUserCnt'];
    }

    /**
     * Sets praiseUserCnt
     *
     * @param int|null $praiseUserCnt 点赞数
     *
     * @return self
     */
    public function setPraiseUserCnt($praiseUserCnt)
    {
        $this->container['praiseUserCnt'] = $praiseUserCnt;

        return $this;
    }

    /**
     * Gets readUserCnt
     *
     * @return int|null
     */
    public function getReadUserCnt()
    {
        return $this->container['readUserCnt'];
    }

    /**
     * Sets readUserCnt
     *
     * @param int|null $readUserCnt 阅读人数
     *
     * @return self
     */
    public function setReadUserCnt($readUserCnt)
    {
        $this->container['readUserCnt'] = $readUserCnt;

        return $this;
    }

    /**
     * Gets replyUserCnt
     *
     * @return int|null
     */
    public function getReplyUserCnt()
    {
        return $this->container['replyUserCnt'];
    }

    /**
     * Sets replyUserCnt
     *
     * @param int|null $replyUserCnt 评论数
     *
     * @return self
     */
    public function setReplyUserCnt($replyUserCnt)
    {
        $this->container['replyUserCnt'] = $replyUserCnt;

        return $this;
    }

    /**
     * Gets shareUserCnt
     *
     * @return int|null
     */
    public function getShareUserCnt()
    {
        return $this->container['shareUserCnt'];
    }

    /**
     * Sets shareUserCnt
     *
     * @param int|null $shareUserCnt 分享人数
     *
     * @return self
     */
    public function setShareUserCnt($shareUserCnt)
    {
        $this->container['shareUserCnt'] = $shareUserCnt;

        return $this;
    }

    /**
     * Gets title
     *
     * @return string|null
     */
    public function getTitle()
    {
        return $this->container['title'];
    }

    /**
     * Sets title
     *
     * @param string|null $title 文章标题
     *
     * @return self
     */
    public function setTitle($title)
    {
        $this->container['title'] = $title;

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


