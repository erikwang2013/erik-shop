if (!("finalizeConstruction" in ViewPU.prototype)) {
    Reflect.set(ViewPU.prototype, "finalizeConstruction", () => { });
}
interface Register_Params {
    email?: string;
    password?: string;
    nickname?: string;
    api?: ApiClient;
}
import router from "@ohos:router";
import { ApiClient } from "@bundle:com.erik.shop/entry/ets/common/api/ApiClient";
import { AppState } from "@bundle:com.erik.shop/entry/ets/store/AppState";
class Register extends ViewPU {
    constructor(parent, params, __localStorage, elmtId = -1, paramsLambda = undefined, extraInfo) {
        super(parent, __localStorage, elmtId, extraInfo);
        if (typeof paramsLambda === "function") {
            this.paramsGenerator_ = paramsLambda;
        }
        this.__email = new ObservedPropertySimplePU("", this, "email");
        this.__password = new ObservedPropertySimplePU("", this, "password");
        this.__nickname = new ObservedPropertySimplePU("", this, "nickname");
        this.api = ApiClient.getInstance();
        this.setInitiallyProvidedValue(params);
        this.finalizeConstruction();
    }
    setInitiallyProvidedValue(params: Register_Params) {
        if (params.email !== undefined) {
            this.email = params.email;
        }
        if (params.password !== undefined) {
            this.password = params.password;
        }
        if (params.nickname !== undefined) {
            this.nickname = params.nickname;
        }
        if (params.api !== undefined) {
            this.api = params.api;
        }
    }
    updateStateVars(params: Register_Params) {
    }
    purgeVariableDependenciesOnElmtId(rmElmtId) {
        this.__email.purgeDependencyOnElmtId(rmElmtId);
        this.__password.purgeDependencyOnElmtId(rmElmtId);
        this.__nickname.purgeDependencyOnElmtId(rmElmtId);
    }
    aboutToBeDeleted() {
        this.__email.aboutToBeDeleted();
        this.__password.aboutToBeDeleted();
        this.__nickname.aboutToBeDeleted();
        SubscriberManager.Get().delete(this.id__());
        this.aboutToBeDeletedInternal();
    }
    private __email: ObservedPropertySimplePU<string>;
    get email() {
        return this.__email.get();
    }
    set email(newValue: string) {
        this.__email.set(newValue);
    }
    private __password: ObservedPropertySimplePU<string>;
    get password() {
        return this.__password.get();
    }
    set password(newValue: string) {
        this.__password.set(newValue);
    }
    private __nickname: ObservedPropertySimplePU<string>;
    get nickname() {
        return this.__nickname.get();
    }
    set nickname(newValue: string) {
        this.__nickname.set(newValue);
    }
    private api: ApiClient;
    async register() {
        const res = await this.api.post("/auth/register", { email: this.email, password: this.password, nickname: this.nickname });
        if (res.code === 0) {
            const data = res.data as Record<string, string>;
            await AppState.getInstance().setToken(data["access_token"] as string);
            router.pushUrl({ url: "pages/Index" });
        }
        else {
            AlertDialog.show({ message: res.msg });
        }
    }
    initialRender() {
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Column.create({ space: 16 });
            Column.padding(24);
            Column.width("100%");
        }, Column);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Text.create("注册");
            Text.fontSize(24);
            Text.fontWeight(FontWeight.Bold);
            Text.margin({ top: 40 });
        }, Text);
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            TextInput.create({ placeholder: "昵称", text: this.nickname });
            TextInput.onChange((v: string) => { this.nickname = v; });
        }, TextInput);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            TextInput.create({ placeholder: "邮箱", text: this.email });
            TextInput.type(InputType.Email);
            TextInput.onChange((v: string) => { this.email = v; });
        }, TextInput);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            TextInput.create({ placeholder: "密码", text: this.password });
            TextInput.type(InputType.Password);
            TextInput.onChange((v: string) => { this.password = v; });
        }, TextInput);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Button.createWithLabel("注册");
            Button.width("100%");
            Button.onClick(() => { this.register(); });
        }, Button);
        Button.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Button.createWithLabel("返回登录");
            Button.width("100%");
            Button.backgroundColor(Color.Gray);
            Button.onClick(() => { router.back(); });
        }, Button);
        Button.pop();
        Column.pop();
    }
    rerender() {
        this.updateDirtyElements();
    }
    static getEntryName(): string {
        return "Register";
    }
}
registerNamedRoute(() => new Register(undefined, {}), "", { bundleName: "com.erik.shop", moduleName: "entry", pagePath: "pages/Register", pageFullPath: "entry/src/main/ets/pages/Register", integratedHsp: "false", moduleType: "followWithHap" });
