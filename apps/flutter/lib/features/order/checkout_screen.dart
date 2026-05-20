import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/api/api_client.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});
  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  List<Map<String, dynamic>> _cartItems = [];
  List<Map<String, dynamic>> _shippingOptions = [];
  List<Map<String, dynamic>> _paymentMethods = [];
  String _selectedShipping = '';
  String _selectedPayment = 'card';
  double _total = 0;
  double _shippingFee = 0;
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

    final shipRes = await ApiClient.instance.get('/shipping/calculate', params: {'dest_country_id': 1, 'weight': 500});
    if (shipRes.isSuccess && shipRes.data != null) {
      final d = shipRes.data as Map<String, dynamic>;
      _shippingOptions = List<Map<String, dynamic>>.from(d['options'] ?? []);
      if (_shippingOptions.isNotEmpty) {
        _selectedShipping = _shippingOptions.first['logistics_code'] ?? '';
        _shippingFee = (_shippingOptions.first['fee'] as num?)?.toDouble() ?? 0;
      }
    }

    final payRes = await ApiClient.instance.get('/payment/methods', params: {'country': 'US', 'currency': 'USD'});
    if (payRes.isSuccess && payRes.data != null) {
      _paymentMethods = List<Map<String, dynamic>>.from(payRes.data as List);
    }

    if (mounted) setState(() => _loading = false);
  }

  Future<void> _placeOrder() async {
    setState(() => _loading = true);
    final res = await ApiClient.instance.post('/orders', data: {'currency_code': 'USD'});
    if (mounted) {
      setState(() => _loading = false);
      if (res.isSuccess) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Order placed!')));
        context.go('/orders');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.msg)));
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Checkout')),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(padding: const EdgeInsets.all(16), children: [
            const Text('Items', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            ..._cartItems.map((item) => ListTile(
              leading: ClipRRect(borderRadius: BorderRadius.circular(4), child: Image.network(item['image'] ?? '', width: 40, height: 40, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const Icon(Icons.image))),
              title: Text(item['title'] ?? '', maxLines: 1, overflow: TextOverflow.ellipsis),
              trailing: Text('\$${((item['price'] as num?)?.toDouble() ?? 0).toStringAsFixed(2)} x${item['quantity']}'),
            )),
            const Divider(),
            const Text('Shipping', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            ..._shippingOptions.map((opt) => RadioListTile<String>(
              title: Text('${opt['logistics_name'] ?? ''} (\$${((opt['fee'] as num?)?.toDouble() ?? 0).toStringAsFixed(2)})'),
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
            const SizedBox(height: 16),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Subtotal: \$${_total.toStringAsFixed(2)}', style: const TextStyle(fontSize: 13)),
                Text('Shipping: \$${_shippingFee.toStringAsFixed(2)}', style: const TextStyle(fontSize: 13)),
                Text('Total: \$${(_total + _shippingFee).toStringAsFixed(2)}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              ]),
              FilledButton(onPressed: _placeOrder, child: const Text('Place Order')),
            ]),
          ]),
  );
}
