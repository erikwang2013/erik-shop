class CurrencyFormatter {
  static const _symbols = <String, String>{
    'USD': '\$', 'EUR': '€', 'GBP': '£', 'JPY': '¥', 'KRW': '₩',
  };

  static String formatPrice(double amount, String currencyCode) {
    final symbol = _symbols[currencyCode] ?? currencyCode;
    if (currencyCode == 'JPY' || currencyCode == 'KRW') {
      return '$symbol ${amount.toStringAsFixed(0)}';
    }
    return '$symbol${amount.toStringAsFixed(2)}';
  }
}
