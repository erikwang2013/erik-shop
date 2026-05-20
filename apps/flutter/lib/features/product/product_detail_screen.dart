import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../core/api/api_client.dart';
import '../../data/models/product.dart';

class ProductDetailScreen extends StatefulWidget {
  final String productId;
  const ProductDetailScreen({super.key, required this.productId});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  Product? _product;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final res = await ApiClient.instance.get('/products/${widget.productId}');
    if (mounted) {
      if (res.isSuccess && res.data != null) {
        setState(() { _product = Product.fromJson(res.data as Map<String, dynamic>); _loading = false; });
      } else {
        setState(() { _error = res.msg; _loading = false; });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Scaffold(body: Center(child: CircularProgressIndicator()));
    if (_product == null) return Scaffold(appBar: AppBar(), body: Center(child: Text(_error ?? 'Not found')));

    final p = _product!;
    return Scaffold(
      appBar: AppBar(title: Text(p.title)),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (p.mainImage != null)
              AspectRatio(aspectRatio: 1, child: CachedNetworkImage(imageUrl: p.mainImage!, fit: BoxFit.cover)),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(p.title, style: Theme.of(context).textTheme.headlineSmall),
                  if (p.brand != null) Text('Brand: ${p.brand}', style: TextStyle(color: Colors.grey[600])),
                  const SizedBox(height: 12),
                  Text('\$${p.minPrice.toStringAsFixed(2)}', style: Theme.of(context).textTheme.headlineMedium?.copyWith(color: Theme.of(context).colorScheme.primary)),
                  if (p.displayPrice != null)
                    Text('VAT included: \$${(p.displayPrice!['tax_inclusive'] as num?)?.toStringAsFixed(2) ?? '-'}'),
                  const SizedBox(height: 12),
                  if (p.description != null) Text(p.description!, style: const TextStyle(fontSize: 14, height: 1.5)),
                  const SizedBox(height: 12),
                  if (p.skus != null)
                    Wrap(
                      spacing: 8,
                      children: p.skus!.map((sku) => ChoiceChip(
                        label: Text(sku.attrs?.values.join(' / ') ?? sku.skuCode ?? ''),
                        selected: false,
                        onSelected: (_) {},
                      )).toList(),
                    ),
                  const SizedBox(height: 24),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: () async {
                        await ApiClient.instance.post('/cart', data: {'sku_id': p.skus?.first.id, 'quantity': 1});
                        if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Added to cart')));
                      },
                      icon: const Icon(Icons.shopping_cart),
                      label: const Text('Add to Cart'),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
