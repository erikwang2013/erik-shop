import 'dart:io' show Platform;
import 'package:flutter/foundation.dart' show kIsWeb;

class AppConstants {
  /// API 基地址：默认 localhost（桌面/模拟器），Android 模拟器用 10.0.2.2 访问宿主机
  /// 生产环境通过 --dart-define=API_BASE_URL=https://api.example.com 覆盖
  static String get apiBaseUrl {
    const fromEnv = String.fromEnvironment('API_BASE_URL');
    if (fromEnv.isNotEmpty) return fromEnv;
    if (!kIsWeb && Platform.isAndroid) return 'http://10.0.2.2:8787/api';
    return 'http://localhost:8787/api';
  }

  static const String apiVersion = '2026-05-20';
  static const String appName = 'Erik Shop';
  static const int pageSize = 20;
}
