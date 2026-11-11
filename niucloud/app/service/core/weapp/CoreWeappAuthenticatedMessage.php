<?php
declare(strict_types=1);
namespace app\service\core\weapp;

use EasyWeChat\Kernel\Encryptor;
use EasyWeChat\Kernel\Message;
use core\exception\CommonException;

/** An authentication result, not a request-supplied verified flag. Instances
 * can only be produced by successful native SDK decryption and target binding. */
final class CoreWeappAuthenticatedMessage
{
    private function __construct(private int $site,private string $appId,private array $message,private string $plaintext){}

    /** @return array{plaintext:string,context:?self} Unbound messages retain
     * the legacy SDK flow but are never authenticated for a sensitive hook. */
    public static function decrypt(Encryptor $native,int $site,string $appId,string $original,string $source,string $ciphertext,string $signature,string $nonce,int|string $timestamp): array
    {
        if($site<=0||!preg_match('/^wx[A-Za-z0-9]{16}$/D',$appId)||!in_array($source,['self_hosted','open_platform'],true))throw new CommonException('小程序消息认证范围无效');
        if($ciphertext===''||strlen($ciphertext)>1048576||!preg_match('/^[a-fA-F0-9]{40}$/D',$signature)||$nonce===''||strlen($nonce)>256||!preg_match('/^[0-9]{1,12}$/D',(string)$timestamp))throw new CommonException('小程序加密消息认证参数无效');
        $bytes=base64_decode($ciphertext,true);if(!is_string($bytes)||strlen($bytes)<32||strlen($bytes)%32!==0)throw new CommonException('小程序消息密文格式无效');
        // The actual configured SDK encryptor verifies signature and receiveId.
        // Never copy AES keys or implement a second decryption algorithm here.
        $plaintext=$native->decrypt($ciphertext,$signature,$nonce,$timestamp);
        if($plaintext===''||strlen($plaintext)>1048576)throw new CommonException('小程序消息内容大小无效');
        $unambiguous=self::unambiguousXml($plaintext);
        $message=Message::format($plaintext);$bound=false;$matches=$unambiguous;
        foreach(['AppId','AppID','appid','AuthorizerAppid','AuthorizerAppId']as$field){
            if(!array_key_exists($field,$message))continue;
            if(!is_string($message[$field])||!hash_equals($appId,$message[$field]))$matches=false;
            $bound=true;
        }
        if($original!==''&&array_key_exists('ToUserName',$message)){
            if(!is_string($message['ToUserName'])||!hash_equals($original,$message['ToUserName']))$matches=false;
            $bound=true;
        }
        // Authorized mini apps share their component's encryptor. Its frame
        // proves the component, NOT which tenant's authorizer owns the message.
        $authenticated=$matches&&($source!=='open_platform'||$bound);
        return ['plaintext'=>$plaintext,'context'=>$authenticated?new self($site,$appId,$message,$plaintext):null];
    }

    public function belongsTo(int $site): bool{return$site>0&&$site===$this->site;}
    public function appId(): string{return$this->appId;}
    public function message(): array{return$this->message;}
    public function plaintext(): string{return$this->plaintext;}

    /** Keep native SDK value decoding. This optional financial-message
     * certification only rejects ambiguous structure, not normal consumers. */
    private static function unambiguousXml(string $plaintext): bool
    {
        if(preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i',$plaintext))throw new CommonException('小程序消息不允许包含外部实体');
        if(!str_starts_with(ltrim($plaintext),'<'))return false;
        $previous=libxml_use_internal_errors(true);
        try{
            $document=new \DOMDocument();
            if(!$document->loadXML($plaintext,LIBXML_NONET|LIBXML_NOBLANKS|LIBXML_NOCDATA)||$document->doctype!==null)throw new CommonException('小程序消息XML格式无效');
            if(!$document->documentElement||$document->documentElement->tagName!=='xml')return false;
            $validate=static function(\DOMElement $element,int $depth)use(&$validate):bool{
                if($depth>32||$element->attributes->length>0)return false;
                $seen=[];$text='';
                foreach($element->childNodes as $node){
                    if($node instanceof \DOMElement){if(isset($seen[$node->tagName])||!$validate($node,$depth+1))return false;$seen[$node->tagName]=true;}
                    elseif($node instanceof \DOMText||$node instanceof \DOMCdataSection)$text.=$node->textContent;
                    else return false;
                }
                return !$seen||trim($text)==='';
            };
            return$validate($document->documentElement,0);
        }finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
    }
}
