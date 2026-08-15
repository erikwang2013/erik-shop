/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import { promptAction, router } from '@kit.ArkUI';
import { ApiClient, ApiResponse } from '../common/api/ApiClient';

/**
 * 结算页：地址选择 + 人机验证 + 真实计费下单 + 支付发起
 * 后端契约（实测）：POST /api/orders {address_id, currency_code, weight_grams} + header X-Poster-Token
 */
@Entry
@Component
struct Checkout {
  @State cartItems: Array<Record<string, Object>> = [];
  @State shippingOptions: Array<Record<string, Object>> = [];
  @State paymentMethods: Array<Record<string, Object>> = [];
  @State addresses: Array<Record<string, Object>> = [];
  @State selectedShipping: number = 0;
  @State selectedPayment: string = 'card';
  @State selectedAddressIdx: number = 0;
  @State total: number = 0;
  @State shippingFee: number = 0;
  @State posterQuestion: string = '';
  @State posterToken: string = '';
  @State posterAnswer: string = '';
  @State posterVerified: boolean = false;
  private api: ApiClient = ApiClient.getInstance();

  aboutToAppear() { this.loadData(); }

  async loadData() {
    const cartRes = await this.api.get('/cart');
    if (cartRes.code === 0) {
      this.cartItems = (cartRes.data as Array<Record<string, Object>>) ?? [];
      this.total = this.cartItems.reduce((sum, item) =>
        sum + ((item['price'] as number) ?? 0) * ((item['quantity'] as number) ?? 1), 0);
    }

    // 地址列表（下单必填 address_id）
    const addrRes = await this.api.get('/user/addresses');
    if (addrRes.code === 0) {
      this.addresses = (addrRes.data as Array<Record<string, Object>>) ?? [];
      if (this.addresses.length > 0) {
        this.refreshShipping();
      }
    }

    const payRes = await this.api.get('/payment/methods', { country: 'US', currency: 'USD' });
    if (payRes.code === 0) {
      this.paymentMethods = (payRes.data as Array<Record<string, Object>>) ?? [];
    }

    // 人机验证题目（下单受 PosterVerify 保护）
    const posterRes = await this.api.get('/poster/challenge');
    if (posterRes.code === 0) {
      const p = posterRes.data as Record<string, Object>;
      this.posterQuestion = (p['question'] as string) ?? '';
      this.posterToken = (p['token'] as string) ?? '';
    }
  }

  refreshShipping() {
    if (this.addresses.length === 0) {
      return;
    }
    const addr = this.addresses[this.selectedAddressIdx];
    const countryId = (addr['country_id'] as string) ?? '';
    const self = this;
    this.api.get('/shipping/calculate', { dest_country_id: countryId, weight: 500 }).then((shipRes: ApiResponse) => {
      if (shipRes.code === 0) {
        const data = shipRes.data as Record<string, Object>;
        self.shippingOptions = (data['options'] as Array<Record<string, Object>>) ?? [];
        if (self.shippingOptions.length > 0) {
          self.shippingFee = (self.shippingOptions[0]['fee'] as number) ?? 0;
        }
      }
    });
  }

  selectPrevAddress() {
    if (this.addresses.length === 0) {
      return;
    }
    this.selectedAddressIdx = (this.selectedAddressIdx - 1 + this.addresses.length) % this.addresses.length;
    this.refreshShipping();
  }

  selectNextAddress() {
    if (this.addresses.length === 0) {
      return;
    }
    this.selectedAddressIdx = (this.selectedAddressIdx + 1) % this.addresses.length;
    this.refreshShipping();
  }

  async verifyPoster() {
    if (this.posterToken === '' || this.posterAnswer.trim() === '') {
      promptAction.showToast({ message: '请输入验证答案' });
      return;
    }
    const res = await this.api.post('/poster/verify', { token: this.posterToken, answer: this.posterAnswer.trim() });
    if (res.code === 0) {
      const d = res.data as Record<string, Object>;
      this.posterToken = (d['token'] as string) ?? '';
      this.posterVerified = true;
      promptAction.showToast({ message: '人机验证通过' });
    } else {
      promptAction.showToast({ message: res.msg });
      // 刷新新题目
      const posterRes = await this.api.get('/poster/challenge');
      if (posterRes.code === 0) {
        const p = posterRes.data as Record<string, Object>;
        this.posterQuestion = (p['question'] as string) ?? '';
        this.posterToken = (p['token'] as string) ?? '';
        this.posterAnswer = '';
      }
    }
  }

  async placeOrder() {
    if (this.addresses.length === 0) {
      promptAction.showToast({ message: '请先在个人中心添加收货地址' });
      return;
    }
    if (!this.posterVerified || this.posterToken === '') {
      promptAction.showToast({ message: '请先完成人机验证' });
      return;
    }
    const addr = this.addresses[this.selectedAddressIdx];
    const res = await this.api.post('/orders', {
      address_id: (addr['id'] as string) ?? '',
      currency_code: 'USD',
      weight_grams: 500,
    }, { 'X-Poster-Token': this.posterToken });

    if (res.code === 0) {
      const d = res.data as Record<string, Object>;
      const orderId = (d['order_id'] as string) ?? '';
      promptAction.showToast({ message: '下单成功' });
      // 发起支付（真实 Stripe SDK 支付需后续集成，此处先创建支付单）
      const payRes = await this.api.post('/payment/create', { order_id: orderId, gateway: 'stripe' });
      if (payRes.code === 0) {
        promptAction.showToast({ message: '支付已创建，请完成支付' });
      } else {
        promptAction.showToast({ message: payRes.msg });
      }
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
          // 收货地址
          Text('收货地址').fontSize(16).fontWeight(FontWeight.Bold).width('100%').padding(12)
          if (this.addresses.length > 0) {
            Row() {
              Button('上一条').fontSize(12).onClick(() => this.selectPrevAddress())
              Column() {
                Text(((this.addresses[this.selectedAddressIdx]['name'] as string) ?? '') + ' ' +
                  ((this.addresses[this.selectedAddressIdx]['phone'] as string) ?? ''))
                  .fontSize(14).maxLines(1)
                Text(((this.addresses[this.selectedAddressIdx]['city'] as string) ?? '') + ' ' +
                  ((this.addresses[this.selectedAddressIdx]['detail'] as string) ?? ''))
                  .fontSize(12).fontColor('#999').maxLines(2)
              }.alignItems(HorizontalAlign.Start).layoutWeight(1).padding({ left: 8, right: 8 })
              Button('下一条').fontSize(12).onClick(() => this.selectNextAddress())
            }.width('100%').padding(8)
          } else {
            Text('暂无收货地址，请先到个人中心添加').fontSize(13).fontColor('#999').padding(12)
          }

          Divider()

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

          Divider()

          // 人机验证（PosterVerify）
          Text('人机验证').fontSize(16).fontWeight(FontWeight.Bold).width('100%').padding(12)
          Row() {
            Text(this.posterQuestion).fontSize(16).fontWeight(FontWeight.Bold).layoutWeight(1)
            TextInput({ placeholder: '输入答案', text: this.posterAnswer })
              .onChange((value: string) => { this.posterAnswer = value; })
              .layoutWeight(1)
            Button(this.posterVerified ? '已通过' : '验证').fontSize(12)
              .enabled(!this.posterVerified)
              .onClick(() => this.verifyPoster())
          }.width('100%').padding(8)
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
