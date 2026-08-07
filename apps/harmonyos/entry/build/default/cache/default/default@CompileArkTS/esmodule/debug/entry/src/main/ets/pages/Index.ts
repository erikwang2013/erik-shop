if (!("finalizeConstruction" in ViewPU.prototype)) {
    Reflect.set(ViewPU.prototype, "finalizeConstruction", () => { });
}
interface Index_Params {
    banners?: Array<BannerItem>;
    hotProducts?: Array<ProductItem>;
    categories?: Array<CategoryItem>;
    api?: ApiClient;
}
import router from "@ohos:router";
import { ApiClient } from "@bundle:com.erik.shop/entry/ets/common/api/ApiClient";
import { ProductCard } from "@bundle:com.erik.shop/entry/ets/common/components/ProductCard";
class Index extends ViewPU {
    constructor(parent, params, __localStorage, elmtId = -1, paramsLambda = undefined, extraInfo) {
        super(parent, __localStorage, elmtId, extraInfo);
        if (typeof paramsLambda === "function") {
            this.paramsGenerator_ = paramsLambda;
        }
        this.__banners = new ObservedPropertyObjectPU([], this, "banners");
        this.__hotProducts = new ObservedPropertyObjectPU([], this, "hotProducts");
        this.__categories = new ObservedPropertyObjectPU([], this, "categories");
        this.api = ApiClient.getInstance();
        this.setInitiallyProvidedValue(params);
        this.finalizeConstruction();
    }
    setInitiallyProvidedValue(params: Index_Params) {
        if (params.banners !== undefined) {
            this.banners = params.banners;
        }
        if (params.hotProducts !== undefined) {
            this.hotProducts = params.hotProducts;
        }
        if (params.categories !== undefined) {
            this.categories = params.categories;
        }
        if (params.api !== undefined) {
            this.api = params.api;
        }
    }
    updateStateVars(params: Index_Params) {
    }
    purgeVariableDependenciesOnElmtId(rmElmtId) {
        this.__banners.purgeDependencyOnElmtId(rmElmtId);
        this.__hotProducts.purgeDependencyOnElmtId(rmElmtId);
        this.__categories.purgeDependencyOnElmtId(rmElmtId);
    }
    aboutToBeDeleted() {
        this.__banners.aboutToBeDeleted();
        this.__hotProducts.aboutToBeDeleted();
        this.__categories.aboutToBeDeleted();
        SubscriberManager.Get().delete(this.id__());
        this.aboutToBeDeletedInternal();
    }
    private __banners: ObservedPropertyObjectPU<Array<BannerItem>>;
    get banners() {
        return this.__banners.get();
    }
    set banners(newValue: Array<BannerItem>) {
        this.__banners.set(newValue);
    }
    private __hotProducts: ObservedPropertyObjectPU<Array<ProductItem>>;
    get hotProducts() {
        return this.__hotProducts.get();
    }
    set hotProducts(newValue: Array<ProductItem>) {
        this.__hotProducts.set(newValue);
    }
    private __categories: ObservedPropertyObjectPU<Array<CategoryItem>>;
    get categories() {
        return this.__categories.get();
    }
    set categories(newValue: Array<CategoryItem>) {
        this.__categories.set(newValue);
    }
    private api: ApiClient;
    aboutToAppear() {
        this.loadData();
    }
    async loadData() {
        const bannerRes = await this.api.get('/banners', { position: 'home' });
        if (bannerRes.code === 0)
            this.banners = bannerRes.data as Array<BannerItem>;
        const productRes = await this.api.get('/products', { per_page: 10, sort: 'sales' });
        if (productRes.code === 0) {
            const data = productRes.data as Record<string, Object>;
            this.hotProducts = data['list'] as Array<ProductItem>;
        }
    }
    initialRender() {
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Column.create();
            Column.width('100%');
            Column.height('100%');
        }, Column);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            // 顶部搜索栏
            Row.create();
            // 顶部搜索栏
            Row.width('100%');
            // 顶部搜索栏
            Row.padding(16);
            // 顶部搜索栏
            Row.justifyContent(FlexAlign.SpaceBetween);
        }, Row);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Search.create({ placeholder: '搜索商品' });
            Search.width('80%');
        }, Search);
        Search.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            Image.create({ "id": 16777225, "type": 20000, params: [], "bundleName": "com.erik.shop", "moduleName": "entry" });
            Image.width(28);
            Image.height(28);
            Image.onClick(() => router.pushUrl({ url: 'pages/Cart' }));
        }, Image);
        // 顶部搜索栏
        Row.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            // 轮播Banner
            Swiper.create();
            // 轮播Banner
            Swiper.autoPlay(true);
            // 轮播Banner
            Swiper.interval(3000);
            // 轮播Banner
            Swiper.height(180);
        }, Swiper);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            ForEach.create();
            const forEachItemGenFunction = _item => {
                const banner = _item;
                this.observeComponentCreation2((elmtId, isInitialRender) => {
                    Image.create(banner.image);
                    Image.width('100%');
                    Image.height(180);
                    Image.objectFit(ImageFit.Cover);
                }, Image);
            };
            this.forEachUpdateFunction(elmtId, this.banners, forEachItemGenFunction);
        }, ForEach);
        ForEach.pop();
        // 轮播Banner
        Swiper.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            // 分类入口
            Grid.create();
            // 分类入口
            Grid.columnsTemplate('1fr 1fr 1fr 1fr 1fr');
            // 分类入口
            Grid.rowsGap(8);
            // 分类入口
            Grid.columnsGap(8);
            // 分类入口
            Grid.padding(12);
        }, Grid);
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            ForEach.create();
            const forEachItemGenFunction = _item => {
                const cat = _item;
                {
                    const itemCreation2 = (elmtId, isInitialRender) => {
                        GridItem.create(() => { }, false);
                    };
                    const observedDeepRender = () => {
                        this.observeComponentCreation2(itemCreation2, GridItem);
                        this.observeComponentCreation2((elmtId, isInitialRender) => {
                            Column.create();
                        }, Column);
                        this.observeComponentCreation2((elmtId, isInitialRender) => {
                            Image.create(cat.icon);
                            Image.width(40);
                            Image.height(40);
                        }, Image);
                        this.observeComponentCreation2((elmtId, isInitialRender) => {
                            Text.create(cat.name);
                            Text.fontSize(12);
                            Text.margin({ top: 4 });
                        }, Text);
                        Text.pop();
                        Column.pop();
                        GridItem.pop();
                    };
                    observedDeepRender();
                }
            };
            this.forEachUpdateFunction(elmtId, this.categories, forEachItemGenFunction);
        }, ForEach);
        ForEach.pop();
        // 分类入口
        Grid.pop();
        this.observeComponentCreation2((elmtId, isInitialRender) => {
            // 热门商品
            Text.create('热门推荐');
            // 热门商品
            Text.fontSize(18);
            // 热门商品
            Text.fontWeight(FontWeight.Bold);
            // 热门商品
            Text.width('100%');
            // 热门商品
            Text.padding(16);
        }, Text);
        // 热门商品
        Text.pop();
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
                const product = _item;
                {
                    const itemCreation2 = (elmtId, isInitialRender) => {
                        GridItem.create(() => { }, false);
                    };
                    const observedDeepRender = () => {
                        this.observeComponentCreation2(itemCreation2, GridItem);
                        {
                            this.observeComponentCreation2((elmtId, isInitialRender) => {
                                if (isInitialRender) {
                                    let componentCall = new ProductCard(this, { product: product }, undefined, elmtId, () => { }, { page: "entry/src/main/ets/pages/Index.ets", line: 72, col: 13 });
                                    ViewPU.create(componentCall);
                                    let paramsLambda = () => {
                                        return {
                                            product: product
                                        };
                                    };
                                    componentCall.paramsGenerator_ = paramsLambda;
                                }
                                else {
                                    this.updateStateVarsOfChildByElmtId(elmtId, {
                                        product: product
                                    });
                                }
                            }, { name: "ProductCard" });
                        }
                        GridItem.pop();
                    };
                    observedDeepRender();
                }
            };
            this.forEachUpdateFunction(elmtId, this.hotProducts, forEachItemGenFunction);
        }, ForEach);
        ForEach.pop();
        Grid.pop();
        Column.pop();
    }
    rerender() {
        this.updateDirtyElements();
    }
    static getEntryName(): string {
        return "Index";
    }
}
interface BannerItem {
    id: string;
    title: string;
    image: string;
    link_url: string;
}
interface ProductItem {
    id: string;
    title: string;
    main_image: string;
    min_price: number;
    max_price: number;
    sales_count: number;
}
interface CategoryItem {
    id: string;
    name: string;
    icon: string;
    slug: string;
}
registerNamedRoute(() => new Index(undefined, {}), "", { bundleName: "com.erik.shop", moduleName: "entry", pagePath: "pages/Index", pageFullPath: "entry/src/main/ets/pages/Index", integratedHsp: "false", moduleType: "followWithHap" });
