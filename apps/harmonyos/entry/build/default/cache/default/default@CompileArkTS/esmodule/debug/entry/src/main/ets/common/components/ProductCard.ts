if (!("finalizeConstruction" in ViewPU.prototype)) {
    Reflect.set(ViewPU.prototype, "finalizeConstruction", () => { });
}
interface ProductCard_Params {
    product?: ProductCardItem;
}
import router from "@ohos:router";
export class ProductCard extends ViewPU {
    constructor(parent, params, __localStorage, elmtId = -1, paramsLambda = undefined, extraInfo) {
        super(parent, __localStorage, elmtId, extraInfo);
        if (typeof paramsLambda === "function") {
            this.paramsGenerator_ = paramsLambda;
        }
        this.__product = new SynchedPropertyObjectOneWayPU(params.product, this, "product");
        this.setInitiallyProvidedValue(params);
        this.finalizeConstruction();
    }
    setInitiallyProvidedValue(params: ProductCard_Params) {
    }
    updateStateVars(params: ProductCard_Params) {
        this.__product.reset(params.product);
    }
    purgeVariableDependenciesOnElmtId(rmElmtId) {
        this.__product.purgeDependencyOnElmtId(rmElmtId);
    }
    aboutToBeDeleted() {
        this.__product.aboutToBeDeleted();
        SubscriberManager.Get().delete(this.id__());
        this.aboutToBeDeletedInternal();
    }
    private __product: SynchedPropertySimpleOneWayPU<ProductCardItem>;
    get product() {
        return this.__product.get();
    }
    set product(newValue: ProductCardItem) {
        this.__product.set(newValue);
    }
    initialRender() {
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Column.create();
            Column.backgroundColor(Color.White);
            Column.borderRadius(8);
            Column.onClick(() => {
                router.pushUrl({ url: 'pages/ProductDetail', params: { id: this.product.id } });
            });
        }, Column);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Image.create(this.product.main_image || '');
            Image.width('100%');
            Image.aspectRatio(1);
            Image.objectFit(ImageFit.Cover);
            Image.borderRadius({ topLeft: 8, topRight: 8 });
        }, Image);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Column.create();
            Column.alignItems(HorizontalAlign.Start);
            Column.width('100%');
        }, Column);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Text.create(this.product.title || '');
            Text.fontSize(13);
            Text.maxLines(2);
            Text.textOverflow({ overflow: TextOverflow.Ellipsis });
            Text.width('100%');
            Text.padding({ left: 8, right: 8, top: 4 });
        }, Text);
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Text.create('$' + ((this.product.min_price ?? 0) as number).toFixed(2));
            Text.fontSize(14);
            Text.fontWeight(FontWeight.Bold);
            Text.fontColor('#FF5722');
            Text.padding({ left: 8, right: 8 });
        }, Text);
        Text.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            If.create();
            if (this.product.is_hot) {
                this.ifElseBranchUpdateFunction(0, () => {
                    this.observeComponentCreation2((elmtId, isInitialRender) => {
                        Text.create('HOT');
                        Text.fontSize(10);
                        Text.fontColor('#FF5722');
                        Text.padding({ left: 8, bottom: 4 });
                    }, Text);
                    Text.pop();
                });
            }
            else {
                this.ifElseBranchUpdateFunction(1, () => {
                });
            }
        }, If);
        If.pop();
        Column.pop();
        Column.pop();
    }
    rerender() {
        this.updateDirtyElements();
    }
}
export interface ProductCardItem {
    id: string;
    title: string;
    main_image?: string;
    min_price?: number;
    max_price?: number;
    sales_count?: number;
    is_hot?: boolean;
    is_new?: boolean;
}
