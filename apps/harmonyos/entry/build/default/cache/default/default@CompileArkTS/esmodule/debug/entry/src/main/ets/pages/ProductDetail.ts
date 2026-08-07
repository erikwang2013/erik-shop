if (!("finalizeConstruction" in ViewPU.prototype)) {
    Reflect.set(ViewPU.prototype, "finalizeConstruction", () => { });
}
interface ProductDetail_Params {
    product?: Record<string, Object>;
    loading?: boolean;
    productId?: string;
    api?: ApiClient;
}
import promptAction from "@ohos:promptAction";
import router from "@ohos:router";
import { ApiClient } from "@bundle:com.erik.shop/entry/ets/common/api/ApiClient";
class ProductDetail extends ViewPU {
    constructor(parent, params, __localStorage, elmtId = -1, paramsLambda = undefined, extraInfo) {
        super(parent, __localStorage, elmtId, extraInfo);
        if (typeof paramsLambda === "function") {
            this.paramsGenerator_ = paramsLambda;
        }
        this.__product = new ObservedPropertyObjectPU({}, this, "product");
        this.__loading = new ObservedPropertySimplePU(true, this, "loading");
        this.productId = '';
        this.api = ApiClient.getInstance();
        this.setInitiallyProvidedValue(params);
        this.finalizeConstruction();
    }
    setInitiallyProvidedValue(params: ProductDetail_Params) {
        if (params.product !== undefined) {
            this.product = params.product;
        }
        if (params.loading !== undefined) {
            this.loading = params.loading;
        }
        if (params.productId !== undefined) {
            this.productId = params.productId;
        }
        if (params.api !== undefined) {
            this.api = params.api;
        }
    }
    updateStateVars(params: ProductDetail_Params) {
    }
    purgeVariableDependenciesOnElmtId(rmElmtId) {
        this.__product.purgeDependencyOnElmtId(rmElmtId);
        this.__loading.purgeDependencyOnElmtId(rmElmtId);
    }
    aboutToBeDeleted() {
        this.__product.aboutToBeDeleted();
        this.__loading.aboutToBeDeleted();
        SubscriberManager.Get().delete(this.id__());
        this.aboutToBeDeletedInternal();
    }
    private __product: ObservedPropertyObjectPU<Record<string, Object>>;
    get product() {
        return this.__product.get();
    }
    set product(newValue: Record<string, Object>) {
        this.__product.set(newValue);
    }
    private __loading: ObservedPropertySimplePU<boolean>;
    get loading() {
        return this.__loading.get();
    }
    set loading(newValue: boolean) {
        this.__loading.set(newValue);
    }
    private productId: string;
    private api: ApiClient;
    aboutToAppear() {
        const params = router.getParams() as Record<string, string>;
        this.productId = params['id'] ?? '';
        this.loadProduct();
    }
    async loadProduct() {
        const res = await this.api.get(`/products/${this.productId}`);
        if (res.code === 0) {
            this.product = res.data as Record<string, Object>;
        }
        this.loading = false;
    }
    async addToCart() {
        const skus = this.product['skus'] as Array<Record<string, Object>>;
        if (!skus || skus.length === 0)
            return;
        const firstSku = skus[0];
        await this.api.post('/cart', { sku_id: (firstSku['id'] as string) ?? '', quantity: 1 });
        promptAction.showToast({ message: '已添加到购物车' });
    }
    initialRender() {
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Column.create();
            Column.width('100%');
            Column.height('100%');
            Column.justifyContent(FlexAlign.Center);
        }, Column);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            If.create();
            if (this.loading) {
                this.ifElseBranchUpdateFunction(0, () => {
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        LoadingProgress.create();
                        LoadingProgress.width(40);
                        LoadingProgress.height(40);
                    }, LoadingProgress);
                });
            }
            else {
                this.ifElseBranchUpdateFunction(1, () => {
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Scroll.create();
                        Scroll.layoutWeight(1);
                    }, Scroll);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Column.create();
                    }, Column);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Image.create((this.product['main_image'] as string) ?? '');
                        Image.width('100%');
                        Image.height(300);
                        Image.objectFit(ImageFit.Cover);
                    }, Image);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Column.create();
                        Column.padding(16);
                        Column.alignItems(HorizontalAlign.Start);
                    }, Column);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Text.create((this.product['title'] as string) ?? '');
                        Text.fontSize(20);
                        Text.fontWeight(FontWeight.Bold);
                        Text.margin({ top: 16, bottom: 8 });
                    }, Text);
                    Text.pop();
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Text.create(`$${(this.product['min_price'] as number)?.toFixed(2)}`);
                        Text.fontSize(24);
                        Text.fontColor('#FF5722');
                        Text.fontWeight(FontWeight.Bold);
                    }, Text);
                    Text.pop();
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Text.create((this.product['description'] as string) ?? '');
                        Text.fontSize(14);
                        Text.margin({ top: 12 });
                        Text.lineHeight(24);
                    }, Text);
                    Text.pop();
                    Column.pop();
                    Column.pop();
                    Scroll.pop();
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Row.create();
                        Row.padding(16);
                        Row.justifyContent(FlexAlign.SpaceBetween);
                    }, Row);
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Button.createWithLabel('加入购物车');
                        Button.width('48%');
                        Button.onClick(() => this.addToCart());
                    }, Button);
                    Button.pop();
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Button.createWithLabel('立即购买');
                        Button.width('48%');
                        Button.backgroundColor('#FF5722');
                        Button.onClick(() => { });
                    }, Button);
                    Button.pop();
                    Row.pop();
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
        return "ProductDetail";
    }
}
registerNamedRoute(() => new ProductDetail(undefined, {}), "", { bundleName: "com.erik.shop", moduleName: "entry", pagePath: "pages/ProductDetail", pageFullPath: "entry/src/main/ets/pages/ProductDetail", integratedHsp: "false", moduleType: "followWithHap" });
