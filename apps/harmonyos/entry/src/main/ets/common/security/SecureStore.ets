/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * Token 安全存储：基于 Asset Kit（security.asset）密钥库
 * 替代 preferences 明文存储；接口保持 get/put/remove(name) 形式
 */
import asset from '@ohos.security.asset';
import util from '@ohos.util';

export class SecureStore {
  private static encoder = util.TextEncoder.create();
  private static decoder = util.TextDecoder.create('utf-8');

  static async get(name: string): Promise<string> {
    try {
      const result = await asset.query(asset.AssetType.SECRET, {
        [asset.Tag.SECRET_NAME]: name,
        [asset.Tag.RETURN_TYPE]: asset.ReturnType.ALL,
      });
      const maps: Array<Record<string, Object>> = Array.isArray(result) ?
        (result as Array<Record<string, Object>>) : [result as Record<string, Object>];
      if (maps.length === 0) {
        return '';
      }
      const data = maps[0][asset.Tag.SECRET_DATA] as Uint8Array;
      if (data === undefined || data.length === 0) {
        return '';
      }
      return SecureStore.decoder.decodeToString(data);
    } catch (e) {
      return '';
    }
  }

  static async put(name: string, value: string): Promise<void> {
    const data = SecureStore.encoder.encodeInto(value);
    try {
      await asset.add(asset.AssetType.SECRET, {
        [asset.Tag.SECRET_NAME]: name,
        [asset.Tag.SECRET_DATA]: data,
        [asset.Tag.ACCESSIBILITY]: asset.Accessibility.DEVICE_FIRST_UNLOCKED,
      });
    } catch (e) {
      // 同名条目已存在（重复登录/刷新），改为更新
      try {
        await asset.update(asset.AssetType.SECRET, {
          [asset.Tag.SECRET_NAME]: name,
          [asset.Tag.UPDATE_ATTR]: {
            [asset.Tag.SECRET_DATA]: data,
          },
        });
      } catch (e2) {
      }
    }
  }

  static async remove(name: string): Promise<void> {
    try {
      await asset.remove(asset.AssetType.SECRET, {
        [asset.Tag.SECRET_NAME]: name,
      });
    } catch (e) {
    }
  }
}
