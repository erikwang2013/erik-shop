import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../constants/app_constants.dart';
import 'api_response.dart';

class ApiClient {
  static final ApiClient instance = ApiClient._();
  late final Dio dio;
  final _storage = const FlutterSecureStorage();

  ApiClient._() {
    dio = Dio(BaseOptions(
      baseUrl: AppConstants.apiBaseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 10),
      headers: {
        'Accept': 'application/json',
        'API-Version': AppConstants.apiVersion,
      },
    ));

    dio.interceptors.addAll([
      _AuthInterceptor(),
      _LocaleInterceptor(),
      LogInterceptor(requestBody: true, responseBody: true),
    ]);
  }

  Future<ApiResponse<T>> get<T>(String path, {Map<String, dynamic>? params}) async {
    final res = await dio.get(path, queryParameters: params);
    return ApiResponse.fromJson<T>(res.data);
  }

  Future<ApiResponse<T>> post<T>(String path, {dynamic data}) async {
    final res = await dio.post(path, data: data);
    return ApiResponse.fromJson<T>(res.data);
  }

  Future<ApiResponse<T>> put<T>(String path, {dynamic data}) async {
    final res = await dio.put(path, data: data);
    return ApiResponse.fromJson<T>(res.data);
  }

  Future<ApiResponse<T>> delete<T>(String path) async {
    final res = await dio.delete(path);
    return ApiResponse.fromJson<T>(res.data);
  }
}

class _AuthInterceptor extends Interceptor {
  final _storage = const FlutterSecureStorage();

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await _storage.read(key: 'access_token');
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      final refreshToken = await _storage.read(key: 'refresh_token');
      if (refreshToken != null) {
        try {
          final dio = Dio(BaseOptions(baseUrl: AppConstants.apiBaseUrl));
          final res = await dio.post('/auth/refresh', data: {'refresh_token': refreshToken});
          if (res.data['code'] == 0) {
            await _storage.write(key: 'access_token', value: res.data['data']['access_token']);
            err.requestOptions.headers['Authorization'] = 'Bearer ${res.data['data']['access_token']}';
            final retry = await Dio().fetch(err.requestOptions);
            return handler.resolve(retry);
          }
        } catch (_) {}
      }
    }
    handler.next(err);
  }
}

class _LocaleInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final prefs = await SharedPreferences.getInstance();
    final localeStr = prefs.getString('app_locale') ?? 'en';
    options.headers['Accept-Language'] = localeStr.split('_').first;
    options.headers['API-Version'] = prefs.getString('api_version') ?? AppConstants.apiVersion;
    handler.next(options);
  }
}
