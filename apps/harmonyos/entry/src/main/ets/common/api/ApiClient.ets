/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import http from '@ohos.net.http';
import preferences from '@ohos.data.preferences';

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
    const prefs = await preferences.getPreferences(context, 'erik_shop');
    this.token = prefs.getSync('access_token', '') as string;
    this.locale = prefs.getSync('locale', 'en') as string;
    this.baseUrl = prefs.getSync('base_url', DEFAULT_BASE_URL) as string;
  }

  setBaseUrl(url: string): void { this.baseUrl = url; }

  async get(path: string, params?: QueryParams): Promise<ApiResponse> {
    const url = this.baseUrl + path;
    const request = http.createHttp();

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

    const response = await request.request(url, {
      method: http.RequestMethod.GET,
      header: headers,
      extraData: params,
    });

    return JSON.parse(response.result as string) as ApiResponse;
  }

  async post(path: string, data?: RequestBody, extraHeaders?: Record<string, string>): Promise<ApiResponse> {
    const url = this.baseUrl + path;
    const request = http.createHttp();

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
    if (extraHeaders) {
      Object.keys(extraHeaders).forEach((key: string) => {
        headers[key] = extraHeaders[key] as string;
      });
    }

    const response = await request.request(url, {
      method: http.RequestMethod.POST,
      header: headers,
      extraData: JSON.stringify(data),
    });

    return JSON.parse(response.result as string) as ApiResponse;
  }

  setToken(token: string): void { this.token = token; }
  setLocale(locale: string): void { this.locale = locale; }

  async delete(path: string): Promise<ApiResponse> {
    const url = this.baseUrl + path;
    const request = http.createHttp();
    const headers: Record<string, string> = {
      "Content-Type": "application/json",
      "Accept": "application/json",
      "API-Version": API_VERSION,
      "Accept-Language": this.locale,
    };
    if (this.token) {
      headers["Authorization"] = `Bearer ${this.token}`;
    }
    const response = await request.request(url, {
      method: http.RequestMethod.DELETE,
      header: headers,
    });
    return JSON.parse(response.result as string) as ApiResponse;
  }
}

export interface ApiResponse {
  code: number;
  msg: string;
  data: Object | null;
}
