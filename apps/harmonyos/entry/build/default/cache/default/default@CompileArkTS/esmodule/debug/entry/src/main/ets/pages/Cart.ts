if (!("finalizeConstruction" in ViewPU.prototype)) {
    Reflect.set(ViewPU.prototype, "finalizeConstruction", () => { });
}
interface Cart_Params {
    items?: Array<Record<string, Object>>;
    total?: number;
    api?: ApiClient;
}
import router from "@ohos:router";
import { ApiClient } from "@bundle:com.erik.shop/entry/ets/common/api/ApiClient";
class Cart extends ViewPU {
    constructor(parent, params, __localStorage, elmtId = -1, paramsLambda = undefined, extraInfo) {
        super(parent, __localStorage, elmtId, extraInfo);
        if (typeof paramsLambda === "function") {
            this.paramsGenerator_ = paramsLambda;
        }
        this.__items = new ObservedPropertyObjectPU([], this, "items");
        this.__total = new ObservedPropertySimplePU(0, this, "total");
        this.api = ApiClient.getInstance();
        this.setInitiallyProvidedValue(params);
        this.finalizeConstruction();
    }
    setInitiallyProvidedValue(params: Cart_Params) {
        if (params.items !== undefined) {
            this.items = params.items;
        }
        if (params.total !== undefined) {
            this.total = params.total;
        }
        if (params.api !== undefined) {
            this.api = params.api;
        }
    }
    updateStateVars(params: Cart_Params) {
    }
    purgeVariableDependenciesOnElmtId(rmElmtId) {
        this.__items.purgeDependencyOnElmtId(rmElmtId);
        this.__total.purgeDependencyOnElmtId(rmElmtId);
    }
    aboutToBeDeleted() {
        this.__items.aboutToBeDeleted();
        this.__total.aboutToBeDeleted();
        SubscriberManager.Get().delete(this.id__());
        this.aboutToBeDeletedInternal();
    }
    private __items: ObservedPropertyObjectPU<Array<Record<string, Object>>>;
    get items() {
        return this.__items.get();
    }
    set items(newValue: Array<Record<string, Object>>) {
        this.__items.set(newValue);
    }
    private __total: ObservedPropertySimplePU<number>;
    get total() {
        return this.__total.get();
    }
    set total(newValue: number) {
        this.__total.set(newValue);
    }
    private api: ApiClient;
    aboutToAppear() { this.loadCart(); }
    async loadCart() {
        const res = await this.api.get('/cart');
        if (res.code === 0) {
            this.items = (res.data as Array<Record<string, Object>>) ?? [];
            this.calcTotal();
        }
    }
    calcTotal() {
        this.total = this.items.reduce((sum, item) => sum + ((item['price'] as number) ?? 0) * ((item['quantity'] as number) ?? 1), 0);
    }
    async removeItem(id: string) {
        await this.api.delete(`/cart/${id}`);
        this.loadCart();
    }
    initialRender() {
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Column.create();
            Column.width('100%');
            Column.height('100%');
        }, Column);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Text.create('购物车');
            Text.fontSize(20);
            Text.fontWeight(FontWeight.Bold);
            Text.width('100%');
            Text.padding(16);
        }, Text);
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            If.create();
            if (this.items.length === 0) {
                this.ifElseBranchUpdateFunction(0, () => {
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Column.create();
                        Column.width('100%');
                        Column.height('60%');
                        Column.justifyContent(FlexAlign.Center);
                    }, Column);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Image.create({ "id": 16777226, "type": 20000, params: [], "bundleName": "com.erik.shop", "moduleName": "entry" });
                        Image.width(80);
                        Image.height(80);
                        Image.opacity(0.3);
                    }, Image);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Text.create('购物车是空的');
                        Text.fontSize(14);
                        Text.fontColor('#999');
                        Text.margin({ top: 8 });
                    }, Text);
                    Text.pop();
                    Column.pop();
                });
            }
            else {
                this.ifElseBranchUpdateFunction(1, () => {
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        List.create();
                        List.layoutWeight(1);
                    }, List);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        ForEach.create();
                        const forEachItemGenFunction = _item => {
                            const item = _item;
                            {
                                const itemCreation = (elmtId, isInitialRender) => {
                                    ViewStackProcessor.StartGetAccessRecordingFor(elmtId);
                                    ListItem.create(deepRenderFunction, true);
                                    if (!isInitialRender) {
                                        ListItem.pop();
                                    }
                                    ViewStackProcessor.StopGetAccessRecording();
                                };
                                const itemCreation2 = (elmtId, isInitialRender) => {
                                    ListItem.create(deepRenderFunction, true);
                                };
                                const deepRenderFunction = (elmtId, isInitialRender) => {
                                    itemCreation(elmtId, isInitialRender);
                                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                                        Row.create();
                                        Row.width('100%');
                                        Row.padding(8);
                                    }, Row);
                                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                                        Image.create((item['image'] as string) ?? '');
                                        Image.width(80);
                                        Image.height(80);
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
                                        Text.fontSize(14);
                                    }, Text);
                                    Text.pop();
                                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                                        Text.create(`$${(item['price'] as number)?.toFixed(2)} x ${item['quantity']}`);
                                        Text.fontSize(12);
                                        Text.fontColor('#999');
                                    }, Text);
                                    Text.pop();
                                    Column.pop();
                                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                                        Button.createWithLabel('删除');
                                        Button.fontSize(12);
                                        Button.onClick(() => this.removeItem(item['id'] as string));
                                    }, Button);
                                    Button.pop();
                                    Row.pop();
                                    ListItem.pop();
                                };
                                this.observeComponentCreation2(itemCreation2, ListItem);
                                ListItem.pop();
                            }
                        };
                        this.forEachUpdateFunction(elmtId, this.items, forEachItemGenFunction);
                    }, ForEach);
                    ForEach.pop();
                    List.pop();
                });
            }
        }, If);
        If.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Row.create();
            Row.width('100%');
            Row.padding(16);
            Row.justifyContent(FlexAlign.SpaceBetween);
        }, Row);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Text.create(`合计: $${this.total.toFixed(2)}`);
            Text.fontSize(18);
            Text.fontWeight(FontWeight.Bold);
        }, Text);
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Button.createWithLabel('去结算');
            Button.onClick(() => router.pushUrl({ url: 'pages/Checkout' }));
        }, Button);
        Button.pop();
        Row.pop();
        Column.pop();
    }
    rerender() {
        this.updateDirtyElements();
    }
    static getEntryName(): string {
        return "Cart";
    }
}
registerNamedRoute(() => new Cart(undefined, {}), "", { bundleName: "com.erik.shop", moduleName: "entry", pagePath: "pages/Cart", pageFullPath: "entry/src/main/ets/pages/Cart", integratedHsp: "false", moduleType: "followWithHap" });
