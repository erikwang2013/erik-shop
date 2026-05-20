import 'package:flutter/material.dart';

class AppLocalizations {
  final Locale locale;
  AppLocalizations(this.locale);

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const delegate = _AppLocalizationsDelegate();

  String translate(String key) {
    final lang = _localeKey(locale);
    return _strings[lang]?[key] ?? key;
  }

  String _localeKey(Locale loc) {
    final code = loc.countryCode != null ? '${loc.languageCode}_${loc.countryCode}' : loc.languageCode;
    if (_strings.containsKey(code)) return code;
    return loc.languageCode;
  }

  static const _strings = <String, Map<String, String>>{
    'zh_CN': {
      'home': '首页', 'products': '商品', 'cart': '购物车', 'checkout': '结算',
      'orders': '订单', 'profile': '我的', 'login': '登录', 'register': '注册',
      'search': '搜索', 'add_to_cart': '加入购物车', 'buy_now': '立即购买',
      'language': '语言', 'currency': '货币', 'settings': '设置', 'logout': '退出登录',
      'welcome': '欢迎', 'price': '价格', 'quantity': '数量', 'total': '合计',
      'shipping': '配送', 'payment': '支付', 'place_order': '提交订单',
      'cancel': '取消', 'confirm': '确认', 'delete': '删除', 'save': '保存',
      'edit': '编辑', 'default_address': '默认地址', 'no_data': '暂无数据',
      'loading': '加载中', 'error_network': '网络错误',
    },
    'zh_HK': {
      'home': '首頁', 'products': '商品', 'cart': '購物車', 'checkout': '結算',
      'orders': '訂單', 'profile': '我的', 'login': '登入', 'register': '註冊',
      'search': '搜尋', 'add_to_cart': '加入購物車', 'buy_now': '立即購買',
      'language': '語言', 'currency': '貨幣', 'settings': '設定', 'logout': '登出',
      'welcome': '歡迎', 'price': '價格', 'quantity': '數量', 'total': '合計',
      'shipping': '配送', 'payment': '支付', 'place_order': '提交訂單',
      'cancel': '取消', 'confirm': '確認', 'delete': '刪除', 'save': '儲存',
      'edit': '編輯', 'default_address': '預設地址', 'no_data': '暫無數據',
      'loading': '載入中', 'error_network': '網絡錯誤',
    },
    'en': {
      'home': 'Home', 'products': 'Products', 'cart': 'Cart', 'checkout': 'Checkout',
      'orders': 'Orders', 'profile': 'Profile', 'login': 'Login', 'register': 'Register',
      'search': 'Search', 'add_to_cart': 'Add to Cart', 'buy_now': 'Buy Now',
      'language': 'Language', 'currency': 'Currency', 'settings': 'Settings', 'logout': 'Logout',
      'welcome': 'Welcome', 'price': 'Price', 'quantity': 'Quantity', 'total': 'Total',
      'shipping': 'Shipping', 'payment': 'Payment', 'place_order': 'Place Order',
      'cancel': 'Cancel', 'confirm': 'Confirm', 'delete': 'Delete', 'save': 'Save',
      'edit': 'Edit', 'default_address': 'Default Address', 'no_data': 'No Data',
      'loading': 'Loading', 'error_network': 'Network Error',
    },
    'ja': {
      'home': 'ホーム', 'products': '商品', 'cart': 'カート', 'checkout': 'チェックアウト',
      'orders': '注文', 'profile': 'プロフィール', 'login': 'ログイン', 'register': '登録',
      'search': '検索', 'add_to_cart': 'カートに入れる', 'buy_now': '今すぐ購入',
      'language': '言語', 'currency': '通貨', 'settings': '設定', 'logout': 'ログアウト',
      'welcome': 'ようこそ', 'price': '価格', 'quantity': '数量', 'total': '合計',
      'shipping': '配送', 'payment': '支払い', 'place_order': '注文を確定',
      'cancel': 'キャンセル', 'confirm': '確認', 'delete': '削除', 'save': '保存',
      'edit': '編集', 'default_address': 'デフォルト住所', 'no_data': 'データなし',
      'loading': '読み込み中', 'error_network': 'ネットワークエラー',
    },
    'ko': {
      'home': '홈', 'products': '상품', 'cart': '장바구니', 'checkout': '결제',
      'orders': '주문', 'profile': '프로필', 'login': '로그인', 'register': '회원가입',
      'search': '검색', 'add_to_cart': '장바구니 담기', 'buy_now': '바로 구매',
      'language': '언어', 'currency': '통화', 'settings': '설정', 'logout': '로그아웃',
      'welcome': '환영합니다', 'price': '가격', 'quantity': '수량', 'total': '합계',
      'shipping': '배송', 'payment': '결제', 'place_order': '주문하기',
      'cancel': '취소', 'confirm': '확인', 'delete': '삭제', 'save': '저장',
      'edit': '편집', 'default_address': '기본 주소', 'no_data': '데이터 없음',
      'loading': '로딩 중', 'error_network': '네트워크 오류',
    },
  };
}

class _AppLocalizationsDelegate extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) => ['en', 'zh', 'ja', 'ko'].contains(locale.languageCode);

  @override
  Future<AppLocalizations> load(Locale locale) async => AppLocalizations(locale);

  @override
  bool shouldReload(covariant LocalizationsDelegate<AppLocalizations> old) => false;
}
