/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import { router } from '@kit.ArkUI';
import { ApiClient } from '../common/api/ApiClient';
import { AppState } from '../store/AppState';
import { SecureStore } from '../common/security/SecureStore';

@Entry
@Component
struct Login {
  @State email: string = '';
  @State password: string = '';
  private api: ApiClient = ApiClient.getInstance();

  async login() {
    const res = await this.api.post('/auth/login', { email: this.email, password: this.password });
    if (res.code === 0) {
      const data = res.data as Record<string, string>;
      await AppState.getInstance().setToken(data['access_token'] as string);
      await SecureStore.put('refresh_token', data['refresh_token'] as string);
      router.pushUrl({ url: 'pages/Index' });
    } else {
      AlertDialog.show({ message: res.msg });
    }
  }

  build() {
    Column({ space: 16 }) {
      Text('登录').fontSize(24).fontWeight(FontWeight.Bold).margin({ top: 40 })
      TextInput({ placeholder: '邮箱', text: this.email })
        .type(InputType.Email)
        .onChange((value: string) => { this.email = value })
      TextInput({ placeholder: '密码', text: this.password })
        .type(InputType.Password)
        .onChange((value: string) => { this.password = value })
      Button('登录')
        .width('100%')
        .onClick(() => { this.login() })
      Button('注册')
        .width('100%')
        .backgroundColor(Color.Gray)
        .onClick(() => { router.pushUrl({ url: 'pages/Register' }) })
    }
    .padding(24).width('100%')
  }
}
