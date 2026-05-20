import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';
import '../../data/models/order.dart';

class OrderListScreen extends StatefulWidget {
  const OrderListScreen({super.key});
  @override
  State<OrderListScreen> createState() => _OrderListScreenState();
}

class _OrderListScreenState extends State<OrderListScreen> {
  List<Order> _orders = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final res = await ApiClient.instance.get('/orders');
    if (mounted && res.isSuccess && res.data != null) {
      final data = res.data as Map<String, dynamic>;
      setState(() {
        _orders = (data['list'] as List).map((e) => Order.fromJson(e as Map<String, dynamic>)).toList();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Orders')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _orders.isEmpty
              ? const Center(child: Text('No orders yet'))
              : ListView.builder(
                  itemCount: _orders.length,
                  itemBuilder: (_, i) {
                    final o = _orders[i];
                    return ListTile(
                      title: Text('#${o.orderNo}'),
                      subtitle: Text('${o.createdAt ?? ""}  |  ${o.statusText ?? ""}'),
                      trailing: Text('\$${o.payAmount.toStringAsFixed(2)} ${o.currencyCode}', style: TextStyle(fontWeight: FontWeight.bold, color: Theme.of(context).colorScheme.primary)),
                    );
                  },
                ),
    );
  }
}
