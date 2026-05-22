---
name: flutter-api-integration
description: Use when connecting Flutter features to the shop-php service API — covers Dio HTTP client with JWT interceptor, API-Version header, hashids-aware serialization, Riverpod repository pattern, i18n Accept-Language header, and error handling
---

# Flutter API 集成

## Overview

Flutter 通过 Dio HTTP 客户端连接 service API。拦截器自动处理 JWT token、API 版本路由、hashids ID 转换、国际化 header、统一错误处理。

## When to Use

- 创建新的数据仓库 (Repository)
- 添加 API 调用
- 处理认证和 token 刷新
- 实现国际化 API 请求

## Core Pattern

### API 客户端（Dio + 拦截器）

```dart
class ApiClient {
  late final Dio _dio;

  ApiClient() {
    _dio = Dio(BaseOptions(
      baseUrl: AppConstants.apiBaseUrl,    // http://localhost:8787/api（不含版本号）
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 10),
      headers: {
        'Accept': 'application/json',
        'API-Version': '2026-05-20',        // API 版本在 header 中
      },
    ));

    _dio.interceptors.addAll([
      VersionInterceptor(),   // API-Version header（最先执行）
      AuthInterceptor(),      // JWT token 注入 + 自动刷新
      LocaleInterceptor(),    // Accept-Language header
      ErrorInterceptor(),     // 统一错误处理
      LogInterceptor(),       // 开发调试（release 移除）
    ]);
  }

  Future<ApiResponse> get(String path, {Map<String, dynamic>? params}) async {
    final response = await _dio.get(path, queryParameters: params);
    return ApiResponse.fromJson(response.data);
  }

  Future<ApiResponse> post(String path, {dynamic data}) async {
    final response = await _dio.post(path, data: data);
    return ApiResponse.fromJson(response.data);
  }
}
```

### 版本拦截器

```dart
class VersionInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    // API 版本通过 header 传递，不在 URL 中
    options.headers['API-Version'] = AppConstants.apiVersion;  // '2026-05-20'
    handler.next(options);
  }
}
```

### JWT 拦截器

```dart
class AuthInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    final token = AuthService.instance.accessToken;
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      final refreshed = await AuthService.instance.refreshToken();
      if (refreshed) {
        final response = await _retry(err.requestOptions);
        return handler.resolve(response);
      }
      AuthService.instance.logout();
    }
    handler.next(err);
  }
}
```

### 国际化拦截器

```dart
class LocaleInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    final locale = I18nService.instance.currentLocale;
    options.headers['Accept-Language'] = locale.toString();  // zh_CN, en, ja, ko
    handler.next(options);
  }
}
```

### API 响应模型

```dart
class ApiResponse<T> {
  final int code;
  final String msg;
  final T? data;

  bool get isSuccess => code == 0;

  factory ApiResponse.fromJson(Map<String, dynamic> json) {
    return ApiResponse(
      code: json['code'] ?? -1,
      msg: json['msg'] ?? 'Unknown error',
      data: json['data'],
    );
  }
}

class PaginatedData<T> {
  final List<T> list;
  final int total;
  final int page;
  final int perPage;

  bool get hasMore => page * perPage < total;
}
```

## Repository 模式

```dart
class ProductRepository {
  final ApiClient _api;

  ProductRepository(this._api);

  Future<PaginatedData<Product>> getProducts({
    int page = 1,
    int perPage = 20,
    int? categoryId,
    String? keyword,
    String? sort,
  }) async {
    // URL 不含版本号：/api/products
    final response = await _api.get('/products', params: {
      'page': page,
      'per_page': perPage,
      if (categoryId != null) 'category_id': categoryId,
      if (keyword != null) 'keyword': keyword,
      if (sort != null) 'sort': sort,
    });

    final data = response.data as Map<String, dynamic>;
    final items = (data['list'] as List)
        .map((json) => Product.fromJson(json))
        .toList();

    return PaginatedData(
      list: items,
      total: data['total'],
      page: data['page'],
      perPage: data['per_page'],
    );
  }
}
```

## Riverpod Provider

```dart
final productRepositoryProvider = Provider<ProductRepository>((ref) {
  return ProductRepository(ref.read(apiClientProvider));
});

final productListProvider = FutureProvider.family<PaginatedData<Product>, ProductListParams>((ref, params) async {
  final repo = ref.read(productRepositoryProvider);
  return repo.getProducts(
    page: params.page,
    perPage: params.perPage,
    categoryId: params.categoryId,
    keyword: params.keyword,
    sort: params.sort,
  );
});
```

## 数据模型

```dart
class Product {
  final String id;            // hashids 字符串，直接用于 UI 展示和后续请求
  final String title;
  final String? description;
  final String? mainImage;
  final double minPrice;
  final double maxPrice;
  final int status;
  final bool isHot;
  final bool isNew;

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: json['id'] as String,       // hashids 已由 service 端编码
      title: json['title'] ?? '',
      description: json['description'],
      mainImage: json['main_image'],
      minPrice: (json['min_price'] as num?)?.toDouble() ?? 0,
      maxPrice: (json['max_price'] as num?)?.toDouble() ?? 0,
      status: json['status'] ?? 0,
      isHot: json['is_hot'] ?? false,
      isNew: json['is_new'] ?? false,
    );
  }
}
```

## 错误处理

```dart
class ErrorInterceptor extends Interceptor {
  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final msg = switch (err.type) {
      DioExceptionType.connectionTimeout => '连接超时',
      DioExceptionType.receiveTimeout => '响应超时',
      DioExceptionType.connectionError => '网络连接失败',
      _ => err.response?.data?['msg'] ?? '未知错误',
    };
    handler.next(DioException(
      requestOptions: err.requestOptions,
      message: msg,
    ));
  }
}
```

## Common Mistakes

- **手动编码/解码 hashids**：service 端中间件自动处理，客户端只需正常传参
- **版本号放 URL**：版本通过 `API-Version` header 传递，不在路径中
- **Token 存储用 shared_preferences**：token 是敏感数据，必须用 flutter_secure_storage
- **忘记 Accept-Language**：API 消息语言由 header 控制，遗漏会导致始终返回中文
- **忘记 API-Version**：必须携带版本 header，否则可能路由到错误版本
