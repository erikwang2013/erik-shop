import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

const _localeKey = 'app_locale';
const _currencyKey = 'app_currency';

const supportedLocales = [
  Locale('zh', 'CN'), Locale('zh', 'HK'), Locale('en'), Locale('ja'), Locale('ko'),
];

const localeLabels = <String, String>{
  'zh_CN': '简体中文', 'zh_HK': '繁體中文', 'en': 'English', 'ja': '日本語', 'ko': '한국어',
};

const supportedCurrencies = ['USD', 'EUR', 'GBP', 'JPY', 'KRW'];

const currencyLabels = <String, String>{
  'USD': 'USD - US Dollar', 'EUR': 'EUR - Euro', 'GBP': 'GBP - British Pound',
  'JPY': 'JPY - Japanese Yen', 'KRW': 'KRW - South Korean Won',
};

Locale _parseLocale(String str) {
  final parts = str.split('_');
  return parts.length == 2 ? Locale(parts[0], parts[1]) : Locale(parts[0]);
}

String localeToString(Locale locale) {
  return locale.countryCode != null ? '${locale.languageCode}_${locale.countryCode}' : locale.languageCode;
}

class LocaleNotifier extends StateNotifier<Locale> {
  LocaleNotifier() : super(const Locale('en')) { _load(); }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(_localeKey);
    if (saved != null) state = _parseLocale(saved);
  }

  Future<void> setLocale(Locale locale) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_localeKey, localeToString(locale));
    state = locale;
  }
}

class CurrencyNotifier extends StateNotifier<String> {
  CurrencyNotifier() : super('USD') { _load(); }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(_currencyKey);
    if (saved != null && supportedCurrencies.contains(saved)) state = saved;
  }

  Future<void> setCurrency(String code) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_currencyKey, code);
    state = code;
  }
}

final localeProvider = StateNotifierProvider<LocaleNotifier, Locale>((ref) => LocaleNotifier());
final currencyProvider = StateNotifierProvider<CurrencyNotifier, String>((ref) => CurrencyNotifier());
