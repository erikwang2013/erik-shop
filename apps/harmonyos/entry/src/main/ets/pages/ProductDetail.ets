/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import { promptAction, router } from '@kit.ArkUI';
import { ApiClient } from '../common/api/ApiClient';

@Entry
@Component
struct ProductDetail {
  @State product: Record<string, Object> = {};
  @State loading: boolean = true;
  private productId: string = '';
  private api: ApiClient = ApiClient.getInstance();

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
    if (!skus || skus.length === 0) return;
    const firstSku = skus[0];
    await this.api.post('/cart', { sku_id: (firstSku['id'] as string) ?? '', quantity: 1 });
    promptAction.showToast({ message: '已添加到购物车' });
  }

  build() {
    Column() {
      if (this.loading) {
        LoadingProgress().width(40).height(40)
      } else {
        Scroll() {
          Column() {
            Image((this.product['main_image'] as string) ?? '')
              .width('100%').height(300).objectFit(ImageFit.Cover)

            Column() {
              Text((this.product['title'] as string) ?? '')
                .fontSize(20).fontWeight(FontWeight.Bold).margin({ top: 16, bottom: 8 })

              Text(`$${(this.product['min_price'] as number)?.toFixed(2)}`)
                .fontSize(24).fontColor('#FF5722').fontWeight(FontWeight.Bold)

              Text((this.product['description'] as string) ?? '')
                .fontSize(14).margin({ top: 12 }).lineHeight(24)
            }
            .padding(16).alignItems(HorizontalAlign.Start)
          }
        }
        .layoutWeight(1)

        Row() {
          Button('加入购物车').width('48%').onClick(() => this.addToCart())
          Button('立即购买').width('48%').backgroundColor('#FF5722').onClick(() => {})
        }
        .padding(16).justifyContent(FlexAlign.SpaceBetween)
      }
    }
    .width('100%').height('100%').justifyContent(FlexAlign.Center)
  }
}
