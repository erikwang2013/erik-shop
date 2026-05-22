---
name: flutter-pc-layout
description: Use when building Flutter UI for the shop-php cross-border e-commerce app — applies PC-style adaptive layout (sidebar + master-detail) for desktop platforms and tab-based layout for tablets
---

# Flutter PC 风格自适应布局

## Overview

一套 Flutter 应用同时支持平板和桌面。使用 `LayoutBuilder` + `responsive_framework` 实现自适应，桌面端（macOS/Windows/Linux）使用侧边导航+主从布局，平板端（iPadOS）使用顶部 Tab 导航+自适应网格。

## When to Use

- 创建新的页面/功能
- 调整布局适配不同平台
- 修改导航结构

## 布局策略

```
                    ┌─────────────┬──────────────────────────┐
                    │             │                          │
Desktop             │  Sidebar    │    Content Area          │
(macOS/Win/Linux)   │  200px      │    (Master-Detail)       │
                    │             │                          │
                    ├─────────────┴──────────────────────────┤
                    │  Tab Bar                                │
Tablet (iPadOS)     ├────────────────────────────────────────┤
                    │  Content (Split View support)           │
                    │                                         │
                    └──────────────────────────────────────────┘
```

## Core Pattern

### App Shell（自适应根布局）

```dart
class AppShell extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return ResponsiveBreakpoints.of(context).largerThan(TABLET)
        ? DesktopLayout()   // 侧边栏 + 主内容
        : TabletLayout();   // Tab 导航 + 内容
  }
}
```

### DesktopLayout

```dart
class DesktopLayout extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        SizedBox(
          width: 200,
          child: NavigationRail(           // 持久侧边栏
            selectedIndex: _currentIndex,
            onDestinationSelected: _onNav,
            destinations: [
              NavigationRailDestination(icon: Icon(Icons.home), label: Text('首页')),
              NavigationRailDestination(icon: Icon(Icons.shopping_bag), label: Text('商品')),
              NavigationRailDestination(icon: Icon(Icons.shopping_cart), label: Text('购物车')),
              NavigationRailDestination(icon: Icon(Icons.person), label: Text('我的')),
            ],
          ),
        ),
        VerticalDivider(width: 1),
        Expanded(
          child: _buildContent(),          // Master-Detail 区域
        ),
      ],
    );
  }
}
```

### 响应式网格

```dart
class ProductGrid extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final crossAxisCount = ResponsiveBreakpoints.of(context)
        .largerThan(DESKTOP) ? 5 :
        ResponsiveBreakpoints.of(context).largerThan(TABLET) ? 3 : 2;

    return GridView.builder(
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: crossAxisCount,
        childAspectRatio: 0.75,
        crossAxisSpacing: 12,
        mainAxisSpacing: 12,
      ),
      itemBuilder: (context, index) => ProductCard(products[index]),
    );
  }
}
```

## 断点定义

| 断点 | 最小宽度 | 平台 |
|------|---------|------|
| MOBILE | 0 | (未使用，本项目不包含手机端) |
| TABLET | 600 | iPadOS |
| DESKTOP | 1024 | macOS, Windows, Linux |

## 页面模板

```dart
class ProductListScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final isDesktop = ResponsiveBreakpoints.of(context).largerThan(TABLET);

    return Scaffold(
      appBar: isDesktop ? null : AppBar(title: Text('商品列表')),
      body: isDesktop
          ? Row(                              // Master-Detail
              children: [
                Expanded(flex: 2, child: ProductList()),
                VerticalDivider(),
                Expanded(flex: 3, child: ProductDetail()),
              ],
            )
          : ProductList(),                   // 单页
    );
  }
}
```

## 窗口约束

```dart
// main.dart
void main() {
  WidgetsFlutterBinding.ensureInitialized();

  // 桌面端最小窗口
  if (Platform.isWindows || Platform.isMacOS || Platform.isLinux) {
    appWindow.minSize = const Size(1024, 768);
    appWindow.size = const Size(1280, 900);
  }

  runApp(const ShopApp());
}
```

## Common Mistakes

- **硬编码尺寸**：使用 `MediaQuery` 或 `LayoutBuilder` 而非固定像素
- **忘记 window_manager**：桌面端需要 `window_manager` 包设置最小尺寸
- **平板用侧边栏**：iPadOS 应用 Apple HIG，使用 Tab 导航而非侧边栏
- **Split View 不支持**：iPadOS 需处理分屏模式下的布局变化
