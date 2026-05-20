/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import http from '@ohos.net.http';
import preferences from '@ohos.data.preferences';

const BASE_URL = 'http://10.0.2.2:8787/api';
const API_VERSION = '2026-05-20';

export class ApiClient {
  private static instance: ApiClient;
  private token: string = '';
  private locale: string = 'en';

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
  }

  async get(path: string, params?: Record<string, string | number>): Promise<ApiResponse> {
    const url = BASE_URL + path;
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

  async post(path: string, data?: Object): Promise<ApiResponse> {
    const url = BASE_URL + path;
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
      method: http.RequestMethod.POST,
      header: headers,
      extraData: JSON.stringify(data),
    });

    return JSON.parse(response.result as string) as ApiResponse;
  }

  setToken(token: string): void { this.token = token; }
  setLocale(locale: string): void { this.locale = locale; }
}

export interface ApiResponse {
  code: number;
  msg: string;
  data: Object | null;
}
  async delete(path: string): Promise<ApiResponse> {
    const url = BASE_URL + path;
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
