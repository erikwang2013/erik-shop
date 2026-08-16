/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * 全局状态管理 - Token/语言/币种/购物车
 */
import preferences from '@ohos.data.preferences';
import { ApiClient } from '../common/api/ApiClient';
import { SecureStore } from '../common/security/SecureStore';

export class AppState {
  private static instance: AppState;
  private prefs?: preferences.Preferences;
  token: string = '';
  locale: string = 'en';
  currency: string = 'USD';
  cartCount: number = 0;

  static getInstance(): AppState {
    if (!AppState.instance) AppState.instance = new AppState();
    return AppState.instance;
  }

  async init(context: Context): Promise<void> {
    this.prefs = await preferences.getPreferences(context, 'erik_shop');
    this.token = await SecureStore.get('access_token');
    this.locale = this.prefs.getSync('locale', 'en') as string;
    this.currency = this.prefs.getSync('currency', 'USD') as string;
    ApiClient.getInstance().setToken(this.token);
    ApiClient.getInstance().setLocale(this.locale);
    await this.refreshCartCount();
  }

  async setToken(token: string): Promise<void> {
    this.token = token;
    await SecureStore.put('access_token', token);
    ApiClient.getInstance().setToken(token);
  }

  async setLocale(locale: string): Promise<void> {
    this.locale = locale;
    await this.prefs?.put('locale', locale);
    await this.prefs?.flush();
    ApiClient.getInstance().setLocale(locale);
  }

  async setCurrency(currency: string): Promise<void> {
    this.currency = currency;
    await this.prefs?.put('currency', currency);
    await this.prefs?.flush();
  }

  async refreshCartCount(): Promise<void> {
    try {
      const res = await ApiClient.getInstance().get('/cart');
      if (res.code === 0) {
        const items = (res.data as Array<Object>) ?? [];
        this.cartCount = items.length;
      }
    } catch (e) {}
  }

  async logout(): Promise<void> {
    this.token = '';
    await SecureStore.remove('access_token');
    await SecureStore.remove('refresh_token');
    ApiClient.getInstance().setToken('');
  }

  isLoggedIn(): boolean { return this.token.length > 0; }
}
