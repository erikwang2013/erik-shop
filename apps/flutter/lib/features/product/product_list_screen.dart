import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:responsive_framework/responsive_framework.dart';
import '../../core/api/api_client.dart';
import '../../data/models/product.dart';
import 'widgets/product_card.dart';

class ProductListScreen extends ConsumerStatefulWidget {
  final String? categoryId;
  final String? keyword;
  const ProductListScreen({super.key, this.categoryId, this.keyword});

  @override
  ConsumerState<ProductListScreen> createState() => _ProductListScreenState();
}

class _ProductListScreenState extends ConsumerState<ProductListScreen> {
  final _searchCtrl = TextEditingController();
  List<Product> _products = [];
  bool _loading = true;
  int _page = 1;
  String _sort = 'default';
  double _priceMin = 0;
  double _priceMax = 5000;

  @override
  void initState() {
    super.initState();
    if (widget.keyword != null) _searchCtrl.text = widget.keyword!;
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final res = await ApiClient.instance.get('/products', params: {
      'page': _page, 'per_page': 20,
      if (widget.categoryId != null) 'category_id': widget.categoryId,
      if (_searchCtrl.text.isNotEmpty) 'keyword': _searchCtrl.text,
      'sort': _sort,
      'min_price': _priceMin.toString(), 'max_price': _priceMax.toString(),
    });
    if (mounted && res.isSuccess && res.data != null) {
      final data = res.data as Map<String, dynamic>;
      setState(() {
        _products = (data['list'] as List).map((e) => Product.fromJson(e)).toList();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDesktop = ResponsiveBreakpoints.of(context).largerThan(TABLET);

    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: _searchCtrl,
          decoration: const InputDecoration(hintText: '搜索商品...', border: InputBorder.none),
          onSubmitted: (_) => _load(),
        ),
        actions: [
          PopupMenuButton<String>(
            onSelected: (v) { _sort = v; _load(); },
            itemBuilder: (_) => [
              const PopupMenuItem(value: 'default', child: Text('默认排序')),
              const PopupMenuItem(value: 'price_asc', child: Text('价格 - 低到高')),
              const PopupMenuItem(value: 'price_desc', child: Text('价格 - 高到低')),
              const PopupMenuItem(value: 'sales', child: Text('销量优先')),
              const PopupMenuItem(value: 'newest', child: Text('最新')),
            ],
          ),
        ],
      ),
      body: Row(
        children: [
          if (isDesktop)
            SizedBox(
              width: 240,
              child: Column(
                children: [
                  const Padding(padding: EdgeInsets.all(12), child: Text('价格范围', style: TextStyle(fontWeight: FontWeight.bold))),
                  RangeSlider(
                    values: RangeValues(_priceMin, _priceMax),
                    min: 0, max: 5000,
                    divisions: 50,
                    labels: RangeLabels('\$${_priceMin.toInt()}', '\$${_priceMax.toInt()}'),
                    onChanged: (v) { _priceMin = v.start; _priceMax = v.end; },
                    onChangeEnd: (_) => _load(),
                  ),
                ],
              ),
            ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _products.isEmpty
                    ? const Center(child: Text('未找到商品'))
                    : GridView.builder(
                        padding: const EdgeInsets.all(12),
                        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: isDesktop ? 3 : 2,
                          childAspectRatio: 0.7, crossAxisSpacing: 10, mainAxisSpacing: 10,
                        ),
                        itemCount: _products.length,
                        itemBuilder: (_, i) => ProductCard(product: _products[i]),
                      ),
          ),
        ],
      ),
    );
  }

  @override
  void dispose() { _searchCtrl.dispose(); super.dispose(); }
}
