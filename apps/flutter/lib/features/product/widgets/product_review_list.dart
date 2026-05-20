import 'package:flutter/material.dart';
import '../../../core/api/api_client.dart';

class ProductReviewList extends StatefulWidget {
  final String productId;
  const ProductReviewList({super.key, required this.productId});
  @override
  State<ProductReviewList> createState() => _ProductReviewListState();
}

class _ProductReviewListState extends State<ProductReviewList> {
  List<Map<String, dynamic>> _reviews = [];
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    final res = await ApiClient.instance.get('/reviews/${widget.productId}');
    if (mounted && res.isSuccess && res.data != null) {
      final data = res.data as Map<String, dynamic>;
      setState(() { _reviews = List<Map<String, dynamic>>.from(data['list'] ?? []); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) => _loading
    ? const Center(child: CircularProgressIndicator())
    : _reviews.isEmpty
      ? const Center(child: Text('暂无评价'))
      : ListView.builder(
          shrinkWrap: true, physics: const NeverScrollableScrollPhysics(),
          itemCount: _reviews.length, itemBuilder: (_, i) {
            final r = _reviews[i];
            return Card(child: Padding(padding: const EdgeInsets.all(12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                ...List.generate(5, (s) => Icon(s < (r['rating'] as int? ?? 5) ? Icons.star : Icons.star_border, size: 16, color: Colors.amber)),
                const Spacer(),
                Text(r['created_at'] ?? '', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
              ]),
              if (r['content'] != null && r['content'] != '') Padding(padding: const EdgeInsets.only(top: 8), child: Text(r['content'], style: const TextStyle(fontSize: 14))),
            ])));
          },
        );
}
