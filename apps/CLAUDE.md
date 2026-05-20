# apps/ — 跨境电商客户端

## Flutter 客户端 (`apps/flutter/`)

支持 iPadOS、macOS、Windows、Linux 的 Flutter 应用。
iPadOS 使用平板布局，macOS/Windows/Linux 使用 PC 风格布局。

### 项目结构
```
apps/flutter/
  lib/
    main.dart                   # 入口，主题，路由
    core/
      api/        api_client.dart, api_urls.dart, api_response.dart
      auth/       auth_service.dart
      constants/  app_constants.dart, app_theme.dart
      utils/      currency_formatter.dart, validators.dart
      widgets/    通用组件
    data/
      models/     数据模型（fromJson/toJson）
      repositories/ 数据仓库层
    features/
      home/       首页
      product/    商品列表/详情
      cart/       购物车
      order/      订单/结算
      profile/    用户中心
      auth/       登录/注册
    routing/      GoRouter 路由
```

### 平台布局
| 平台 | 模式 | 导航 |
|------|------|------|
| macOS/Windows/Linux | PC风格，侧边栏+主从 | 持久侧边Rail |
| iPadOS | 平板自适应，Split View | 顶部Tab |

### 国际化
- ARB 文件：zh_CN、zh_HK、en、ja、ko
- API 携带 `Accept-Language` + `API-Version` header

### 技术栈
flutter_riverpod / go_router / dio / responsive_framework / cached_network_image / flutter_secure_storage / fl_chart / window_manager / google_sign_in / sign_in_with_apple

---

## 鸿蒙客户端 (`apps/harmonyos/`)

基于 HarmonyOS NEXT (API 12+) 的 ArkTS + ArkUI 客户端。

### 项目结构
```
apps/harmonyos/
  entry/
    src/main/
      ets/
        entryability/   EntryAbility.ets
        pages/          页面
          Index.ets, ProductList.ets, ProductDetail.ets,
          Cart.ets, OrderList.ets, OrderDetail.ets,
          Checkout.ets, Profile.ets, Login.ets
        common/         公共
          api/           ApiClient.ets, ApiResponse.ets
          utils/         CurrencyFormatter.ets, Validators.ets
          components/    ProductCard.ets, LoadingView.ets
        model/           Product.ets, Order.ets, User.ets, CartItem.ets
        store/           AppState.ets, UserStore.ets, CartStore.ets
        i18n/            strings_en.json, strings_zh.json
      resources/        图片/资源
  oh-package.json5
  build-profile.json5
```

### 技术栈
- ArkTS + ArkUI (声明式UI)
- @ohos.net.http (HTTP)
- @ohos.data.preferences (本地存储)
- @ohos.data.rdb (SQLite)
- @ohos.i18n (国际化)
- @ohos.geoLocationManager (GeoIP定位)

### 命令
```bash
hvigorw assembleHap        # 编译
hvigorw --mode module -p product default assembleHap  # 发布
```
