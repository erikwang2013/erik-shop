/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import { ApiClient } from '../common/api/ApiClient';

@Entry
@Component
struct OrderList {
  @State orders: Array<Record<string, Object>> = [];
  @State selectedTab: number = -1;
  @State loading: boolean = true;
  private api: ApiClient = ApiClient.getInstance();

  aboutToAppear() { this.loadOrders(); }

  async loadOrders() {
    this.loading = true;
    const params: Record<string, string|number> = { per_page: 50 };
    if (this.selectedTab >= 0) params['status'] = this.selectedTab;
    const res = await this.api.get('/orders', params);
    if (res.code === 0) {
      const data = res.data as Record<string, Object>;
      this.orders = (data['list'] as Array<Record<string, Object>>) ?? [];
    }
    this.loading = false;
  }

  build() {
    Column() {
      Text('我的订单').fontSize(20).fontWeight(FontWeight.Bold).width('100%').padding(16)

      Tabs({ index: this.selectedTab + 1 }) {
        TabContent() { this.orderList() }.tabBar('全部')
        TabContent() { this.orderList() }.tabBar('待付款')
        TabContent() { this.orderList() }.tabBar('已发货')
        TabContent() { this.orderList() }.tabBar('已完成')
      }
      .onChange((index: number) => {
        this.selectedTab = index - 1;
        this.loadOrders();
      })
    }.width('100%').height('100%')
  }

  @Builder orderList() {
    if (this.loading) {
      Column() { LoadingProgress().width(40).height(40) }
        .width('100%').height('60%').justifyContent(FlexAlign.Center)
    } else if (this.orders.length === 0) {
      Column() { Text('暂无订单').fontSize(14).fontColor('#999') }
        .width('100%').height('60%').justifyContent(FlexAlign.Center)
    } else {
      List() {
        ForEach(this.orders, (order: Record<string, Object>) => {
          ListItem() {
            Column() {
              Row() {
                Text('#' + (order['order_no'] as string ?? '')).fontSize(14).fontWeight(FontWeight.Bold)
                Blank()
                Text(order['status_text'] as string ?? '').fontSize(12).fontColor('#FF5722')
              }.width('100%')
              Text('$' + ((order['pay_amount'] as number) ?? 0).toFixed(2)).fontSize(16).margin({ top: 4 })
              Text(order['created_at'] as string ?? '').fontSize(11).fontColor('#999').margin({ top: 2 })
            }.alignItems(HorizontalAlign.Start).padding(12)
          }.width('100%').backgroundColor(Color.White).borderRadius(8).margin({ left: 12, right: 12, top: 6 })
        })
      }
    }
  }
}
