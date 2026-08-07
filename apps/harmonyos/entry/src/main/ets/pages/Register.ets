/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import { router } from "@kit.ArkUI";
import { ApiClient } from "../common/api/ApiClient";
import { AppState } from "../store/AppState";

@Entry
@Component
struct Register {
  @State email: string = "";
  @State password: string = "";
  @State nickname: string = "";
  private api: ApiClient = ApiClient.getInstance();

  async register() {
    const res = await this.api.post("/auth/register", { email: this.email, password: this.password, nickname: this.nickname });
    if (res.code === 0) {
      const data = res.data as Record<string, string>;
      await AppState.getInstance().setToken(data["access_token"] as string);
      router.pushUrl({ url: "pages/Index" });
    } else {
      AlertDialog.show({ message: res.msg });
    }
  }

  build() {
    Column({ space: 16 }) {
      Text("注册").fontSize(24).fontWeight(FontWeight.Bold).margin({ top: 40 })
      TextInput({ placeholder: "昵称", text: this.nickname }).onChange((v: string) => { this.nickname = v })
      TextInput({ placeholder: "邮箱", text: this.email }).type(InputType.Email).onChange((v: string) => { this.email = v })
      TextInput({ placeholder: "密码", text: this.password }).type(InputType.Password).onChange((v: string) => { this.password = v })
      Button("注册").width("100%").onClick(() => { this.register() })
      Button("返回登录").width("100%").backgroundColor(Color.Gray).onClick(() => { router.back() })
    }.padding(24).width("100%")
  }
}
