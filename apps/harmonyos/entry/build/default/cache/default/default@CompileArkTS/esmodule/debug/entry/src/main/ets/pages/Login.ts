if (!("finalizeConstruction" in ViewPU.prototype)) {
    Reflect.set(ViewPU.prototype, "finalizeConstruction", () => { });
}
interface Login_Params {
    email?: string;
    password?: string;
    api?: ApiClient;
}
import router from "@ohos:router";
import { ApiClient } from "@bundle:com.erik.shop/entry/ets/common/api/ApiClient";
import preferences from "@ohos:data.preferences";
class Login extends ViewPU {
    constructor(parent, params, __localStorage, elmtId = -1, paramsLambda = undefined, extraInfo) {
        super(parent, __localStorage, elmtId, extraInfo);
        if (typeof paramsLambda === "function") {
            this.paramsGenerator_ = paramsLambda;
        }
        this.__email = new ObservedPropertySimplePU('', this, "email");
        this.__password = new ObservedPropertySimplePU('', this, "password");
        this.api = ApiClient.getInstance();
        this.setInitiallyProvidedValue(params);
        this.finalizeConstruction();
    }
    setInitiallyProvidedValue(params: Login_Params) {
        if (params.email !== undefined) {
            this.email = params.email;
        }
        if (params.password !== undefined) {
            this.password = params.password;
        }
        if (params.api !== undefined) {
            this.api = params.api;
        }
    }
    updateStateVars(params: Login_Params) {
    }
    purgeVariableDependenciesOnElmtId(rmElmtId) {
        this.__email.purgeDependencyOnElmtId(rmElmtId);
        this.__password.purgeDependencyOnElmtId(rmElmtId);
    }
    aboutToBeDeleted() {
        this.__email.aboutToBeDeleted();
        this.__password.aboutToBeDeleted();
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
    private api: ApiClient;
    async login() {
        const res = await this.api.post('/auth/login', { email: this.email, password: this.password });
        if (res.code === 0) {
            const data = res.data as Record<string, string>;
            this.api.setToken(data['access_token'] as string);
            const prefs = await preferences.getPreferences(getContext(), 'erik_shop');
            await prefs.put('access_token', data['access_token']);
            await prefs.put('refresh_token', data['refresh_token']);
            router.pushUrl({ url: 'pages/Index' });
        }
        else {
            AlertDialog.show({ message: res.msg });
        }
    }
    initialRender() {
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Column.create({ space: 16 });
            Column.padding(24);
            Column.width('100%');
        }, Column);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Text.create('登录');
            Text.fontSize(24);
            Text.fontWeight(FontWeight.Bold);
            Text.margin({ top: 40 });
        }, Text);
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            TextInput.create({ placeholder: '邮箱', text: this.email });
            TextInput.type(InputType.Email);
            TextInput.onChange((value: string) => { this.email = value; });
        }, TextInput);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            TextInput.create({ placeholder: '密码', text: this.password });
            TextInput.type(InputType.Password);
            TextInput.onChange((value: string) => { this.password = value; });
        }, TextInput);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Button.createWithLabel('登录');
            Button.width('100%');
            Button.onClick(() => { this.login(); });
        }, Button);
        Button.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Button.createWithLabel('注册');
            Button.width('100%');
            Button.backgroundColor(Color.Gray);
            Button.onClick(() => { router.pushUrl({ url: 'pages/Register' }); });
        }, Button);
        Button.pop();
        Column.pop();
    }
    rerender() {
        this.updateDirtyElements();
    }
    static getEntryName(): string {
        return "Login";
    }
}
registerNamedRoute(() => new Login(undefined, {}), "", { bundleName: "com.erik.shop", moduleName: "entry", pagePath: "pages/Login", pageFullPath: "entry/src/main/ets/pages/Login", integratedHsp: "false", moduleType: "followWithHap" });
