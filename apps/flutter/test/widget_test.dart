// ShopApp 冒烟测试：验证应用可构建并渲染主界面。
// 不依赖真实网络（flutter_test 环境下 HTTP 请求自动返回 400，HomeScreen 已做异常降级）。
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:shop_app/main.dart';

void main() {
  testWidgets('ShopApp 构建并渲染主界面', (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: ShopApp()));
    await tester.pump();

    // 主界面应用栏标题正常显示
    expect(find.text('Erik Shop'), findsOneWidget);

    // 推进时钟，释放 dio 超时等 pending Timer（测试环境 HTTP 被拦截为 400）
    await tester.pump(const Duration(seconds: 12));
  });
}
