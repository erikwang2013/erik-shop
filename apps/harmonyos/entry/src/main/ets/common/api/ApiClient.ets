/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import http from '@ohos.net.http';
import preferences from '@ohos.data.preferences';
import { SecureStore } from '../security/SecureStore';
import { SecureCrypto, CRYPTO_ENABLED } from '../security/SecureCrypto';

const DEFAULT_BASE_URL = 'http://10.0.2.2:8787/api';
const API_VERSION = '2026-05-20';

/** 查询参数（GET），显式声明以满足 ArkTS 字面量约束 */
export interface QueryParams {
  position?: string;
  per_page?: number;
  sort?: string;
  keyword?: string;
  status?: number;
  dest_country_id?: string;
  weight?: number;
  country?: string;
  currency?: string;
}

/** 请求体（POST），显式声明以满足 ArkTS 字面量约束 */
export interface RequestBody {
  email?: string;
  password?: string;
  nickname?: string;
  sku_id?: string;
  quantity?: number;
  currency_code?: string;
  address_id?: string;
  coupon_id?: string;
  weight_grams?: number;
  gateway?: string;
  order_id?: string;
  token?: string;
  answer?: string;
}

export class ApiClient {
  private static instance: ApiClient;
  private token: string = '';
  private locale: string = 'en';
  private baseUrl: string = DEFAULT_BASE_URL;

  static getInstance(): ApiClient {
    if (!ApiClient.instance) {
      ApiClient.instance = new ApiClient();
    }
    return ApiClient.instance;
  }

  async init(context: Context): Promise<void> {
    this.token = await SecureStore.get('access_token');
    const prefs = await preferences.getPreferences(context, 'erik_shop');
    this.locale = prefs.getSync('locale', 'en') as string;
    this.baseUrl = prefs.getSync('base_url', DEFAULT_BASE_URL) as string;
  }

  setBaseUrl(url: string): void { this.baseUrl = url; }

  /** 公共请求头；加密开关开启时附带响应加密标记 */
  private buildHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'API-Version': API_VERSION,
      'Accept-Language': this.locale,
      'X-Platform': 'harmonyos',
    };
    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }
    if (CRYPTO_ENABLED) {
      headers['X-Encrypt-Response'] = '1';
    }
    return headers;
  }

  /** 响应解析；后端加密 data 字段时（encrypted=true）先解密再返回 */
  private async parseResponse(text: string): Promise<ApiResponse> {
    const json = JSON.parse(text) as Record<string, Object>;
    if (CRYPTO_ENABLED && json['encrypted'] === true && typeof json['data'] === 'string') {
      const decrypted = await SecureCrypto.decrypt(json['data'] as string);
      json['data'] = JSON.parse(decrypted) as Object;
    }
    return json as ApiResponse;
  }

  async get(path: string, params?: QueryParams): Promise<ApiResponse> {
    const url = this.baseUrl + path;
    const request = http.createHttp();
    const headers = this.buildHeaders();

    const response = await request.request(url, {
      method: http.RequestMethod.GET,
      header: headers,
      extraData: params,
    });

    return this.parseResponse(response.result as string);
  }

  async post(path: string, data?: RequestBody, extraHeaders?: Record<string, string>): Promise<ApiResponse> {
    const url = this.baseUrl + path;
    const request = http.createHttp();
    const headers = this.buildHeaders();
    if (extraHeaders) {
      Object.keys(extraHeaders).forEach((key: string) => {
        headers[key] = extraHeaders[key] as string;
      });
    }

    let body = '';
    if (data !== undefined) {
      body = CRYPTO_ENABLED ? await SecureCrypto.encrypt(JSON.stringify(data)) : JSON.stringify(data);
      if (CRYPTO_ENABLED) {
        headers['X-Encrypted'] = '1';
      }
    }

    const response = await request.request(url, {
      method: http.RequestMethod.POST,
      header: headers,
      extraData: body,
    });

    return this.parseResponse(response.result as string);
  }

  setToken(token: string): void { this.token = token; }
  setLocale(locale: string): void { this.locale = locale; }

  async delete(path: string): Promise<ApiResponse> {
    const url = this.baseUrl + path;
    const request = http.createHttp();
    const headers = this.buildHeaders();
    const response = await request.request(url, {
      method: http.RequestMethod.DELETE,
      header: headers,
    });
    return this.parseResponse(response.result as string);
  }
}

export interface ApiResponse {
  code: number;
  msg: string;
  data: Object | null;
}
