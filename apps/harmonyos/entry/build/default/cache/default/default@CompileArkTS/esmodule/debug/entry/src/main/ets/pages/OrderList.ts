if (!("finalizeConstruction" in ViewPU.prototype)) {
    Reflect.set(ViewPU.prototype, "finalizeConstruction", () => { });
}
interface OrderList_Params {
    orders?: Array<Record<string, Object>>;
    selectedTab?: number;
    loading?: boolean;
    api?: ApiClient;
}
import { ApiClient } from "@bundle:com.erik.shop/entry/ets/common/api/ApiClient";
import type { QueryParams } from "@bundle:com.erik.shop/entry/ets/common/api/ApiClient";
class OrderList extends ViewPU {
    constructor(parent, params, __localStorage, elmtId = -1, paramsLambda = undefined, extraInfo) {
        super(parent, __localStorage, elmtId, extraInfo);
        if (typeof paramsLambda === "function") {
            this.paramsGenerator_ = paramsLambda;
        }
        this.__orders = new ObservedPropertyObjectPU([], this, "orders");
        this.__selectedTab = new ObservedPropertySimplePU(-1, this, "selectedTab");
        this.__loading = new ObservedPropertySimplePU(true, this, "loading");
        this.api = ApiClient.getInstance();
        this.setInitiallyProvidedValue(params);
        this.finalizeConstruction();
    }
    setInitiallyProvidedValue(params: OrderList_Params) {
        if (params.orders !== undefined) {
            this.orders = params.orders;
        }
        if (params.selectedTab !== undefined) {
            this.selectedTab = params.selectedTab;
        }
        if (params.loading !== undefined) {
            this.loading = params.loading;
        }
        if (params.api !== undefined) {
            this.api = params.api;
        }
    }
    updateStateVars(params: OrderList_Params) {
    }
    purgeVariableDependenciesOnElmtId(rmElmtId) {
        this.__orders.purgeDependencyOnElmtId(rmElmtId);
        this.__selectedTab.purgeDependencyOnElmtId(rmElmtId);
        this.__loading.purgeDependencyOnElmtId(rmElmtId);
    }
    aboutToBeDeleted() {
        this.__orders.aboutToBeDeleted();
        this.__selectedTab.aboutToBeDeleted();
        this.__loading.aboutToBeDeleted();
        SubscriberManager.Get().delete(this.id__());
        this.aboutToBeDeletedInternal();
    }
    private __orders: ObservedPropertyObjectPU<Array<Record<string, Object>>>;
    get orders() {
        return this.__orders.get();
    }
    set orders(newValue: Array<Record<string, Object>>) {
        this.__orders.set(newValue);
    }
    private __selectedTab: ObservedPropertySimplePU<number>;
    get selectedTab() {
        return this.__selectedTab.get();
    }
    set selectedTab(newValue: number) {
        this.__selectedTab.set(newValue);
    }
    private __loading: ObservedPropertySimplePU<boolean>;
    get loading() {
        return this.__loading.get();
    }
    set loading(newValue: boolean) {
        this.__loading.set(newValue);
    }
    private api: ApiClient;
    aboutToAppear() { this.loadOrders(); }
    async loadOrders() {
        this.loading = true;
        const params: QueryParams = { per_page: 50 };
        if (this.selectedTab >= 0)
            params.status = this.selectedTab;
        const res = await this.api.get('/orders', params);
        if (res.code === 0) {
            const data = res.data as Record<string, Object>;
            this.orders = (data['list'] as Array<Record<string, Object>>) ?? [];
        }
        this.loading = false;
    }
    initialRender() {
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Column.create();
            Column.width('100%');
            Column.height('100%');
        }, Column);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Text.create('我的订单');
            Text.fontSize(20);
            Text.fontWeight(FontWeight.Bold);
            Text.width('100%');
            Text.padding(16);
        }, Text);
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Tabs.create({ index: this.selectedTab + 1 });
            Tabs.onChange((index: number) => {
                this.selectedTab = index - 1;
                this.loadOrders();
            });
        }, Tabs);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            TabContent.create(() => {
                this.orderList.bind(this)();
            });
            TabContent.tabBar('全部');
        }, TabContent);
        TabContent.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            TabContent.create(() => {
                this.orderList.bind(this)();
            });
            TabContent.tabBar('待付款');
        }, TabContent);
        TabContent.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            TabContent.create(() => {
                this.orderList.bind(this)();
            });
            TabContent.tabBar('已发货');
        }, TabContent);
        TabContent.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            TabContent.create(() => {
                this.orderList.bind(this)();
            });
            TabContent.tabBar('已完成');
        }, TabContent);
        TabContent.pop();
        Tabs.pop();
        Column.pop();
    }
    orderList(parent = null) {
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            If.create();
            if (this.loading) {
                this.ifElseBranchUpdateFunction(0, () => {
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Column.create();
                        Column.width('100%');
                        Column.height('60%');
                        Column.justifyContent(FlexAlign.Center);
                    }, Column);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        LoadingProgress.create();
                        LoadingProgress.width(40);
                        LoadingProgress.height(40);
                    }, LoadingProgress);
                    Column.pop();
                });
            }
            else if (this.orders.length === 0) {
                this.ifElseBranchUpdateFunction(1, () => {
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Column.create();
                        Column.width('100%');
                        Column.height('60%');
                        Column.justifyContent(FlexAlign.Center);
                    }, Column);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Text.create('暂无订单');
                        Text.fontSize(14);
                        Text.fontColor('#999');
                    }, Text);
                    Text.pop();
                    Column.pop();
                });
            }
            else {
                this.ifElseBranchUpdateFunction(2, () => {
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        List.create();
                    }, List);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        ForEach.create();
                        const forEachItemGenFunction = _item => {
                            const order = _item;
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
                                    ListItem.width('100%');
                                    ListItem.backgroundColor(Color.White);
                                    ListItem.borderRadius(8);
                                    ListItem.margin({ left: 12, right: 12, top: 6 });
                                };
                                const deepRenderFunction = (elmtId, isInitialRender) => {
                                    itemCreation(elmtId, isInitialRender);
                                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                                        Column.create();
                                        Column.alignItems(HorizontalAlign.Start);
                                        Column.padding(12);
                                    }, Column);
                                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                                        Row.create();
                                        Row.width('100%');
                                    }, Row);
                                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                                        Text.create('#' + ((order['order_no'] as string) ?? ''));
                                        Text.fontSize(14);
                                        Text.fontWeight(FontWeight.Bold);
                                    }, Text);
                                    Text.pop();
                                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                                        Blank.create();
                                    }, Blank);
                                    Blank.pop();
                                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                                        Text.create((order['status_text'] as string) ?? '');
                                        Text.fontSize(12);
                                        Text.fontColor('#FF5722');
                                    }, Text);
                                    Text.pop();
                                    Row.pop();
                                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                                        Text.create('$' + ((order['pay_amount'] as number) ?? 0).toFixed(2));
                                        Text.fontSize(16);
                                        Text.margin({ top: 4 });
                                    }, Text);
                                    Text.pop();
                                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                                        Text.create((order['created_at'] as string) ?? '');
                                        Text.fontSize(11);
                                        Text.fontColor('#999');
                                        Text.margin({ top: 2 });
                                    }, Text);
                                    Text.pop();
                                    Column.pop();
                                    ListItem.pop();
                                };
                                this.observeComponentCreation2(itemCreation2, ListItem);
                                ListItem.pop();
                            }
                        };
                        this.forEachUpdateFunction(elmtId, this.orders, forEachItemGenFunction);
                    }, ForEach);
                    ForEach.pop();
                    List.pop();
                });
            }
        }, If);
        If.pop();
    }
    rerender() {
        this.updateDirtyElements();
    }
    static getEntryName(): string {
        return "OrderList";
    }
}
registerNamedRoute(() => new OrderList(undefined, {}), "", { bundleName: "com.erik.shop", moduleName: "entry", pagePath: "pages/OrderList", pageFullPath: "entry/src/main/ets/pages/OrderList", integratedHsp: "false", moduleType: "followWithHap" });
