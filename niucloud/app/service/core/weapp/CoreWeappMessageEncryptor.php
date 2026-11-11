<?php
declare(strict_types=1);
namespace app\service\core\weapp;

use EasyWeChat\Kernel\Encryptor;

/** Optional native SDK decorator. One native decrypt produces both the normal
 * SDK message and a separately captured, authenticated INNER message. */
final class CoreWeappMessageEncryptor extends Encryptor
{
    private ?CoreWeappAuthenticatedMessage $authenticated=null;
    public function __construct(private Encryptor $native,private int $site,private string $targetAppId,private string $originalId,private string $source){}
    public function getToken(): string{return$this->native->getToken();}
    public function createSignature(mixed ...$attributes): string{return$this->native->createSignature(...$attributes);}
    public function encrypt(string $plaintext,string|null $nonce=null,int|string $timestamp=null): string{return$this->native->encrypt($plaintext,$nonce,$timestamp);}
    public function decrypt(string $ciphertext,string $msgSignature,string $nonce,int|string $timestamp): string
    {
        $this->authenticated=null;
        $verified=CoreWeappAuthenticatedMessage::decrypt($this->native,$this->site,$this->targetAppId,$this->originalId,$this->source,$ciphertext,$msgSignature,$nonce,$timestamp);
        $this->authenticated=$verified['context'];return$verified['plaintext'];
    }
    public function authenticatedMessage(): ?CoreWeappAuthenticatedMessage{return$this->authenticated;}
}
