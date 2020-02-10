<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 23/04/2018
 * Time: 10:41
 */

class TextingCryptor
{
    private $key = '6541qaed651asd49wrg654vh6gj964t984fd13asx6a7d97gf65h43cv216d88sc4sa2c1fd8g8';

    private $cipher_algo;
    private $hash_algo;
    private $iv_num_bytes;
    private $format;

    const FORMAT_RAW = 0;
    const FORMAT_B64 = 1;
    const FORMAT_HEX = 2;

    /**
     * Construct a Cryptor, using aes256 encryption, sha256 key hashing and base64 encoding.
     * @param string $cipher_algo The cipher algorithm.
     * @param string $hash_algo   Key hashing algorithm.
     * @param int $fmt         Format of the encrypted data.
     * @throws Exception
     */
    public function __construct($cipher_algo = 'aes-256-ctr', $hash_algo = 'sha256', $fmt = TextingCryptor::FORMAT_B64)
    {
        $this->cipher_algo = $cipher_algo;
        $this->hash_algo = $hash_algo;
        $this->format = $fmt;

        if (!in_array($cipher_algo, openssl_get_cipher_methods(true)))
            throw new Exception("TextingCryptor:: - unknown cipher algo {$cipher_algo}");

        if (!in_array($hash_algo, openssl_get_md_methods(true)))
            throw new Exception("TextingCryptor:: - unknown hash algo {$hash_algo}");

        $this->iv_num_bytes = openssl_cipher_iv_length($cipher_algo);
    }

    /**
     * Encrypt a string.
     * @param  string $in  String to encrypt.
     * @param  string $key Encryption key.
     * @param  int $fmt Optional override for the output encoding. One of FORMAT_RAW, FORMAT_B64 or FORMAT_HEX.
     * @return string      The encrypted string.
     * @throws Exception
     */
    public function encryptString($in, $key = null, $fmt = null)
    {
        if ($key === null)
            $key = $this->key;

        if ($fmt === null)
            $fmt = $this->format;

        // Build an initialisation vector
        $iv = mcrypt_create_iv($this->iv_num_bytes, MCRYPT_DEV_URANDOM);

        // Hash the key
        $keyhash = openssl_digest($key, $this->hash_algo, true);

        // and encrypt
        $opts =  OPENSSL_RAW_DATA;
        $encrypted = openssl_encrypt($in, $this->cipher_algo, $keyhash, $opts, $iv);

        if ($encrypted === false)
            throw new Exception('Cryptor::encryptString() - Encryption failed: ' . openssl_error_string());

        // The result comprises the IV and encrypted data
        $res = $iv . $encrypted;
        // and format the result if required.
        if ($fmt == TextingCryptor::FORMAT_B64)
            $res = base64_encode($res);

        else if ($fmt == TextingCryptor::FORMAT_HEX)
            $res = unpack('H*', $res)[1];

        return $res;
    }
    /**
     * Decrypt a string.
     * @param  string $in  String to decrypt.
     * @param  string $key Decryption key.
     * @param  int $fmt Optional override for the input encoding. One of FORMAT_RAW, FORMAT_B64 or FORMAT_HEX.
     * @return string      The decrypted string.
     * @throws Exception
     */
    public function decryptString($in, $key = null, $fmt = null)
    {
        if ($key === null)
            $key = $this->key;

        if ($fmt === null)
            $fmt = $this->format;

        $raw = $in;

        // Restore the encrypted data if encoded
        if ($fmt == TextingCryptor::FORMAT_B64)
            $raw = base64_decode($in);

        else if ($fmt == TextingCryptor::FORMAT_HEX)
            $raw = pack('H*', $in);

        // and do an integrity check on the size.
        if (strlen($raw) < $this->iv_num_bytes)
            throw new Exception('Cryptor::decryptString() - ' . 'data length ' . strlen($raw) . " is less than iv length {$this->iv_num_bytes}");

        // Extract the initialisation vector and encrypted data
        $iv = substr($raw, 0, $this->iv_num_bytes);
        $raw = substr($raw, $this->iv_num_bytes);

        // Hash the key
        $keyhash = openssl_digest($key, $this->hash_algo, true);

        // and decrypt.
        $opts = OPENSSL_RAW_DATA;
        $res = openssl_decrypt($raw, $this->cipher_algo, $keyhash, $opts, $iv);

        if ($res === false)
            throw new Exception('Cryptor::decryptString - decryption failed: ' . openssl_error_string());

        return $res;
    }
    /**
     * Static convenience method for encrypting.
     * @param  string $in  String to encrypt.
     * @param  string $key Encryption key.
     * @param  int $fmt Optional override for the output encoding. One of FORMAT_RAW, FORMAT_B64 or FORMAT_HEX.
     * @return string      The encrypted string.
     * @throws Exception
     */
    public static function Encrypt($in, $key = null, $fmt = null)
    {
        $c = new TextingCryptor();
        return $c->encryptString($in, $key, $fmt);
    }
    /**
     * Static convenience method for decrypting.
     * @param  string $in  String to decrypt.
     * @param  string $key Decryption key.
     * @param  int $fmt Optional override for the input encoding. One of FORMAT_RAW, FORMAT_B64 or FORMAT_HEX.
     * @return string      The decrypted string.
     * @throws Exception
     */
    public static function Decrypt($in, $key = null, $fmt = null)
    {
        $c = new TextingCryptor();
        return $c->decryptString($in, $key, $fmt);
    }
}