/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import { ApiClient } from '../common/api/ApiClient';

@Entry
@Component
struct Cart {
  @State items: Array<Record<string, Object>> = [];
  @State total: number = 0;
  private api: ApiClient = ApiClient.getInstance();

  aboutToAppear() { this.loadCart(); }

  async loadCart() {
    const res = await this.api.get('/cart');
    if (res.code === 0) {
      this.items = (res.data as Array<Record<string, Object>>) ?? [];
      this.calcTotal();
    }
  }

  calcTotal() {
    this.total = this.items.reduce((sum, item) =>
      sum + (item['price'] as number ?? 0) * (item['quantity'] as number ?? 1), 0);
  }

  async removeItem(id: string) {
    await this.api.delete(`/cart/${id}`);
    this.loadCart();
  }

  build() {
    Column() {
      Text('购物车').fontSize(20).fontWeight(FontWeight.Bold).width('100%').padding(16)

      if (this.items.length === 0) {
        Column() {
          Image($r('app.media.empty_cart')).width(80).height(80).opacity(0.3)
          Text('购物车是空的').fontSize(14).fontColor('#999').margin({ top: 8 })
        }
        .width('100%').height('60%').justifyContent(FlexAlign.Center)
      } else {
        List() {
          ForEach(this.items, (item: Record<string, Object>) => {
            ListItem() {
              Row() {
                Image(item['image'] as string ?? '').width(80).height(80)
                Column() {
                  Text(item['title'] as string ?? '').maxLines(2).fontSize(14)
                  Text(`$${(item['price'] as number)?.toFixed(2)} x ${item['quantity']}`)
                    .fontSize(12).fontColor('#999')
                }.alignItems(HorizontalAlign.Start).layoutWeight(1).padding(8)
                Button('删除').fontSize(12).onClick(() => this.removeItem(item['id'] as string))
              }.width('100%').padding(8)
            }
          })
        }.layoutWeight(1)
      }

      Row() {
        Text(`合计: $${this.total.toFixed(2)}`).fontSize(18).fontWeight(FontWeight.Bold)
        Button('去结算').onClick(() => router.pushUrl({ url: 'pages/Checkout' }))
      }
      .width('100%').padding(16).justifyContent(FlexAlign.SpaceBetween)
    }
    .width('100%').height('100%')
  }
}
