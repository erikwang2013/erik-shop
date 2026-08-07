if (!("finalizeConstruction" in ViewPU.prototype)) {
    Reflect.set(ViewPU.prototype, "finalizeConstruction", () => { });
}
interface SearchPage_Params {
    keyword?: string;
    results?: Array<ProductCardItem>;
    totalCount?: number;
    loading?: boolean;
    searched?: boolean;
    api?: ApiClient;
}
import router from "@ohos:router";
import { ApiClient } from "@bundle:com.erik.shop/entry/ets/common/api/ApiClient";
import { ProductCard } from "@bundle:com.erik.shop/entry/ets/common/components/ProductCard";
import type { ProductCardItem } from "@bundle:com.erik.shop/entry/ets/common/components/ProductCard";
class SearchPage extends ViewPU {
    constructor(parent, params, __localStorage, elmtId = -1, paramsLambda = undefined, extraInfo) {
        super(parent, __localStorage, elmtId, extraInfo);
        if (typeof paramsLambda === "function") {
            this.paramsGenerator_ = paramsLambda;
        }
        this.__keyword = new ObservedPropertySimplePU('', this, "keyword");
        this.__results = new ObservedPropertyObjectPU([], this, "results");
        this.__totalCount = new ObservedPropertySimplePU(0, this, "totalCount");
        this.__loading = new ObservedPropertySimplePU(false, this, "loading");
        this.__searched = new ObservedPropertySimplePU(false, this, "searched");
        this.api = ApiClient.getInstance();
        this.setInitiallyProvidedValue(params);
        this.finalizeConstruction();
    }
    setInitiallyProvidedValue(params: SearchPage_Params) {
        if (params.keyword !== undefined) {
            this.keyword = params.keyword;
        }
        if (params.results !== undefined) {
            this.results = params.results;
        }
        if (params.totalCount !== undefined) {
            this.totalCount = params.totalCount;
        }
        if (params.loading !== undefined) {
            this.loading = params.loading;
        }
        if (params.searched !== undefined) {
            this.searched = params.searched;
        }
        if (params.api !== undefined) {
            this.api = params.api;
        }
    }
    updateStateVars(params: SearchPage_Params) {
    }
    purgeVariableDependenciesOnElmtId(rmElmtId) {
        this.__keyword.purgeDependencyOnElmtId(rmElmtId);
        this.__results.purgeDependencyOnElmtId(rmElmtId);
        this.__totalCount.purgeDependencyOnElmtId(rmElmtId);
        this.__loading.purgeDependencyOnElmtId(rmElmtId);
        this.__searched.purgeDependencyOnElmtId(rmElmtId);
    }
    aboutToBeDeleted() {
        this.__keyword.aboutToBeDeleted();
        this.__results.aboutToBeDeleted();
        this.__totalCount.aboutToBeDeleted();
        this.__loading.aboutToBeDeleted();
        this.__searched.aboutToBeDeleted();
        SubscriberManager.Get().delete(this.id__());
        this.aboutToBeDeletedInternal();
    }
    private __keyword: ObservedPropertySimplePU<string>;
    get keyword() {
        return this.__keyword.get();
    }
    set keyword(newValue: string) {
        this.__keyword.set(newValue);
    }
    private __results: ObservedPropertyObjectPU<Array<ProductCardItem>>;
    get results() {
        return this.__results.get();
    }
    set results(newValue: Array<ProductCardItem>) {
        this.__results.set(newValue);
    }
    private __totalCount: ObservedPropertySimplePU<number>;
    get totalCount() {
        return this.__totalCount.get();
    }
    set totalCount(newValue: number) {
        this.__totalCount.set(newValue);
    }
    private __loading: ObservedPropertySimplePU<boolean>;
    get loading() {
        return this.__loading.get();
    }
    set loading(newValue: boolean) {
        this.__loading.set(newValue);
    }
    private __searched: ObservedPropertySimplePU<boolean>;
    get searched() {
        return this.__searched.get();
    }
    set searched(newValue: boolean) {
        this.__searched.set(newValue);
    }
    private api: ApiClient;
    async doSearch() {
        if (!this.keyword.trim())
            return;
        this.loading = true;
        this.searched = true;
        const res = await this.api.get('/search', { keyword: this.keyword, per_page: 40 });
        if (res.code === 0) {
            const data = res.data as Record<string, Object>;
            this.results = (data['list'] as Array<ProductCardItem>) ?? [];
            this.totalCount = (data['total'] as number) ?? 0;
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
            Row.create();
            Row.width('100%');
            Row.padding(12);
        }, Row);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Image.create({ "id": 16777222, "type": 20000, params: [], "bundleName": "com.erik.shop", "moduleName": "entry" });
            Image.width(24);
            Image.height(24);
            Image.onClick(() => router.back());
        }, Image);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            TextInput.create({ placeholder: '搜索商品...', text: this.keyword });
            TextInput.layoutWeight(1);
            TextInput.margin({ left: 8, right: 8 });
            TextInput.onChange((value: string) => { this.keyword = value; });
            TextInput.onSubmit(() => { this.doSearch(); });
        }, TextInput);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Button.createWithLabel('搜索');
            Button.fontSize(14);
            Button.onClick(() => this.doSearch());
        }, Button);
        Button.pop();
        Row.pop();
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
            else if (this.searched) {
                this.ifElseBranchUpdateFunction(1, () => {
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Text.create('共' + this.totalCount + '个结果');
                        Text.fontSize(12);
                        Text.fontColor('#999');
                        Text.width('100%');
                        Text.padding(16);
                    }, Text);
                    Text.pop();
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        If.create();
                        if (this.results.length === 0) {
                            this.ifElseBranchUpdateFunction(0, () => {
                                this.observeComponentCreation2((elmtId, isInitialRender) => {
                                    Column.create();
                                    Column.width('100%');
                                    Column.height('50%');
                                    Column.justifyContent(FlexAlign.Center);
                                }, Column);
                                this.observeComponentCreation2((elmtId, isInitialRender) => {
                                    Image.create({ "id": 16777227, "type": 20000, params: [], "bundleName": "com.erik.shop", "moduleName": "entry" });
                                    Image.width(80);
                                    Image.height(80);
                                    Image.opacity(0.3);
                                }, Image);
                                this.observeComponentCreation2((elmtId, isInitialRender) => {
                                    Text.create('未找到相关商品');
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
                                    Grid.create();
                                    Grid.columnsTemplate('1fr 1fr');
                                    Grid.rowsGap(8);
                                    Grid.columnsGap(8);
                                    Grid.padding(12);
                                }, Grid);
                                this.observeComponentCreation2((elmtId, isInitialRender) => {
                                    ForEach.create();
                                    const forEachItemGenFunction = _item => {
                                        const item = _item;
                                        {
                                            const itemCreation2 = (elmtId, isInitialRender) => {
                                                GridItem.create(() => { }, false);
                                            };
                                            const observedDeepRender = () => {
                                                this.observeComponentCreation2(itemCreation2, GridItem);
                                                {
                                                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                                                        if (isInitialRender) {
                                                            let componentCall = new ProductCard(this, { product: item }, undefined, elmtId, () => { }, { page: "entry/src/main/ets/pages/Search.ets", line: 56, col: 28 });
                                                            ViewPU.create(componentCall);
                                                            let paramsLambda = () => {
                                                                return {
                                                                    product: item
                                                                };
                                                            };
                                                            componentCall.paramsGenerator_ = paramsLambda;
                                                        }
                                                        else {
                                                            this.updateStateVarsOfChildByElmtId(elmtId, {
                                                                product: item
                                                            });
                                                        }
                                                    }, { name: "ProductCard" });
                                                }
                                                GridItem.pop();
                                            };
                                            observedDeepRender();
                                        }
                                    };
                                    this.forEachUpdateFunction(elmtId, this.results, forEachItemGenFunction);
                                }, ForEach);
                                ForEach.pop();
                                Grid.pop();
                            });
                        }
                    }, If);
                    If.pop();
                });
            }
            else {
                this.ifElseBranchUpdateFunction(2, () => {
                });
            }
        }, If);
        If.pop();
        Column.pop();
    }
    rerender() {
        this.updateDirtyElements();
    }
    static getEntryName(): string {
        return "SearchPage";
    }
}
registerNamedRoute(() => new SearchPage(undefined, {}), "", { bundleName: "com.erik.shop", moduleName: "entry", pagePath: "pages/Search", pageFullPath: "entry/src/main/ets/pages/Search", integratedHsp: "false", moduleType: "followWithHap" });
