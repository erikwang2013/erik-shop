import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AuthService {
  static final AuthService instance = AuthService._();
  final _storage = const FlutterSecureStorage();

  String? _accessToken;
  String? _refreshToken;
  bool _isLoggedIn = false;

  AuthService._();

  bool get isLoggedIn => _isLoggedIn;
  String? get accessToken => _accessToken;

  Future<void> init() async {
    _accessToken = await _storage.read(key: 'access_token');
    _refreshToken = await _storage.read(key: 'refresh_token');
    _isLoggedIn = _accessToken != null;
  }

  Future<void> saveTokens(String accessToken, String refreshToken) async {
    _accessToken = accessToken;
    _refreshToken = refreshToken;
    _isLoggedIn = true;
    await _storage.write(key: 'access_token', value: accessToken);
    await _storage.write(key: 'refresh_token', value: refreshToken);
  }

  Future<void> logout() async {
    _accessToken = null;
    _refreshToken = null;
    _isLoggedIn = false;
    await _storage.delete(key: 'access_token');
    await _storage.delete(key: 'refresh_token');
  }

  Future<bool> refreshToken() async {
    if (_refreshToken == null) return false;
    // Token refresh handled by ApiClient interceptor
    return true;
  }
}
