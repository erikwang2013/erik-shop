if (!("finalizeConstruction" in ViewPU.prototype)) {
    Reflect.set(ViewPU.prototype, "finalizeConstruction", () => { });
}
interface Checkout_Params {
    cartItems?: Array<Record<string, Object>>;
    shippingOptions?: Array<Record<string, Object>>;
    paymentMethods?: Array<Record<string, Object>>;
    addresses?: Array<Record<string, Object>>;
    selectedShipping?: number;
    selectedPayment?: string;
    selectedAddressIdx?: number;
    total?: number;
    shippingFee?: number;
    posterQuestion?: string;
    posterToken?: string;
    posterAnswer?: string;
    posterVerified?: boolean;
    api?: ApiClient;
}
import promptAction from "@ohos:promptAction";
import router from "@ohos:router";
import { ApiClient } from "@bundle:com.erik.shop/entry/ets/common/api/ApiClient";
import type { ApiResponse } from "@bundle:com.erik.shop/entry/ets/common/api/ApiClient";
class Checkout extends ViewPU {
    constructor(parent, params, __localStorage, elmtId = -1, paramsLambda = undefined, extraInfo) {
        super(parent, __localStorage, elmtId, extraInfo);
        if (typeof paramsLambda === "function") {
            this.paramsGenerator_ = paramsLambda;
        }
        this.__cartItems = new ObservedPropertyObjectPU([], this, "cartItems");
        this.__shippingOptions = new ObservedPropertyObjectPU([], this, "shippingOptions");
        this.__paymentMethods = new ObservedPropertyObjectPU([], this, "paymentMethods");
        this.__addresses = new ObservedPropertyObjectPU([], this, "addresses");
        this.__selectedShipping = new ObservedPropertySimplePU(0, this, "selectedShipping");
        this.__selectedPayment = new ObservedPropertySimplePU('card', this, "selectedPayment");
        this.__selectedAddressIdx = new ObservedPropertySimplePU(0, this, "selectedAddressIdx");
        this.__total = new ObservedPropertySimplePU(0, this, "total");
        this.__shippingFee = new ObservedPropertySimplePU(0, this, "shippingFee");
        this.__posterQuestion = new ObservedPropertySimplePU('', this, "posterQuestion");
        this.__posterToken = new ObservedPropertySimplePU('', this, "posterToken");
        this.__posterAnswer = new ObservedPropertySimplePU('', this, "posterAnswer");
        this.__posterVerified = new ObservedPropertySimplePU(false, this, "posterVerified");
        this.api = ApiClient.getInstance();
        this.setInitiallyProvidedValue(params);
        this.finalizeConstruction();
    }
    setInitiallyProvidedValue(params: Checkout_Params) {
        if (params.cartItems !== undefined) {
            this.cartItems = params.cartItems;
        }
        if (params.shippingOptions !== undefined) {
            this.shippingOptions = params.shippingOptions;
        }
        if (params.paymentMethods !== undefined) {
            this.paymentMethods = params.paymentMethods;
        }
        if (params.addresses !== undefined) {
            this.addresses = params.addresses;
        }
        if (params.selectedShipping !== undefined) {
            this.selectedShipping = params.selectedShipping;
        }
        if (params.selectedPayment !== undefined) {
            this.selectedPayment = params.selectedPayment;
        }
        if (params.selectedAddressIdx !== undefined) {
            this.selectedAddressIdx = params.selectedAddressIdx;
        }
        if (params.total !== undefined) {
            this.total = params.total;
        }
        if (params.shippingFee !== undefined) {
            this.shippingFee = params.shippingFee;
        }
        if (params.posterQuestion !== undefined) {
            this.posterQuestion = params.posterQuestion;
        }
        if (params.posterToken !== undefined) {
            this.posterToken = params.posterToken;
        }
        if (params.posterAnswer !== undefined) {
            this.posterAnswer = params.posterAnswer;
        }
        if (params.posterVerified !== undefined) {
            this.posterVerified = params.posterVerified;
        }
        if (params.api !== undefined) {
            this.api = params.api;
        }
    }
    updateStateVars(params: Checkout_Params) {
    }
    purgeVariableDependenciesOnElmtId(rmElmtId) {
        this.__cartItems.purgeDependencyOnElmtId(rmElmtId);
        this.__shippingOptions.purgeDependencyOnElmtId(rmElmtId);
        this.__paymentMethods.purgeDependencyOnElmtId(rmElmtId);
        this.__addresses.purgeDependencyOnElmtId(rmElmtId);
        this.__selectedShipping.purgeDependencyOnElmtId(rmElmtId);
        this.__selectedPayment.purgeDependencyOnElmtId(rmElmtId);
        this.__selectedAddressIdx.purgeDependencyOnElmtId(rmElmtId);
        this.__total.purgeDependencyOnElmtId(rmElmtId);
        this.__shippingFee.purgeDependencyOnElmtId(rmElmtId);
        this.__posterQuestion.purgeDependencyOnElmtId(rmElmtId);
        this.__posterToken.purgeDependencyOnElmtId(rmElmtId);
        this.__posterAnswer.purgeDependencyOnElmtId(rmElmtId);
        this.__posterVerified.purgeDependencyOnElmtId(rmElmtId);
    }
    aboutToBeDeleted() {
        this.__cartItems.aboutToBeDeleted();
        this.__shippingOptions.aboutToBeDeleted();
        this.__paymentMethods.aboutToBeDeleted();
        this.__addresses.aboutToBeDeleted();
        this.__selectedShipping.aboutToBeDeleted();
        this.__selectedPayment.aboutToBeDeleted();
        this.__selectedAddressIdx.aboutToBeDeleted();
        this.__total.aboutToBeDeleted();
        this.__shippingFee.aboutToBeDeleted();
        this.__posterQuestion.aboutToBeDeleted();
        this.__posterToken.aboutToBeDeleted();
        this.__posterAnswer.aboutToBeDeleted();
        this.__posterVerified.aboutToBeDeleted();
        SubscriberManager.Get().delete(this.id__());
        this.aboutToBeDeletedInternal();
    }
    private __cartItems: ObservedPropertyObjectPU<Array<Record<string, Object>>>;
    get cartItems() {
        return this.__cartItems.get();
    }
    set cartItems(newValue: Array<Record<string, Object>>) {
        this.__cartItems.set(newValue);
    }
    private __shippingOptions: ObservedPropertyObjectPU<Array<Record<string, Object>>>;
    get shippingOptions() {
        return this.__shippingOptions.get();
    }
    set shippingOptions(newValue: Array<Record<string, Object>>) {
        this.__shippingOptions.set(newValue);
    }
    private __paymentMethods: ObservedPropertyObjectPU<Array<Record<string, Object>>>;
    get paymentMethods() {
        return this.__paymentMethods.get();
    }
    set paymentMethods(newValue: Array<Record<string, Object>>) {
        this.__paymentMethods.set(newValue);
    }
    private __addresses: ObservedPropertyObjectPU<Array<Record<string, Object>>>;
    get addresses() {
        return this.__addresses.get();
    }
    set addresses(newValue: Array<Record<string, Object>>) {
        this.__addresses.set(newValue);
    }
    private __selectedShipping: ObservedPropertySimplePU<number>;
    get selectedShipping() {
        return this.__selectedShipping.get();
    }
    set selectedShipping(newValue: number) {
        this.__selectedShipping.set(newValue);
    }
    private __selectedPayment: ObservedPropertySimplePU<string>;
    get selectedPayment() {
        return this.__selectedPayment.get();
    }
    set selectedPayment(newValue: string) {
        this.__selectedPayment.set(newValue);
    }
    private __selectedAddressIdx: ObservedPropertySimplePU<number>;
    get selectedAddressIdx() {
        return this.__selectedAddressIdx.get();
    }
    set selectedAddressIdx(newValue: number) {
        this.__selectedAddressIdx.set(newValue);
    }
    private __total: ObservedPropertySimplePU<number>;
    get total() {
        return this.__total.get();
    }
    set total(newValue: number) {
        this.__total.set(newValue);
    }
    private __shippingFee: ObservedPropertySimplePU<number>;
    get shippingFee() {
        return this.__shippingFee.get();
    }
    set shippingFee(newValue: number) {
        this.__shippingFee.set(newValue);
    }
    private __posterQuestion: ObservedPropertySimplePU<string>;
    get posterQuestion() {
        return this.__posterQuestion.get();
    }
    set posterQuestion(newValue: string) {
        this.__posterQuestion.set(newValue);
    }
    private __posterToken: ObservedPropertySimplePU<string>;
    get posterToken() {
        return this.__posterToken.get();
    }
    set posterToken(newValue: string) {
        this.__posterToken.set(newValue);
    }
    private __posterAnswer: ObservedPropertySimplePU<string>;
    get posterAnswer() {
        return this.__posterAnswer.get();
    }
    set posterAnswer(newValue: string) {
        this.__posterAnswer.set(newValue);
    }
    private __posterVerified: ObservedPropertySimplePU<boolean>;
    get posterVerified() {
        return this.__posterVerified.get();
    }
    set posterVerified(newValue: boolean) {
        this.__posterVerified.set(newValue);
    }
    private api: ApiClient;
    aboutToAppear() { this.loadData(); }
    async loadData() {
        const cartRes = await this.api.get('/cart');
        if (cartRes.code === 0) {
            this.cartItems = (cartRes.data as Array<Record<string, Object>>) ?? [];
            this.total = this.cartItems.reduce((sum, item) => sum + ((item['price'] as number) ?? 0) * ((item['quantity'] as number) ?? 1), 0);
        }
        // 地址列表（下单必填 address_id）
        const addrRes = await this.api.get('/user/addresses');
        if (addrRes.code === 0) {
            this.addresses = (addrRes.data as Array<Record<string, Object>>) ?? [];
            if (this.addresses.length > 0) {
                this.refreshShipping();
            }
        }
        const payRes = await this.api.get('/payment/methods', { country: 'US', currency: 'USD' });
        if (payRes.code === 0) {
            this.paymentMethods = (payRes.data as Array<Record<string, Object>>) ?? [];
        }
        // 人机验证题目（下单受 PosterVerify 保护）
        const posterRes = await this.api.get('/poster/challenge');
        if (posterRes.code === 0) {
            const p = posterRes.data as Record<string, Object>;
            this.posterQuestion = (p['question'] as string) ?? '';
            this.posterToken = (p['token'] as string) ?? '';
        }
    }
    refreshShipping() {
        if (this.addresses.length === 0) {
            return;
        }
        const addr = this.addresses[this.selectedAddressIdx];
        const countryId = (addr['country_id'] as string) ?? '';
        const self = this;
        this.api.get('/shipping/calculate', { dest_country_id: countryId, weight: 500 }).then((shipRes: ApiResponse) => {
            if (shipRes.code === 0) {
                const data = shipRes.data as Record<string, Object>;
                self.shippingOptions = (data['options'] as Array<Record<string, Object>>) ?? [];
                if (self.shippingOptions.length > 0) {
                    self.shippingFee = (self.shippingOptions[0]['fee'] as number) ?? 0;
                }
            }
        });
    }
    selectPrevAddress() {
        if (this.addresses.length === 0) {
            return;
        }
        this.selectedAddressIdx = (this.selectedAddressIdx - 1 + this.addresses.length) % this.addresses.length;
        this.refreshShipping();
    }
    selectNextAddress() {
        if (this.addresses.length === 0) {
            return;
        }
        this.selectedAddressIdx = (this.selectedAddressIdx + 1) % this.addresses.length;
        this.refreshShipping();
    }
    async verifyPoster() {
        if (this.posterToken === '' || this.posterAnswer.trim() === '') {
            promptAction.showToast({ message: '请输入验证答案' });
            return;
        }
        const res = await this.api.post('/poster/verify', { token: this.posterToken, answer: this.posterAnswer.trim() });
        if (res.code === 0) {
            const d = res.data as Record<string, Object>;
            this.posterToken = (d['token'] as string) ?? '';
            this.posterVerified = true;
            promptAction.showToast({ message: '人机验证通过' });
        }
        else {
            promptAction.showToast({ message: res.msg });
            // 刷新新题目
            const posterRes = await this.api.get('/poster/challenge');
            if (posterRes.code === 0) {
                const p = posterRes.data as Record<string, Object>;
                this.posterQuestion = (p['question'] as string) ?? '';
                this.posterToken = (p['token'] as string) ?? '';
                this.posterAnswer = '';
            }
        }
    }
    async placeOrder() {
        if (this.addresses.length === 0) {
            promptAction.showToast({ message: '请先在个人中心添加收货地址' });
            return;
        }
        if (!this.posterVerified || this.posterToken === '') {
            promptAction.showToast({ message: '请先完成人机验证' });
            return;
        }
        const addr = this.addresses[this.selectedAddressIdx];
        const res = await this.api.post('/orders', {
            address_id: (addr['id'] as string) ?? '',
            currency_code: 'USD',
            weight_grams: 500,
        }, { 'X-Poster-Token': this.posterToken });
        if (res.code === 0) {
            const d = res.data as Record<string, Object>;
            const orderId = (d['order_id'] as string) ?? '';
            promptAction.showToast({ message: '下单成功' });
            // 发起支付（真实 Stripe SDK 支付需后续集成，此处先创建支付单）
            const payRes = await this.api.post('/payment/create', { order_id: orderId, gateway: 'stripe' });
            if (payRes.code === 0) {
                promptAction.showToast({ message: '支付已创建，请完成支付' });
            }
            else {
                promptAction.showToast({ message: payRes.msg });
            }
            router.pushUrl({ url: 'pages/OrderList' });
        }
        else {
            promptAction.showToast({ message: res.msg });
        }
    }
    initialRender() {
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Column.create();
            Column.width('100%');
            Column.height('100%');
        }, Column);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Text.create('结算');
            Text.fontSize(20);
            Text.fontWeight(FontWeight.Bold);
            Text.width('100%');
            Text.padding(16);
        }, Text);
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Scroll.create();
            Scroll.layoutWeight(1);
        }, Scroll);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Column.create();
            Column.width('100%');
        }, Column);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            // 收货地址
            Text.create('收货地址');
            // 收货地址
            Text.fontSize(16);
            // 收货地址
            Text.fontWeight(FontWeight.Bold);
            // 收货地址
            Text.width('100%');
            // 收货地址
            Text.padding(12);
        }, Text);
        // 收货地址
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            If.create();
            if (this.addresses.length > 0) {
                this.ifElseBranchUpdateFunction(0, () => {
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Row.create();
                        Row.width('100%');
                        Row.padding(8);
                    }, Row);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Button.createWithLabel('上一条');
                        Button.fontSize(12);
                        Button.onClick(() => this.selectPrevAddress());
                    }, Button);
                    Button.pop();
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Column.create();
                        Column.alignItems(HorizontalAlign.Start);
                        Column.layoutWeight(1);
                        Column.padding({ left: 8, right: 8 });
                    }, Column);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Text.create(((this.addresses[this.selectedAddressIdx]['name'] as string) ?? '') + ' ' +
                            ((this.addresses[this.selectedAddressIdx]['phone'] as string) ?? ''));
                        Text.fontSize(14);
                        Text.maxLines(1);
                    }, Text);
                    Text.pop();
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Text.create(((this.addresses[this.selectedAddressIdx]['city'] as string) ?? '') + ' ' +
                            ((this.addresses[this.selectedAddressIdx]['detail'] as string) ?? ''));
                        Text.fontSize(12);
                        Text.fontColor('#999');
                        Text.maxLines(2);
                    }, Text);
                    Text.pop();
                    Column.pop();
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Button.createWithLabel('下一条');
                        Button.fontSize(12);
                        Button.onClick(() => this.selectNextAddress());
                    }, Button);
                    Button.pop();
                    Row.pop();
                });
            }
            else {
                this.ifElseBranchUpdateFunction(1, () => {
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Text.create('暂无收货地址，请先到个人中心添加');
                        Text.fontSize(13);
                        Text.fontColor('#999');
                        Text.padding(12);
                    }, Text);
                    Text.pop();
                });
            }
        }, If);
        If.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Divider.create();
        }, Divider);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            // 订单商品
            Text.create('订单商品');
            // 订单商品
            Text.fontSize(16);
            // 订单商品
            Text.fontWeight(FontWeight.Bold);
            // 订单商品
            Text.width('100%');
            // 订单商品
            Text.padding(12);
        }, Text);
        // 订单商品
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            ForEach.create();
            const forEachItemGenFunction = _item => {
                const item = _item;
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Row.create();
                    Row.width('100%');
                    Row.padding(8);
                }, Row);
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Image.create((item['image'] as string) ?? '');
                    Image.width(60);
                    Image.height(60);
                }, Image);
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Column.create();
                    Column.alignItems(HorizontalAlign.Start);
                    Column.layoutWeight(1);
                    Column.padding(8);
                }, Column);
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Text.create((item['title'] as string) ?? '');
                    Text.maxLines(2);
                    Text.fontSize(13);
                }, Text);
                Text.pop();
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Text.create('$' + ((item['price'] as number) ?? 0).toFixed(2) + ' x ' + (item['quantity'] ?? 1));
                    Text.fontSize(12);
                    Text.fontColor('#999');
                }, Text);
                Text.pop();
                Column.pop();
                Row.pop();
            };
            this.forEachUpdateFunction(elmtId, this.cartItems, forEachItemGenFunction);
        }, ForEach);
        ForEach.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Divider.create();
        }, Divider);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            // 物流方式
            Text.create('配送方式');
            // 物流方式
            Text.fontSize(16);
            // 物流方式
            Text.fontWeight(FontWeight.Bold);
            // 物流方式
            Text.width('100%');
            // 物流方式
            Text.padding(12);
        }, Text);
        // 物流方式
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            ForEach.create();
            const forEachItemGenFunction = (_item, index: number) => {
                const opt = _item;
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Row.create();
                    Row.width('100%');
                    Row.padding(8);
                }, Row);
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Radio.create({ value: index.toString(), group: 'shipping' });
                    Radio.onChange((_checked: boolean) => {
                        this.selectedShipping = index;
                        this.shippingFee = (opt['fee'] as number) ?? 0;
                    });
                }, Radio);
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Column.create();
                    Column.alignItems(HorizontalAlign.Start);
                    Column.margin({ left: 8 });
                }, Column);
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Text.create((opt['logistics_name'] as string) ?? '');
                    Text.fontSize(14);
                }, Text);
                Text.pop();
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Text.create('$' + ((opt['fee'] as number) ?? 0).toFixed(2) + ' · ' + ((opt['estimated_days'] as string) ?? ''));
                    Text.fontSize(12);
                    Text.fontColor('#999');
                }, Text);
                Text.pop();
                Column.pop();
                Row.pop();
            };
            this.forEachUpdateFunction(elmtId, this.shippingOptions, forEachItemGenFunction, undefined, true, false);
        }, ForEach);
        ForEach.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Divider.create();
        }, Divider);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            // 支付方式
            Text.create('支付方式');
            // 支付方式
            Text.fontSize(16);
            // 支付方式
            Text.fontWeight(FontWeight.Bold);
            // 支付方式
            Text.width('100%');
            // 支付方式
            Text.padding(12);
        }, Text);
        // 支付方式
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            ForEach.create();
            const forEachItemGenFunction = _item => {
                const method = _item;
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Row.create();
                    Row.width('100%');
                    Row.padding(8);
                }, Row);
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Radio.create({ value: (method['method_code'] as string) ?? '', group: 'payment' });
                    Radio.onChange((_checked: boolean) => {
                        this.selectedPayment = (method['method_code'] as string) ?? '';
                    });
                }, Radio);
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Text.create((method['method_name'] as string) ?? '');
                    Text.fontSize(14);
                    Text.margin({ left: 8 });
                }, Text);
                Text.pop();
                Row.pop();
            };
            this.forEachUpdateFunction(elmtId, this.paymentMethods, forEachItemGenFunction);
        }, ForEach);
        ForEach.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Divider.create();
        }, Divider);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            // 人机验证（PosterVerify）
            Text.create('人机验证');
            // 人机验证（PosterVerify）
            Text.fontSize(16);
            // 人机验证（PosterVerify）
            Text.fontWeight(FontWeight.Bold);
            // 人机验证（PosterVerify）
            Text.width('100%');
            // 人机验证（PosterVerify）
            Text.padding(12);
        }, Text);
        // 人机验证（PosterVerify）
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Row.create();
            Row.width('100%');
            Row.padding(8);
        }, Row);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Text.create(this.posterQuestion);
            Text.fontSize(16);
            Text.fontWeight(FontWeight.Bold);
            Text.layoutWeight(1);
        }, Text);
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            TextInput.create({ placeholder: '输入答案', text: this.posterAnswer });
            TextInput.onChange((value: string) => { this.posterAnswer = value; });
            TextInput.layoutWeight(1);
        }, TextInput);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Button.createWithLabel(this.posterVerified ? '已通过' : '验证');
            Button.fontSize(12);
            Button.enabled(!this.posterVerified);
            Button.onClick(() => this.verifyPoster());
        }, Button);
        Button.pop();
        Row.pop();
        Column.pop();
        Scroll.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            // 底部汇总
            Row.create();
            // 底部汇总
            Row.width('100%');
            // 底部汇总
            Row.padding(16);
            // 底部汇总
            Row.justifyContent(FlexAlign.SpaceBetween);
        }, Row);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Column.create();
            Column.alignItems(HorizontalAlign.Start);
            Column.layoutWeight(1);
        }, Column);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Text.create('商品: $' + this.total.toFixed(2));
            Text.fontSize(12);
        }, Text);
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Text.create('运费: $' + this.shippingFee.toFixed(2));
            Text.fontSize(12);
        }, Text);
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Text.create('合计: $' + (this.total + this.shippingFee).toFixed(2));
            Text.fontSize(18);
            Text.fontWeight(FontWeight.Bold);
            Text.fontColor('#FF5722');
        }, Text);
        Text.pop();
        Column.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Button.createWithLabel('提交订单');
            Button.onClick(() => this.placeOrder());
        }, Button);
        Button.pop();
        // 底部汇总
        Row.pop();
        Column.pop();
    }
    rerender() {
        this.updateDirtyElements();
    }
    static getEntryName(): string {
        return "Checkout";
    }
}
registerNamedRoute(() => new Checkout(undefined, {}), "", { bundleName: "com.erik.shop", moduleName: "entry", pagePath: "pages/Checkout", pageFullPath: "entry/src/main/ets/pages/Checkout", integratedHsp: "false", moduleType: "followWithHap" });
