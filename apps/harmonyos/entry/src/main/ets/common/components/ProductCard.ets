/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * 商品卡片组件 - 用于首页、搜索、列表
 */
import { router } from '@kit.ArkUI';

@Component
export struct ProductCard {
  @Prop product: ProductCardItem;

  build() {
    Column() {
      Image(this.product.main_image || '')
        .width('100%').aspectRatio(1).objectFit(ImageFit.Cover)
        .borderRadius({ topLeft: 8, topRight: 8 })
      Column() {
        Text(this.product.title || '')
          .fontSize(13).maxLines(2).textOverflow({ overflow: TextOverflow.Ellipsis })
          .width('100%').padding({ left: 8, right: 8, top: 4 })
        Text('$' + ((this.product.min_price ?? 0) as number).toFixed(2))
          .fontSize(14).fontWeight(FontWeight.Bold).fontColor('#FF5722')
          .padding({ left: 8, right: 8 })
        if (this.product.is_hot) {
          Text('HOT')
            .fontSize(10).fontColor('#FF5722')
            .padding({ left: 8, bottom: 4 })
        }
      }.alignItems(HorizontalAlign.Start).width('100%')
    }
    .backgroundColor(Color.White).borderRadius(8)
    .onClick(() => {
      router.pushUrl({ url: 'pages/ProductDetail', params: { id: this.product.id } })
    })
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
