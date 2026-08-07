import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';
import '../../data/models/order.dart';

class OrderDetailScreen extends StatefulWidget {
  final String orderId;
  const OrderDetailScreen({super.key, required this.orderId});
  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  Order? _order;
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final res = await ApiClient.instance.get('/orders/${widget.orderId}');
    if (mounted && res.isSuccess && res.data != null) {
      setState(() { _order = Order.fromJson(res.data as Map<String, dynamic>); _loading = false; });
    }
  }

  Future<void> _cancel() async {
    final res = await ApiClient.instance.post('/orders/${widget.orderId}/cancel');
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.isSuccess ? '订单已取消' : res.msg)));
      if (res.isSuccess) _load();
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Order Details')),
    body: _loading ? const Center(child: CircularProgressIndicator()) : _order == null
      ? const Center(child: Text('Not found'))
      : ListView(padding: const EdgeInsets.all(16), children: [
          Text('#${_order!.orderNo}', style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 4),
          Text(_order!.statusText ?? 'Status: ${_order!.status}', style: TextStyle(color: Colors.grey[600])),
          const SizedBox(height: 16),
          ...?_order!.items?.map((item) => ListTile(
            leading: ClipRRect(borderRadius: BorderRadius.circular(4), child: Image.network(item.image ?? '', width: 50, height: 50, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const Icon(Icons.image))),
            title: Text(item.title), subtitle: Text('\$${item.price.toStringAsFixed(2)} x ${item.quantity}'),
            trailing: Text('\$${item.subtotal.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.bold)),
          )),
          const Divider(),
          Row(children: [const Text('Total: '), Text('\$${_order!.payAmount.toStringAsFixed(2)} ${_order!.currencyCode}', style: Theme.of(context).textTheme.titleMedium)]),
          if (_order!.createdAt != null) Text('Created: ${_order!.createdAt}', style: TextStyle(color: Colors.grey[500], fontSize: 12)),
          const SizedBox(height: 16),
          if (_order!.status == 0) FilledButton.tonal(onPressed: _cancel, child: const Text('Cancel Order')),
        ]),
  );
}
