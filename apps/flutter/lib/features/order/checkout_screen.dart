import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/api/api_client.dart';
import '../../core/i18n/locale_provider.dart';
import '../../core/utils/currency_formatter.dart';

class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});
  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  List<Map<String, dynamic>> _cartItems = [];
  List<Map<String, dynamic>> _shippingOptions = [];
  List<Map<String, dynamic>> _paymentMethods = [];
  List<Map<String, dynamic>> _addresses = [];
  String _selectedShipping = '';
  String _selectedPayment = 'card';
  int _selectedAddressIdx = 0;
  double _total = 0;
  double _shippingFee = 0;
  String _posterQuestion = '';
  String _posterToken = '';
  String _posterAnswer = '';
  bool _posterVerified = false;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _loading = true);

    final cartRes = await ApiClient.instance.get('/cart');
    if (cartRes.isSuccess && cartRes.data != null) {
      _cartItems = List<Map<String, dynamic>>.from(cartRes.data as List);
      _total = _cartItems.fold(0, (sum, item) => sum + ((item['price'] as num?)?.toDouble() ?? 0) * ((item['quantity'] as num?)?.toInt() ?? 1));
    }

    // 收货地址（下单必填 address_id）
    final addrRes = await ApiClient.instance.get('/user/addresses');
    if (addrRes.isSuccess && addrRes.data != null) {
      _addresses = List<Map<String, dynamic>>.from(addrRes.data as List);
      if (_addresses.isNotEmpty) {
        _refreshShipping();
      }
    }

    final currency = ref.read(currencyProvider);
    final payRes = await ApiClient.instance.get('/payment/methods', params: {'country': 'US', 'currency': currency});
    if (payRes.isSuccess && payRes.data != null) {
      _paymentMethods = List<Map<String, dynamic>>.from(payRes.data as List);
    }

    // 人机验证题目（下单路由受 PosterVerify 保护）
    final posterRes = await ApiClient.instance.get('/poster/challenge');
    if (posterRes.isSuccess && posterRes.data != null) {
      final p = posterRes.data as Map<String, dynamic>;
      _posterQuestion = p['question'] as String? ?? '';
      _posterToken = p['token'] as String? ?? '';
    }

    if (mounted) setState(() => _loading = false);
  }

  Future<void> _refreshShipping() async {
    if (_addresses.isEmpty) return;
    final countryId = _addresses[_selectedAddressIdx]['country_id'] as String? ?? '';
    final shipRes = await ApiClient.instance
        .get('/shipping/calculate', params: {'dest_country_id': countryId, 'weight': 500});
    if (shipRes.isSuccess && shipRes.data != null && mounted) {
      final d = shipRes.data as Map<String, dynamic>;
      setState(() {
        _shippingOptions = List<Map<String, dynamic>>.from(d['options'] ?? []);
        if (_shippingOptions.isNotEmpty) {
          _selectedShipping = _shippingOptions.first['logistics_code'] ?? '';
          _shippingFee = (_shippingOptions.first['fee'] as num?)?.toDouble() ?? 0;
        }
      });
    }
  }

  Future<void> _verifyPoster() async {
    if (_posterToken.isEmpty || _posterAnswer.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please enter the captcha answer')));
      return;
    }
    final res = await ApiClient.instance.post('/poster/verify', data: {'token': _posterToken, 'answer': _posterAnswer.trim()});
    if (mounted) {
      if (res.isSuccess && res.data != null) {
        final d = res.data as Map<String, dynamic>;
        setState(() {
          _posterToken = d['token'] as String? ?? '';
          _posterVerified = true;
        });
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Captcha verified')));
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.msg)));
        // 刷新新题目
        final posterRes = await ApiClient.instance.get('/poster/challenge');
        if (posterRes.isSuccess && posterRes.data != null && mounted) {
          final p = posterRes.data as Map<String, dynamic>;
          setState(() {
            _posterQuestion = p['question'] as String? ?? '';
            _posterToken = p['token'] as String? ?? '';
            _posterAnswer = '';
          });
        }
      }
    }
  }

  Future<void> _placeOrder() async {
    if (_addresses.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please add a shipping address first')));
      return;
    }
    if (!_posterVerified || _posterToken.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please complete the captcha first')));
      return;
    }
    setState(() => _loading = true);
    final addressId = _addresses[_selectedAddressIdx]['id'] as String? ?? '';
    final res = await ApiClient.instance.post('/orders',
        data: {
          'address_id': addressId,
          'currency_code': ref.read(currencyProvider),
          'weight_grams': 500,
        },
        headers: {'X-Poster-Token': _posterToken});
    if (mounted) {
      setState(() => _loading = false);
      if (res.isSuccess) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Order placed!')));
        // 发起支付（真实 Stripe SDK 支付需后续集成）
        final orderId = (res.data as Map<String, dynamic>)['order_id'] as String? ?? '';
        final payRes = await ApiClient.instance.post('/payment/create', data: {'order_id': orderId, 'gateway': 'stripe'});
        if (mounted && !payRes.isSuccess) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(payRes.msg)));
        }
        context.go('/orders');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.msg)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final currency = ref.watch(currencyProvider);
    return Scaffold(
    appBar: AppBar(title: const Text('Checkout')),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(padding: const EdgeInsets.all(16), children: [
            const Text('Address', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            if (_addresses.isNotEmpty)
              Row(children: [
                IconButton(
                  icon: const Icon(Icons.chevron_left),
                  onPressed: () {
                    setState(() {
                      _selectedAddressIdx = (_selectedAddressIdx - 1 + _addresses.length) % _addresses.length;
                    });
                    _refreshShipping();
                  },
                ),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('${_addresses[_selectedAddressIdx]['name'] ?? ''} ${_addresses[_selectedAddressIdx]['phone'] ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis),
                    Text('${_addresses[_selectedAddressIdx]['city'] ?? ''} ${_addresses[_selectedAddressIdx]['detail'] ?? ''}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: Colors.grey)),
                  ]),
                ),
                IconButton(
                  icon: const Icon(Icons.chevron_right),
                  onPressed: () {
                    setState(() {
                      _selectedAddressIdx = (_selectedAddressIdx + 1) % _addresses.length;
                    });
                    _refreshShipping();
                  },
                ),
              ])
            else
              const Text('No address. Please add one in Profile.', style: TextStyle(fontSize: 12, color: Colors.grey)),
            const Divider(),
            const Text('Items', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            ..._cartItems.map((item) => ListTile(
              leading: ClipRRect(borderRadius: BorderRadius.circular(4), child: Image.network(item['image'] ?? '', width: 40, height: 40, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const Icon(Icons.image))),
              title: Text(item['title'] ?? '', maxLines: 1, overflow: TextOverflow.ellipsis),
              trailing: Text('${CurrencyFormatter.formatPrice((item['price'] as num?)?.toDouble() ?? 0, currency)} x${item['quantity']}'),
            )),
            const Divider(),
            const Text('Shipping', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            ..._shippingOptions.map((opt) => RadioListTile<String>(
              title: Text('${opt['logistics_name'] ?? ''} (${CurrencyFormatter.formatPrice((opt['fee'] as num?)?.toDouble() ?? 0, currency)})'),
              subtitle: Text(opt['estimated_days'] ?? ''),
              value: opt['logistics_code'] ?? '', groupValue: _selectedShipping,
              onChanged: (v) { setState(() { _selectedShipping = v!; _shippingFee = (opt['fee'] as num?)?.toDouble() ?? 0; }); },
            )),
            const Divider(),
            const Text('Payment', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            ..._paymentMethods.map((m) => RadioListTile<String>(
              title: Text(m['method_name'] ?? m['method_code'] ?? ''),
              value: m['method_code'] ?? '', groupValue: _selectedPayment,
              onChanged: (v) { setState(() => _selectedPayment = v!); },
            )),
            const Divider(),
            const Text('Captcha', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            Row(children: [
              Expanded(child: Text(_posterQuestion, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold))),
              Expanded(
                child: TextField(
                  enabled: !_posterVerified,
                  decoration: const InputDecoration(hintText: 'Answer', isDense: true),
                  onChanged: (v) => _posterAnswer = v,
                ),
              ),
              FilledButton(
                onPressed: _posterVerified ? null : _verifyPoster,
                child: Text(_posterVerified ? 'Verified' : 'Verify'),
              ),
            ]),
            const SizedBox(height: 16),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Subtotal: ${CurrencyFormatter.formatPrice(_total, currency)}', style: const TextStyle(fontSize: 13)),
                Text('Shipping: ${CurrencyFormatter.formatPrice(_shippingFee, currency)}', style: const TextStyle(fontSize: 13)),
                Text('Total: ${CurrencyFormatter.formatPrice(_total + _shippingFee, currency)}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              ]),
              FilledButton(onPressed: _placeOrder, child: const Text('Place Order')),
            ]),
          ]),
    );
  }
}
