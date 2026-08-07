# apps/ — 跨境电商客户端

## Flutter 客户端 (`apps/flutter/`)

支持 iPadOS、macOS、Windows、Linux 的 Flutter 应用。
iPadOS 使用平板布局，macOS/Windows/Linux 使用 PC 风格布局。

### 项目结构（实际）
```
apps/flutter/
  lib/
    main.dart                     # 入口，主题，Riverpod+GoRouter
    core/
      api/          api_client.dart, api_response.dart
      auth/         auth_service.dart (Token安全存储)
      constants/    app_constants.dart, app_theme.dart
      i18n/         app_localizations.dart (5语言硬编码), locale_provider.dart
      utils/        currency_formatter.dart
    data/
      models/       product.dart, order.dart
    features/
      home/         home_screen.dart (PC侧边栏+平板Tab)
      product/      product_detail_screen.dart, product_list_screen.dart
                    widgets/product_card.dart
      cart/         cart_screen.dart
      order/        order_list_screen.dart, checkout_screen.dart
      profile/      profile_screen.dart, address_list_screen.dart
      auth/         login_screen.dart
    routing/        app_router.dart (10条路由)
```

### 平台布局
| 平台 | 模式 | 导航 |
|------|------|------|
| macOS/Windows/Linux | PC风格，侧边Rail+主从 | NavigationRail |
| iPadOS | 平板自适应 | NavigationBar(底部Tab) |

### 国际化
- `app_localizations.dart` 硬编码 5 语言翻译 (zh_CN/zh_HK/en/ja/ko)
- `locale_provider.dart` Riverpod StateNotifier 持久化到 SharedPreferences
- API 拦截器动态注入 `Accept-Language` + `API-Version` header

### 技术栈
flutter_riverpod / go_router / dio / responsive_framework / cached_network_image / flutter_secure_storage / shared_preferences / fl_chart / window_manager

---

## 鸿蒙客户端 (`apps/harmonyos/`)

基于 HarmonyOS NEXT (API 12+) 的 ArkTS + ArkUI 客户端。

### 项目结构（实际）
```
apps/harmonyos/
  entry/
    src/main/
      ets/
        entryability/   EntryAbility.ets (应用入口+AppState初始化)
        pages/          9个页面
          Index.ets, ProductDetail.ets, Cart.ets, OrderList.ets,
          Checkout.ets, Profile.ets, Login.ets, Register.ets, Search.ets
        common/
          api/           ApiClient.ets (HTTP客户端+ApiResponse接口)
          components/    ProductCard.ets (可复用商品卡片)
        store/           AppState.ets (Token/语言/币种/购物车全局状态)
  build-profile.json5
  oh-package.json5
  entry/build-profile.json5
```

### 技术栈
- ArkTS + ArkUI (声明式UI)
- @ohos.net.http (HTTP，封装在ApiClient)
- @ohos.data.preferences (本地存储，封装在AppState)
- @ohos.window (窗口管理)

### 命令
```bash
hvigorw assembleHap        # 编译
hvigorw --mode module -p product default assembleHap  # 发布
```
