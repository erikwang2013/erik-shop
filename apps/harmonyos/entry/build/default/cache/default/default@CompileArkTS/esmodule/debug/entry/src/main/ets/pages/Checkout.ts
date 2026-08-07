if (!("finalizeConstruction" in ViewPU.prototype)) {
    Reflect.set(ViewPU.prototype, "finalizeConstruction", () => { });
}
interface Checkout_Params {
    cartItems?: Array<Record<string, Object>>;
    shippingOptions?: Array<Record<string, Object>>;
    paymentMethods?: Array<Record<string, Object>>;
    selectedShipping?: number;
    selectedPayment?: string;
    total?: number;
    shippingFee?: number;
    api?: ApiClient;
}
import promptAction from "@ohos:promptAction";
import router from "@ohos:router";
import { ApiClient } from "@bundle:com.erik.shop/entry/ets/common/api/ApiClient";
class Checkout extends ViewPU {
    constructor(parent, params, __localStorage, elmtId = -1, paramsLambda = undefined, extraInfo) {
        super(parent, __localStorage, elmtId, extraInfo);
        if (typeof paramsLambda === "function") {
            this.paramsGenerator_ = paramsLambda;
        }
        this.__cartItems = new ObservedPropertyObjectPU([], this, "cartItems");
        this.__shippingOptions = new ObservedPropertyObjectPU([], this, "shippingOptions");
        this.__paymentMethods = new ObservedPropertyObjectPU([], this, "paymentMethods");
        this.__selectedShipping = new ObservedPropertySimplePU(0, this, "selectedShipping");
        this.__selectedPayment = new ObservedPropertySimplePU('card', this, "selectedPayment");
        this.__total = new ObservedPropertySimplePU(0, this, "total");
        this.__shippingFee = new ObservedPropertySimplePU(0, this, "shippingFee");
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
        if (params.selectedShipping !== undefined) {
            this.selectedShipping = params.selectedShipping;
        }
        if (params.selectedPayment !== undefined) {
            this.selectedPayment = params.selectedPayment;
        }
        if (params.total !== undefined) {
            this.total = params.total;
        }
        if (params.shippingFee !== undefined) {
            this.shippingFee = params.shippingFee;
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
        this.__selectedShipping.purgeDependencyOnElmtId(rmElmtId);
        this.__selectedPayment.purgeDependencyOnElmtId(rmElmtId);
        this.__total.purgeDependencyOnElmtId(rmElmtId);
        this.__shippingFee.purgeDependencyOnElmtId(rmElmtId);
    }
    aboutToBeDeleted() {
        this.__cartItems.aboutToBeDeleted();
        this.__shippingOptions.aboutToBeDeleted();
        this.__paymentMethods.aboutToBeDeleted();
        this.__selectedShipping.aboutToBeDeleted();
        this.__selectedPayment.aboutToBeDeleted();
        this.__total.aboutToBeDeleted();
        this.__shippingFee.aboutToBeDeleted();
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
    private api: ApiClient;
    aboutToAppear() { this.loadData(); }
    async loadData() {
        const cartRes = await this.api.get('/cart');
        if (cartRes.code === 0) {
            this.cartItems = (cartRes.data as Array<Record<string, Object>>) ?? [];
            this.total = this.cartItems.reduce((sum, item) => sum + ((item['price'] as number) ?? 0) * ((item['quantity'] as number) ?? 1), 0);
        }
        const shipRes = await this.api.get('/shipping/calculate', { dest_country_id: 1, weight: 500 });
        if (shipRes.code === 0) {
            const data = shipRes.data as Record<string, Object>;
            this.shippingOptions = (data['options'] as Array<Record<string, Object>>) ?? [];
            if (this.shippingOptions.length > 0) {
                this.shippingFee = (this.shippingOptions[0]['fee'] as number) ?? 0;
            }
        }
        const payRes = await this.api.get('/payment/methods', { country: 'US', currency: 'USD' });
        if (payRes.code === 0) {
            this.paymentMethods = (payRes.data as Array<Record<string, Object>>) ?? [];
        }
    }
    async placeOrder() {
        const res = await this.api.post('/orders', { currency_code: 'USD' });
        if (res.code === 0) {
            promptAction.showToast({ message: '下单成功' });
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
