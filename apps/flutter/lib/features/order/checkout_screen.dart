import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/api/api_client.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});
  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _submitting = false;

  Future<void> _placeOrder() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);

    final res = await ApiClient.instance.post('/orders', data: {
      'currency_code': 'USD',
    });

    if (mounted) {
      setState(() => _submitting = false);
      if (res.isSuccess) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Order placed!')));
        context.go('/orders');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.msg)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const Text('Shipping Method', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            ...['Standard (7-15 days)', 'Express (3-5 days)'].map((m) => RadioListTile<String>(
              title: Text(m), value: m, groupValue: 'Standard (7-15 days)', onChanged: (_) {},
            )),
            const Divider(),
            const Text('Payment Method', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            ...['Credit Card', 'PayPal'].map((m) => RadioListTile<String>(
              title: Text(m), value: m, groupValue: 'Credit Card', onChanged: (_) {},
            )),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _submitting ? null : _placeOrder,
              child: _submitting ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Place Order'),
            ),
          ],
        ),
      ),
    );
  }
}
