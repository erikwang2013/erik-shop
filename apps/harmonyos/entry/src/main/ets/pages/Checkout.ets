/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import { promptAction, router } from '@kit.ArkUI';
import { ApiClient } from '../common/api/ApiClient';

@Entry
@Component
struct Checkout {
  @State cartItems: Array<Record<string, Object>> = [];
  @State shippingOptions: Array<Record<string, Object>> = [];
  @State paymentMethods: Array<Record<string, Object>> = [];
  @State selectedShipping: number = 0;
  @State selectedPayment: string = 'card';
  @State total: number = 0;
  @State shippingFee: number = 0;
  private api: ApiClient = ApiClient.getInstance();

  aboutToAppear() { this.loadData(); }

  async loadData() {
    const cartRes = await this.api.get('/cart');
    if (cartRes.code === 0) {
      this.cartItems = (cartRes.data as Array<Record<string, Object>>) ?? [];
      this.total = this.cartItems.reduce((sum, item) =>
        sum + ((item['price'] as number) ?? 0) * ((item['quantity'] as number) ?? 1), 0);
    }

    const shipRes = await this.api.get('/shipping/calculate', { dest_country_id: 1, weight: 500 });
    if (shipRes.code === 0) {
      const data = shipRes.data as Record<string, Object>;
      this.shippingOptions = (data['options'] as Array<Record<string, Object>>) ?? [];
      if (this.shippingOptions.length > 0) {
        this.shippingFee = (this.shippingOptions[0]['fee'] as number) ?? 0;
      }
    }

    const payRes = await this.api.get('/payment/methods', { country: 'US', currency: 'USD' });
    if (payRes.code === 0) {
      this.paymentMethods = (payRes.data as Array<Record<string, Object>>) ?? [];
    }
  }

  async placeOrder() {
    const res = await this.api.post('/orders', { currency_code: 'USD' });
    if (res.code === 0) {
      promptAction.showToast({ message: '下单成功' });
      router.pushUrl({ url: 'pages/OrderList' });
    } else {
      promptAction.showToast({ message: res.msg });
    }
  }

  build() {
    Column() {
      Text('结算').fontSize(20).fontWeight(FontWeight.Bold).width('100%').padding(16)

      Scroll() {
        Column() {
          // 订单商品
          Text('订单商品').fontSize(16).fontWeight(FontWeight.Bold).width('100%').padding(12)
          ForEach(this.cartItems, (item: Record<string, Object>) => {
            Row() {
              Image((item['image'] as string) ?? '').width(60).height(60)
              Column() {
                Text((item['title'] as string) ?? '').maxLines(2).fontSize(13)
                Text('$' + ((item['price'] as number) ?? 0).toFixed(2) + ' x ' + (item['quantity'] ?? 1))
                  .fontSize(12).fontColor('#999')
              }.alignItems(HorizontalAlign.Start).layoutWeight(1).padding(8)
            }.width('100%').padding(8)
          })

          Divider()

          // 物流方式
          Text('配送方式').fontSize(16).fontWeight(FontWeight.Bold).width('100%').padding(12)
          ForEach(this.shippingOptions, (opt: Record<string, Object>, index: number) => {
            Row() {
              Radio({ value: index.toString(), group: 'shipping' }).onChange((_checked: boolean) => {
                this.selectedShipping = index;
                this.shippingFee = (opt['fee'] as number) ?? 0;
              })
              Column() {
                Text((opt['logistics_name'] as string) ?? '').fontSize(14)
                Text('$' + ((opt['fee'] as number) ?? 0).toFixed(2) + ' · ' + ((opt['estimated_days'] as string) ?? '')).fontSize(12).fontColor('#999')
              }.alignItems(HorizontalAlign.Start).margin({ left: 8 })
            }.width('100%').padding(8)
          })

          Divider()

          // 支付方式
          Text('支付方式').fontSize(16).fontWeight(FontWeight.Bold).width('100%').padding(12)
          ForEach(this.paymentMethods, (method: Record<string, Object>) => {
            Row() {
              Radio({ value: (method['method_code'] as string) ?? '', group: 'payment' }).onChange((_checked: boolean) => {
                this.selectedPayment = (method['method_code'] as string) ?? '';
              })
              Text((method['method_name'] as string) ?? '').fontSize(14).margin({ left: 8 })
            }.width('100%').padding(8)
          })
        }.width('100%')
      }.layoutWeight(1)

      // 底部汇总
      Row() {
        Column() {
          Text('商品: $' + this.total.toFixed(2)).fontSize(12)
          Text('运费: $' + this.shippingFee.toFixed(2)).fontSize(12)
          Text('合计: $' + (this.total + this.shippingFee).toFixed(2)).fontSize(18).fontWeight(FontWeight.Bold).fontColor('#FF5722')
        }.alignItems(HorizontalAlign.Start).layoutWeight(1)
        Button('提交订单').onClick(() => this.placeOrder())
      }.width('100%').padding(16).justifyContent(FlexAlign.SpaceBetween)
    }.width('100%').height('100%')
  }
}
