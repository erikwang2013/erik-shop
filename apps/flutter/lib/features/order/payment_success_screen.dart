import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// 支付完成页：下单支付成功后展示订单号/金额/状态，可返回订单列表或继续购物
class PaymentSuccessScreen extends StatelessWidget {
  final String orderNo;
  final String amount;
  final String currency;
  final String status;

  const PaymentSuccessScreen({
    super.key,
    required this.orderNo,
    required this.amount,
    required this.currency,
    this.status = 'Pending payment',
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Payment')),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.check_circle, color: Colors.green, size: 72),
              const SizedBox(height: 16),
              Text('Order placed', style: theme.textTheme.titleLarge),
              const SizedBox(height: 4),
              Text('Please complete the payment soon', style: TextStyle(fontSize: 13, color: Colors.grey[600])),
              const SizedBox(height: 32),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.grey.shade100,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(children: [
                  _row(context, 'Order No.', orderNo),
                  const Divider(height: 20),
                  _row(context, 'Amount', '$currency $amount', bold: true),
                  const Divider(height: 20),
                  _row(context, 'Status', status, color: Colors.orange),
                ]),
              ),
              const SizedBox(height: 32),
              FilledButton(
                onPressed: () => context.go('/orders'),
                style: FilledButton.styleFrom(minimumSize: const Size(240, 44)),
                child: const Text('View My Orders'),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () => context.go('/'),
                style: OutlinedButton.styleFrom(minimumSize: const Size(240, 44)),
                child: const Text('Continue Shopping'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _row(BuildContext context, String label, String value, {bool bold = false, Color? color}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: TextStyle(fontSize: 14, color: Colors.grey[600])),
        Text(value,
            style: TextStyle(
                fontSize: bold ? 16 : 14, fontWeight: bold ? FontWeight.bold : FontWeight.normal, color: color)),
      ],
    );
  }
}
