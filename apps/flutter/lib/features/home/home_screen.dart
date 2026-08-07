import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:responsive_framework/responsive_framework.dart';
import '../../core/api/api_client.dart';
import '../../data/models/product.dart';
import '../product/widgets/product_card.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});
  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  List<Product> _hotProducts = [];
  int _selectedNav = 0;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    try {
      final res = await ApiClient.instance.get('/products', params: {'per_page': 12, 'sort': 'sales'});
      if (mounted && res.isSuccess && res.data != null) {
        final data = res.data as Map<String, dynamic>;
        setState(() {
          _hotProducts = (data['list'] as List).map((e) => Product.fromJson(e as Map<String, dynamic>)).toList();
          _loading = false;
        });
      }
    } catch (_) {
      // 网络异常时静默降级为空列表，避免 UI 崩溃
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDesktop = ResponsiveBreakpoints.of(context).largerThan(TABLET);

    return Scaffold(
      body: Row(
        children: [
          if (isDesktop)
            NavigationRail(
              selectedIndex: _selectedNav,
              onDestinationSelected: (i) {
                setState(() => _selectedNav = i);
                if (i == 1) context.push('/products');
                if (i == 2) context.push('/cart');
                if (i == 3) context.push('/profile');
              },
              labelType: NavigationRailLabelType.all,
              destinations: const [
                NavigationRailDestination(icon: Icon(Icons.home), label: Text('Home')),
                NavigationRailDestination(icon: Icon(Icons.shopping_bag), label: Text('Products')),
                NavigationRailDestination(icon: Icon(Icons.shopping_cart), label: Text('Cart')),
                NavigationRailDestination(icon: Icon(Icons.person), label: Text('Profile')),
              ],
            ),
          Expanded(
            child: CustomScrollView(
              slivers: [
                SliverAppBar(
                  title: const Text('Erik Shop'),
                  floating: true,
                  actions: [
                    IconButton(icon: const Icon(Icons.search), onPressed: () {}),
                    IconButton(icon: const Icon(Icons.shopping_cart_outlined), onPressed: () => context.push('/cart')),
                  ],
                ),
                SliverPadding(
                  padding: const EdgeInsets.all(16),
                  sliver: SliverToBoxAdapter(
                    child: _loading
                        ? const Center(child: CircularProgressIndicator())
                        : _buildProductGrid(isDesktop),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: isDesktop ? null : NavigationBar(
        selectedIndex: _selectedNav,
        onDestinationSelected: (i) {
          setState(() => _selectedNav = i);
          if (i == 2) context.push('/cart');
          if (i == 3) context.push('/profile');
        },
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.shopping_bag), label: 'Products'),
          NavigationDestination(icon: Icon(Icons.shopping_cart), label: 'Cart'),
          NavigationDestination(icon: Icon(Icons.person), label: 'Profile'),
        ],
      ),
    );
  }

  Widget _buildProductGrid(bool isDesktop) {
    final crossAxisCount = isDesktop ? 4 : 2;
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: crossAxisCount, childAspectRatio: 0.7,
        crossAxisSpacing: 12, mainAxisSpacing: 12,
      ),
      itemCount: _hotProducts.length,
      itemBuilder: (_, i) => ProductCard(product: _hotProducts[i]),
    );
  }
}
