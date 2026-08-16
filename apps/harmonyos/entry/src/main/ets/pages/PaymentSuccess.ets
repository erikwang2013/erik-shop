/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * 支付完成页：下单支付成功后展示订单号/金额/状态，可返回订单列表或继续购物
 * 参数：order_no / amount / currency / status（均字符串）
 */
import { router } from '@kit.ArkUI';

@Entry
@Component
struct PaymentSuccess {
  @State orderNo: string = '';
  @State amount: string = '';
  @State currency: string = 'USD';
  @State status: string = '待支付';

  aboutToAppear() {
    const params = router.getParams() as Record<string, string>;
    this.orderNo = params['order_no'] ?? '';
    this.amount = params['amount'] ?? '';
    this.currency = params['currency'] ?? 'USD';
    this.status = params['status'] ?? '待支付';
  }

  build() {
    Column() {
      Text('✓').fontSize(64).fontColor('#52C41A').margin({ top: 48 })
      Text('订单提交成功').fontSize(20).fontWeight(FontWeight.Bold).margin({ top: 16 })
      Text('请尽快完成支付').fontSize(13).fontColor('#999').margin({ top: 8 })

      Column() {
        Row() {
          Text('订单号').fontSize(14).fontColor('#999')
          Text(this.orderNo).fontSize(14).layoutWeight(1).textAlign(TextAlign.End)
        }.width('100%').padding(8)
        Divider()
        Row() {
          Text('支付金额').fontSize(14).fontColor('#999')
          Text(this.currency + ' ' + this.amount).fontSize(16).fontWeight(FontWeight.Bold).layoutWeight(1).textAlign(TextAlign.End)
        }.width('100%').padding(8)
        Divider()
        Row() {
          Text('支付状态').fontSize(14).fontColor('#999')
          Text(this.status).fontSize(14).fontColor('#FF9800').layoutWeight(1).textAlign(TextAlign.End)
        }.width('100%').padding(8)
      }.width('90%').margin({ top: 32 }).padding(8)
      .borderRadius(12).backgroundColor('#F5F5F5')

      Button('查看我的订单')
        .width('80%').margin({ top: 32 })
        .onClick(() => { router.pushUrl({ url: 'pages/OrderList' }) })
      Button('继续购物')
        .width('80%').margin({ top: 12 }).backgroundColor(Color.Gray)
        .onClick(() => { router.pushUrl({ url: 'pages/Index' }) })
    }.width('100%').height('100%')
  }
}
