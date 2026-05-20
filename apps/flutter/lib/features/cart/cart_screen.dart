import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/api/api_client.dart';

class CartScreen extends StatefulWidget {
  const CartScreen({super.key});
  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadCart();
  }

  Future<void> _loadCart() async {
    final res = await ApiClient.instance.get('/cart');
    if (mounted && res.isSuccess && res.data != null) {
      setState(() { _items = List<Map<String, dynamic>>.from(res.data as List); _loading = false; });
    }
  }

  Future<void> _removeItem(String id) async {
    await ApiClient.instance.delete('/cart/$id');
    _loadCart();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Shopping Cart')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _items.isEmpty
              ? const Center(child: Text('Cart is empty'))
              : Column(
                  children: [
                    Expanded(
                      child: ListView.builder(
                        itemCount: _items.length,
                        itemBuilder: (_, i) {
                          final item = _items[i];
                          return ListTile(
                            leading: ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: Image.network(item['image'] ?? '', width: 60, height: 60, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const Icon(Icons.image)),
                            ),
                            title: Text(item['title'] ?? ''),
                            subtitle: Text('\$${(item['price'] as num?)?.toStringAsFixed(2) ?? '0.00'} x ${item['quantity']}'),
                            trailing: IconButton(icon: const Icon(Icons.delete_outline), onPressed: () => _removeItem(item['id'] as String)),
                          );
                        },
                      ),
                    ),
                    SafeArea(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: FilledButton(
                          onPressed: () => context.push('/checkout'),
                          child: const Text('Proceed to Checkout'),
                        ),
                      ),
                    ),
                  ],
                ),
    );
  }
}
