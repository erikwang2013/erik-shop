/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import { router } from '@kit.ArkUI';

@Entry
@Component
struct Profile {
  @State isLoggedIn: boolean = false;

  build() {
    Column() {
      Text('我的').fontSize(20).fontWeight(FontWeight.Bold).width('100%').padding(16)

      List() {
        ListItem() {
          Row() {
            Image($r('app.media.avatar_placeholder')).width(64).height(64).borderRadius(32)
            Column() {
              Text(this.isLoggedIn ? '用户名' : '登录/注册').fontSize(18)
              Text('查看个人资料').fontSize(12).fontColor('#999')
            }.alignItems(HorizontalAlign.Start).margin({ left: 12 }).layoutWeight(1)
            Image($r('app.media.arrow_right')).width(24).height(24)
          }.width('100%').padding(16)
        }

        ListItem() { this.menuItem('我的订单', 'pages/OrderList') }
        ListItem() { this.menuItem('收藏夹', '') }
        ListItem() { this.menuItem('收货地址', '') }
        ListItem() { this.menuItem('礼品卡', '') }
        ListItem() { this.menuItem('语言', '', '中文') }
        ListItem() { this.menuItem('币种', '', 'USD') }
        ListItem() { this.menuItem('隐私设置', '') }
      }
      .layoutWeight(1)
    }
    .width('100%').height('100%')
  }

  @Builder menuItem(title: string, route: string, rightText: string = '') {
    Row() {
      Text(title).fontSize(16)
      Blank()
      if (rightText) {
        Text(rightText).fontSize(12).fontColor('#999').margin({ right: 4 })
      }
      Image($r('app.media.arrow_right')).width(20).height(20)
    }
    .width('100%').padding(16)
    .onClick(() => { if (route) router.pushUrl({ url: route }) })
  }
}
