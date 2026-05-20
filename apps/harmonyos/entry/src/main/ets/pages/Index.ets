/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * 首页 - Banner + 分类入口 + 热门商品
 */
import { ApiClient } from '../common/api/ApiClient';
import { ProductCard } from '../common/components/ProductCard';

@Entry
@Component
struct Index {
  @State banners: Array<BannerItem> = [];
  @State hotProducts: Array<ProductItem> = [];
  @State categories: Array<CategoryItem> = [];
  private api: ApiClient = ApiClient.getInstance();

  aboutToAppear() {
    this.loadData();
  }

  async loadData() {
    const bannerRes = await this.api.get('/banners', { position: 'home' });
    if (bannerRes.code === 0) this.banners = bannerRes.data as Array<BannerItem>;

    const productRes = await this.api.get('/products', { per_page: 10, sort: 'sales' });
    if (productRes.code === 0) {
      const data = productRes.data as Record<string, Object>;
      this.hotProducts = data['list'] as Array<ProductItem>;
    }
  }

  build() {
    Column() {
      // 顶部搜索栏
      Row() {
        Search({ placeholder: '搜索商品' })
          .width('80%')
        Image($r('app.media.cart_icon'))
          .width(28).height(28)
          .onClick(() => router.pushUrl({ url: 'pages/Cart' }))
      }
      .width('100%').padding(16).justifyContent(FlexAlign.SpaceBetween)

      // 轮播Banner
      Swiper() {
        ForEach(this.banners, (banner: BannerItem) => {
          Image(banner.image).width('100%').height(180).objectFit(ImageFit.Cover)
        })
      }
      .autoPlay(true).interval(3000).height(180)

      // 分类入口
      Grid() {
        ForEach(this.categories, (cat: CategoryItem) => {
          GridItem() {
            Column() {
              Image(cat.icon).width(40).height(40)
              Text(cat.name).fontSize(12).margin({ top: 4 })
            }
          }
        })
      }
      .columnsTemplate('1fr 1fr 1fr 1fr 1fr')
      .rowsGap(8).columnsGap(8).padding(12)

      // 热门商品
      Text('热门推荐').fontSize(18).fontWeight(FontWeight.Bold).width('100%').padding(16)

      Grid() {
        ForEach(this.hotProducts, (product: ProductItem) => {
          GridItem() {
            ProductCard({ product: product })
          }
        })
      }
      .columnsTemplate('1fr 1fr')
      .rowsGap(8).columnsGap(8).padding(12)
    }
    .width('100%').height('100%')
  }
}

interface BannerItem { id: string; title: string; image: string; link_url: string }
interface ProductItem { id: string; title: string; main_image: string; min_price: number; max_price: number; sales_count: number }
interface CategoryItem { id: string; name: string; icon: string; slug: string }
