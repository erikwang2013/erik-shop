/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * 接口 AES 加解密：与后端 app/common/Encryption.php 协议一致
 * 协议：AES-256-CBC/PKCS7 + 随机 16 字节 IV，密文 = base64(iv + ciphertext)
 */
import cryptoFramework from '@ohos.security.cryptoFramework';
import util from '@ohos.util';

// 与后端 ENCRYPTION_KEY 一致的 32 字节密钥；留空则接口走明文（加密开关关闭）
const ENCRYPTION_KEY = '';
const IV_LENGTH = 16;

export const CRYPTO_ENABLED = ENCRYPTION_KEY.length > 0;

export class SecureCrypto {
  private static encoder = util.TextEncoder.create();
  private static decoder = util.TextDecoder.create('utf-8');
  private static b64 = new util.Base64Helper();

  static async encrypt(jsonStr: string): Promise<string> {
    const key = await SecureCrypto.toSymKey();
    const rand = cryptoFramework.createRandom();
    const iv = (await rand.generateRandom(IV_LENGTH)).data;
    const cipher = cryptoFramework.createCipher('AES256|CBC|PKCS7');
    await cipher.init(cryptoFramework.CryptoMode.ENCRYPT_MODE, key, { data: iv });
    const out1 = await cipher.update({ data: SecureCrypto.encoder.encodeInto(jsonStr) });
    const out2 = await cipher.doFinal(null);
    const body = SecureCrypto.concat(out1.data, out2.data);
    return SecureCrypto.b64.encodeToStringSync(SecureCrypto.concat(iv, body));
  }

  static async decrypt(payload: string): Promise<string> {
    const decoded = SecureCrypto.b64.decodeSync(payload);
    const iv = decoded.subarray(0, IV_LENGTH);
    const cipherData = decoded.subarray(IV_LENGTH);
    const key = await SecureCrypto.toSymKey();
    const cipher = cryptoFramework.createCipher('AES256|CBC|PKCS7');
    await cipher.init(cryptoFramework.CryptoMode.DECRYPT_MODE, key, { data: iv });
    const out1 = await cipher.update({ data: cipherData });
    const out2 = await cipher.doFinal(null);
    const plain = SecureCrypto.concat(out1.data, out2.data);
    return SecureCrypto.decoder.decodeToString(plain);
  }

  private static async toSymKey(): Promise<cryptoFramework.SymKey> {
    const generator = cryptoFramework.createSymKeyGenerator('AES256');
    return generator.convertKey({ data: SecureCrypto.encoder.encodeInto(ENCRYPTION_KEY) });
  }

  private static concat(a: Uint8Array, b: Uint8Array): Uint8Array {
    const out = new Uint8Array(a.length + b.length);
    out.set(a, 0);
    out.set(b, a.length);
    return out;
  }
}
